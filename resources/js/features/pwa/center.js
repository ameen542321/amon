import { apiRequest } from './api-client';
import {
    accountReady,
    clearAccountData,
    currentAccountScope,
    revokeAccount,
} from './account-context';
import { deleteDraft, listDraftsForAccount } from './draft-store';
import {
    deleteOutboxItem,
    listOutboxForAccount,
    updateOutboxItem,
} from './outbox-store';
import { syncOutbox } from './outbox-sync';
import { pwaRuntimeConfig } from './runtime-config';

const root = document.querySelector('[data-pwa-center]');
if (root) {
    const find = (name) => root.querySelector(`[data-pwa-${name}]`);
    const dialog = find('center-dialog');
    const trigger = find('center-open');
    const close = find('center-close');
    const status = find('server-status');
    const feedback = find('center-feedback');
    let loadedPreferences = false;
    let previouslyLocked = false;
    let verified = false;
    let refreshing = false;
    const prefix = () =>
        currentAccountScope()?.startsWith('accountant:')
            ? '/accountant'
            : '/user';
    const lastSyncKey = () => `carled:${currentAccountScope()}:last-sync`;
    const message = (text) => {
        feedback.textContent = text;
        feedback.classList.toggle('hidden', !text);
    };
    const text = (className, value) => {
        const node = document.createElement('p');
        node.className = className;
        node.textContent = value;
        return node;
    };
    const button = (label, callback, disabled = false) => {
        const node = document.createElement('button');
        node.type = 'button';
        node.className = 'ui-btn ui-btn-secondary';
        node.textContent = label;
        node.disabled = disabled;
        node.addEventListener('click', async () => {
            node.disabled = true;
            try {
                await callback();
            } catch (error) {
                message(error.message || 'تعذر إكمال العملية.');
            } finally {
                await refresh();
            }
        });
        return node;
    };
    const withAccountLock = async (action) => {
        const scope = currentAccountScope();
        if (!scope) throw new Error('انتهت جلسة الجهاز. أعد فتح الصفحة.');
        if (!navigator.locks)
            throw new Error(
                'المتصفح لا يدعم قفل المزامنة؛ استخدم متصفحًا حديثًا.',
            );
        return navigator.locks.request(
            `carled-client:${scope}`,
            { ifAvailable: true },
            (lock) => {
                if (!lock)
                    throw new Error(
                        'المزامنة قيد التنفيذ في تبويب آخر. حاول بعد انتهائها.',
                    );
                if (currentAccountScope() !== scope)
                    throw new Error('تغير الحساب أثناء العملية.');
                return action(scope);
            },
        );
    };
    const refresh = async () => {
        if (refreshing) return;
        refreshing = true;
        try {
            await accountReady;
            const scope = currentAccountScope();
            find('account-section').classList.toggle('hidden', !scope);
            find('sync').disabled =
                !scope ||
                !verified ||
                !navigator.onLine ||
                !pwaRuntimeConfig.outboxEnabled ||
                !navigator.locks;
            if (!scope) {
                find('pending-count').textContent = '0';
                return;
            }
            const [items, drafts] = await Promise.all([
                listOutboxForAccount(scope),
                listDraftsForAccount(scope),
            ]);
            if (currentAccountScope() !== scope) return;
            find('pending-count').textContent = String(items.length);
            const outbox = find('outbox-list');
            outbox.replaceChildren();
            const labels = {
                queued: 'بانتظار الإرسال',
                sending: 'جارٍ الإرسال',
                failed: 'تعذر الإرسال',
                conflict: 'تحتاج مراجعة البيانات',
            };
            for (const item of items) {
                const row = document.createElement('article');
                row.className = 'ui-pwa-item';
                const content = document.createElement('div');
                content.append(
                    text(
                        'ui-title font-bold',
                        `مسودة جرد · متجر ${item.storeId}`,
                    ),
                    text(
                        'ui-text-soft mt-1',
                        labels[item.status] ?? 'طلب محفوظ',
                    ),
                );
                if (item.lastError?.message)
                    content.append(
                        text('ui-text-soft mt-2', item.lastError.message),
                    );
                const actions = document.createElement('div');
                actions.className = 'flex flex-wrap gap-2';
                actions.append(
                    button(
                        'إعادة المحاولة',
                        async () => {
                            await withAccountLock(async (account) => {
                                const latest = (
                                    await listOutboxForAccount(account)
                                ).find(
                                    (entry) => entry.localId === item.localId,
                                );
                                if (
                                    !latest ||
                                    latest.status === 'sending' ||
                                    latest.status === 'conflict'
                                )
                                    throw new Error(
                                        'راجع حالة الطلب قبل إعادة الإرسال.',
                                    );
                                await updateOutboxItem(item.localId, {
                                    status: 'queued',
                                    attempts: 0,
                                    nextAttemptAt: 0,
                                });
                            });
                            await syncOutbox();
                        },
                        !verified ||
                            !navigator.onLine ||
                            !pwaRuntimeConfig.outboxEnabled ||
                            !navigator.locks ||
                            ['sending', 'conflict'].includes(item.status),
                    ),
                );
                const target = new URL(item.url, location.origin);
                if (
                    target.origin === location.origin &&
                    /^\/accountant\/inventory-counts\/\d+\/items$/.test(
                        target.pathname,
                    )
                ) {
                    const link = document.createElement('a');
                    link.className = 'ui-btn ui-btn-secondary';
                    link.textContent = 'مراجعة الجلسة';
                    link.href = target.pathname.replace(/\/items$/, '');
                    actions.append(link);
                }
                actions.append(
                    button(
                        'حذف الطلب المحلي',
                        async () => {
                            if (
                                !window.confirm(
                                    'حذف الطلب من الجهاز؟ لا يلغي ذلك أي حفظ تم بالفعل في الخادم.',
                                )
                            )
                                return;
                            await withAccountLock(async (account) => {
                                const latest = (
                                    await listOutboxForAccount(account)
                                ).find(
                                    (entry) => entry.localId === item.localId,
                                );
                                if (latest?.status === 'sending')
                                    throw new Error(
                                        'انتظر انتهاء إرسال الطلب.',
                                    );
                                await deleteOutboxItem(item.localId);
                            });
                        },
                        item.status === 'sending',
                    ),
                );
                row.append(content, actions);
                outbox.append(row);
            }
            if (!items.length)
                outbox.append(text('ui-text-soft', 'لا توجد طلبات مؤجلة.'));
            const list = find('drafts-list');
            list.replaceChildren();
            for (const draft of drafts) {
                const row = document.createElement('article');
                row.className = 'ui-pwa-item';
                const content = document.createElement('div');
                content.append(
                    text(
                        'ui-title font-bold',
                        `${draft.draftType === 'purchase-order' ? 'طلبية توريد' : 'مسودة جرد'} · متجر ${draft.storeId}`,
                    ),
                    text(
                        'ui-text-caption mt-1',
                        new Date(draft.savedAt).toLocaleString(
                            'ar-SA-u-ca-gregory',
                            { timeZone: 'Asia/Riyadh' },
                        ),
                    ),
                );
                row.append(
                    content,
                    button('تنزيل المسودة', async () => {
                        if (currentAccountScope() !== scope)
                            throw new Error('تغير الحساب.');
                        const file = new Blob(
                            [JSON.stringify(draft, null, 2)],
                            { type: 'application/json' },
                        );
                        const url = URL.createObjectURL(file);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `draft-${draft.draftType}-${draft.storeId}.json`;
                        link.click();
                        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
                    }),
                    button('حذف المسودة', async () => {
                        if (
                            !window.confirm(
                                'حذف المسودة المحلية؟ لا يغير ذلك بيانات الخادم.',
                            )
                        )
                            return;
                        await withAccountLock(async (account) => {
                            const pending = await listOutboxForAccount(account);
                            if (
                                pending.some((item) =>
                                    item.localId.startsWith(`${draft.key}:`),
                                )
                            )
                                throw new Error(
                                    'راجع الطلب المؤجل المرتبط بالمسودة أولًا.',
                                );
                            await deleteDraft(draft.key);
                        });
                    }),
                );
                list.append(row);
            }
            if (!drafts.length)
                list.append(
                    text('ui-text-soft', 'لا توجد مسودات محفوظة لهذا الحساب.'),
                );
            const bytes = new TextEncoder().encode(
                JSON.stringify([...items, ...drafts]),
            ).length;
            find('storage-size').textContent =
                `${drafts.length} مسودات · حجم بيانات الحساب تقريبًا ${(bytes / 1024).toFixed(1)} كيلوبايت`;
            const last = localStorage.getItem(lastSyncKey());
            find('last-sync').textContent = last
                ? `آخر مزامنة ناجحة: ${new Date(Number(last)).toLocaleString('ar-SA-u-ca-gregory', { timeZone: 'Asia/Riyadh' })}`
                : 'لم تتم مزامنة طلبات على هذا الجهاز.';
        } catch {
            message(
                'تعذر فتح بيانات الجهاز. قد يكون التخزين محظورًا أو ممتلئًا.',
            );
        } finally {
            refreshing = false;
        }
    };
    const check = async () => {
        await accountReady;
        verified = false;
        if (!navigator.onLine)
            status.textContent = 'الاتصال منقطع؛ بيانات الخادم غير متاحة.';
        else if (!currentAccountScope())
            status.textContent =
                'اتصال الشبكة متاح؛ سجل الدخول للتحقق من الخادم والمزامنة.';
        else {
            const scope = currentAccountScope();
            status.textContent = 'جارٍ التحقق من الخادم…';
            try {
                const result = await apiRequest(
                    `${prefix()}/api/v1/app-context`,
                    { timeout: 5000 },
                );
                verified =
                    currentAccountScope() === scope &&
                    `${result.data?.account?.type}:${result.data?.account?.id}` ===
                        scope;
                if (!verified && currentAccountScope() === scope && result.data?.account?.id) revokeAccount(scope);
                status.textContent = verified
                    ? 'متصل بالخادم · الحساب مؤكد'
                    : 'تغير الحساب؛ أعد فتح الصفحة قبل المتابعة.';
            } catch (error) {
                status.textContent = [401, 403, 419].includes(error.status)
                    ? 'انتهت الجلسة؛ سجل الدخول مجددًا.'
                    : 'الشبكة متاحة لكن تعذر الوصول إلى الخادم.';
            }
        }
        find('connectivity').textContent = verified
            ? 'متصل'
            : navigator.onLine
              ? 'غير مؤكد'
              : 'دون اتصال';
        await refresh();
    };
    const loadPreferences = async () => {
        const form = find('preferences');
        const save = find('preferences-save');
        save.disabled = true;
        if (!verified || !navigator.onLine) return;
        if (loadedPreferences) {
            save.disabled = false;
            return;
        }
        const scope = currentAccountScope();
        try {
            const result = await apiRequest(
                `${prefix()}/api/v1/pwa/preferences`,
            );
            if (currentAccountScope() !== scope) return;
            for (const key of ['announcements', 'messages', 'operations'])
                form.elements[key].checked = result.data[key] === true;
            loadedPreferences = true;
            save.disabled = false;
            window.dispatchEvent(
                new CustomEvent('carled:form-saved', { detail: { form } }),
            );
        } catch {
            message('تعذر تحميل تفضيلات الإشعارات من الخادم.');
        }
    };
    const hide = () => {
        dialog.classList.add('hidden');
        if (!previouslyLocked) document.body.classList.remove('ui-scroll-lock');
        trigger.focus();
    };
    trigger.addEventListener('click', async () => {
        previouslyLocked = document.body.classList.contains('ui-scroll-lock');
        dialog.classList.remove('hidden');
        document.body.classList.add('ui-scroll-lock');
        close.focus();
        message('');
        await check();
        await loadPreferences();
    });
    close.addEventListener('click', hide);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) hide();
    });
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            hide();
        }
        if (event.key !== 'Tab') return;
        const items = [
            ...dialog.querySelectorAll(
                'button:not(:disabled), a[href], input:not(:disabled)',
            ),
        ].filter((node) => node.getClientRects().length);
        const first = items[0],
            last = items.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    });
    find('check').addEventListener('click', async () => {
        await check();
        await loadPreferences();
    });
    find('sync').addEventListener('click', async () => {
        find('sync').disabled = true;
        await syncOutbox();
        await refresh();
    });
    find('install-help').addEventListener('click', () => {
        hide();
        window.dispatchEvent(new CustomEvent('carled:pwa-install-request'));
    });
    find('clear-account').addEventListener('click', async () => {
        if (
            !window.confirm(
                'حذف جميع المسودات والطلبات المؤجلة لهذا الحساب من الجهاز؟ بيانات الخادم لن تتغير.',
            )
        )
            return;
        try {
            await withAccountLock((scope) =>
                clearAccountData(scope, { includeOperations: false }),
            );
            message(
                'حُذفت المسودات والطلبات المؤجلة من الجهاز. النماذج المفتوحة قد تحفظ مسودة جديدة عند تعديلها.',
            );
        } catch (error) {
            message(error.message);
        }
        await refresh();
    });
    find('preferences').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (
            !loadedPreferences ||
            !verified ||
            !navigator.onLine ||
            !currentAccountScope()
        )
            return;
        const save = find('preferences-save');
        save.disabled = true;
        try {
            const values = Object.fromEntries(
                ['announcements', 'messages', 'operations'].map((key) => [
                    key,
                    event.target.elements[key].checked,
                ]),
            );
            await apiRequest(`${prefix()}/api/v1/pwa/preferences`, {
                method: 'PUT',
                body: JSON.stringify(values),
                headers: { 'Content-Type': 'application/json' },
            });
            window.dispatchEvent(
                new CustomEvent('carled:form-saved', {
                    detail: { form: event.target },
                }),
            );
            message('حُفظت تفضيلات الإشعارات.');
        } catch (error) {
            message(error.message);
        } finally {
            save.disabled = !verified || !navigator.onLine;
        }
    });
    window.addEventListener('carled:outbox-synced', (event) => {
        if (event.detail.accountScope === currentAccountScope())
            localStorage.setItem(lastSyncKey(), String(Date.now()));
        refresh();
    });
    for (const name of ['carled:outbox-changed', 'carled:client-data-cleared'])
        window.addEventListener(name, refresh);
    window.addEventListener('carled:outbox-error', (event) =>
        message(event.detail.message),
    );
    window.addEventListener('carled:outbox-unavailable', () =>
        message('المزامنة تتطلب متصفحًا يدعم Web Locks؛ بقيت الطلبات محفوظة.'),
    );
    window.addEventListener('carled:client-storage-unavailable', () =>
        message(
            'التخزين المحلي غير متاح؛ احفظ في الخادم وراجع نتيجة أي عملية قبل إعادة تسجيلها.',
        ),
    );
    window.addEventListener('carled:account-invalidated', () => {
        verified = false;
        loadedPreferences = false;
        message('تغير الحساب أو انتهت جلسة الجهاز. أعد فتح الصفحة.');
        refresh();
    });
    window.addEventListener('offline', () => {
        verified = false;
        find('preferences-save').disabled = true;
        check();
    });
    window.addEventListener('online', check);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') check();
    });
    window.setInterval(() => {
        if (document.visibilityState === 'visible') check();
    }, 60000);
    document.addEventListener('DOMContentLoaded', check);
}
