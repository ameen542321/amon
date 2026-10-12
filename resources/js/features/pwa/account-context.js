import { deleteDraftsForAccount } from './draft-store';
import { deleteOutboxForAccount } from './outbox-store';

const ACTIVE_KEY = 'carled-active-account';
const declaredScope =
    document.querySelector('[data-client-account-scope]')?.dataset
        .clientAccountScope ?? null;
let available = Boolean(declaredScope);
let storageUnavailable = false;
let invalidated = false;
const operationMemory = new Map();

export const currentAccountScope = () => {
    if (!available) return null;
    try {
        return localStorage.getItem(ACTIVE_KEY) === declaredScope
            ? declaredScope
            : null;
    } catch {
        storageUnavailable = true;
        return null;
    }
};

export const scopedStorageKey = (purpose, storeId) => {
    const scope =
        currentAccountScope() ??
        (storageUnavailable && !invalidated ? declaredScope : null);
    if (!scope || !storeId)
        throw new Error('جلسة الجهاز غير متاحة. أعد فتح الصفحة.');
    return `carled:${scope}:store:${storeId}:${purpose}`;
};

export const canUseCurrentPage = () =>
    !invalidated && (storageUnavailable || Boolean(currentAccountScope()));
export const readOperationState = (key) => {
    if (operationMemory.has(key)) return operationMemory.get(key);
    try {
        return localStorage.getItem(key) ?? operationMemory.get(key) ?? null;
    } catch {
        return operationMemory.get(key) ?? null;
    }
};
export const writeOperationState = (key, value) => {
    operationMemory.set(key, value);
    try {
        localStorage.setItem(key, value);
    } catch {
        window.dispatchEvent(
            new CustomEvent('carled:client-storage-unavailable'),
        );
    }
};
export const removeOperationState = (key) => {
    operationMemory.set(key, null);
    try {
        localStorage.removeItem(key);
    } catch {
        /* تبقى حالة العملية في الذاكرة فقط عند حظر التخزين. */
    }
};

const clearLocalKeys = (scope, includeOperations = true) => {
    for (const key of Object.keys(localStorage)) {
        if (
            (!includeOperations && key === `carled:${scope}:last-sync`) ||
            (includeOperations && key.startsWith(`carled:${scope}:`)) ||
            key.startsWith(`purchase-order-draft:${scope}:`) ||
            key.startsWith(`purchase-order-draft:${scope.split(':')[1]}:`) ||
            (includeOperations &&
                (key === 'quick-sale-pending-operation' ||
                    key.startsWith('purchase-order-approval-pending-')))
        )
            localStorage.removeItem(key);
    }
};

export const clearAccountData = async (
    scope,
    { includeOperations = true } = {},
) => {
    if (!scope) return;
    clearLocalKeys(scope, includeOperations);
    await Promise.all([
        deleteDraftsForAccount(scope),
        deleteOutboxForAccount(scope),
    ]);
    window.dispatchEvent(
        new CustomEvent('carled:client-data-cleared', {
            detail: { accountScope: scope },
        }),
    );
};

export const revokeAccount = (scope) => {
    available = false;
    invalidated = true;
    operationMemory.clear();
    try {
        localStorage.setItem(`carled-revoked:${scope}`, '1');
        if (localStorage.getItem(ACTIVE_KEY) === scope)
            localStorage.removeItem(ACTIVE_KEY);
    } catch {
        /* لا تخزين محلي عند تعذره. */
    }
    window.dispatchEvent(new CustomEvent('carled:account-invalidated'));
};

const initialize = async () => {
    if (!declaredScope) return;
    try {
        const previous = localStorage.getItem(ACTIVE_KEY);
        available = false;
        localStorage.setItem(ACTIVE_KEY, declaredScope);
        if (previous && previous !== declaredScope) {
            localStorage.setItem(`carled-revoked:${previous}`, '1');
            const cleanup = () => clearAccountData(previous);
            if (navigator.locks)
                await navigator.locks.request(
                    `carled-client:${previous}`,
                    cleanup,
                );
            else await cleanup();
        }
        if (localStorage.getItem(`carled-revoked:${declaredScope}`)) {
            await clearAccountData(declaredScope);
            localStorage.removeItem(`carled-revoked:${declaredScope}`);
        }
        available = true;
        if (localStorage.getItem('quick-sale-pending-operation')) {
            localStorage.removeItem('quick-sale-pending-operation');
            try { sessionStorage.setItem('carled-legacy-sale-warning', '1'); } catch { /* قد يمنع المتصفح تخزين الجلسة. */ }
        }
        // علامات الاعتماد القديمة لا تحفظ محتوى، ولا حاجة لإبقائها على القرص.
        for (const key of Object.keys(localStorage)) {
            if (key.startsWith('purchase-order-approval-pending-'))
                localStorage.removeItem(key);
        }
    } catch {
        available = false;
        storageUnavailable = true;
        window.dispatchEvent(
            new CustomEvent('carled:client-storage-unavailable'),
        );
    }
};

export const accountReady = initialize();
window.addEventListener('storage', (event) => {
    if (event.key === ACTIVE_KEY && event.newValue !== declaredScope) {
        available = false;
        invalidated = true;
        operationMemory.clear();
        window.dispatchEvent(new CustomEvent('carled:account-invalidated'));
    }
});

window.addEventListener('carled:account-invalidated', () => {
    document.body.classList.add('ui-pwa-account-ended');
    if (document.querySelector('[data-pwa-session-ended]')) return;
    const overlay = document.createElement('div');
    overlay.dataset.pwaSessionEnded = '';
    overlay.className = 'ui-modal-backdrop';
    overlay.setAttribute('role', 'alertdialog');
    overlay.setAttribute('aria-label', 'تغيرت جلسة الجهاز');
    overlay.setAttribute('aria-modal', 'true');
    const panel = document.createElement('div');
    panel.className = 'ui-modal-panel';
    const text = document.createElement('p');
    text.className = 'ui-title';
    text.textContent =
        'تغير الحساب أو انتهت جلسة الجهاز. أعد فتح الصفحة للمتابعة.';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ui-btn ui-btn-primary mt-4';
    button.textContent = 'فتح الجلسة الحالية';
    button.addEventListener('click', () => window.location.reload());
    panel.append(text, button);
    overlay.append(panel);
    document.body.append(overlay);
    button.focus();
});

const recheckScope = async () => {
    await accountReady;
    if (available && declaredScope && !currentAccountScope())
        window.dispatchEvent(new CustomEvent('carled:account-invalidated'));
};
window.addEventListener('pageshow', recheckScope);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') recheckScope();
});
