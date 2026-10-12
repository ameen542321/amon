import { pwaRuntimeConfig } from './runtime-config';
import { apiRequest } from './api-client';
import { deleteOutboxItem, listOutboxForAccount, updateOutboxItem } from './outbox-store';
import { currentAccountScope, accountReady, revokeAccount } from './account-context';

let syncing = false;
const STALE_SENDING_MS = 60 * 1000;
const notify = (type, detail = {}) => window.dispatchEvent(new CustomEvent(type, { detail }));

export const assertInventorySaved = (result, item) => {
    const sessionId = new URL(item.url, window.location.origin).pathname.split('/').at(-2);
    if (result.data?.saved !== true || String(result.data.session_id) !== sessionId || !result.data.session_version) {
        throw Object.assign(new Error('لم يؤكد الخادم حفظ جلسة الجرد. بقي الطلب محفوظًا.'), { code: 'INVALID_RESPONSE', status: 0 });
    }
};

const syncItem = async (item) => {
    await updateOutboxItem(item.localId, { status: 'sending', attempts: item.attempts + 1, lastAttemptAt: Date.now() });
    notify('carled:outbox-changed');
    try {
        const result = await apiRequest(item.url, {
            method: item.method,
            body: new URLSearchParams(item.payload),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            idempotencyKey: item.idempotencyKey,
        });
        assertInventorySaved(result, item);
        if (currentAccountScope() !== item.accountScope) return;
        await deleteOutboxItem(item.localId);
        // لا يتضمن الحدث كميات أو محتوى المسودة.
        notify('carled:outbox-synced', { localId: item.localId, accountScope: item.accountScope, storeId: item.storeId, sessionVersion: result.data.session_version, queuedAt: item.createdAt });
    } catch (error) {
        const busy = error.status === 409 && error.code === 'IDEMPOTENCY_ERROR';
        const conflict = !busy && (error.status === 409 || error.status === 422);
        const sessionExpired = [401, 403, 419].includes(error.status);
        await updateOutboxItem(item.localId, {
            status: conflict ? 'conflict' : 'failed',
            lastError: { code: error.code ?? 'SYNC_ERROR', message: error.message, requestId: error.requestId },
            nextAttemptAt: Date.now() + (busy ? 3000 : Math.min(300000, 5000 * 2 ** item.attempts)),
        });
        if (conflict) notify('carled:outbox-conflict', { localId: item.localId, accountScope: item.accountScope });
        if (sessionExpired) notify('carled:outbox-auth-required');
        if (sessionExpired || error.code === 'NETWORK_ERROR' || error.code === 'REQUEST_TIMEOUT' || error.code === 'INVALID_RESPONSE') throw error;
    } finally {
        notify('carled:outbox-changed');
    }
};

export const syncOutbox = async () => {
    await accountReady;
    const accountScope = currentAccountScope();
    if (!pwaRuntimeConfig.outboxEnabled || syncing || !navigator.onLine || !accountScope) return;
    // Web Locks يمنع إرسال تبويبين لنفس الحساب. عند غيابه تبقى المزامنة متوقفة بأمان.
    if (!navigator.locks) { notify('carled:outbox-unavailable'); return; }
    syncing = true;
    try {
        await navigator.locks.request(`carled-client:${accountScope}`, { ifAvailable: true }, async lock => {
            if (!lock) return;
            const all = await listOutboxForAccount(accountScope);
            if (!all.length) return;
            const prefix = accountScope.startsWith('accountant:') ? '/accountant' : '/user';
            const context = await apiRequest(`${prefix}/api/v1/app-context`);
            if (`${context.data?.account?.type}:${context.data?.account?.id}` !== accountScope) {
                if (context.data?.account?.id && currentAccountScope() === accountScope) revokeAccount(accountScope);
                throw new Error('تغير الحساب على الخادم. أعد فتح الصفحة قبل المزامنة.');
            }
            for (const item of all) {
                if (item.status === 'sending' && Date.now() - (item.lastAttemptAt ?? 0) > STALE_SENDING_MS) {
                    await updateOutboxItem(item.localId, { status: 'failed', nextAttemptAt: 0 });
                    item.status = 'failed'; item.nextAttemptAt = 0;
                }
            }
            const items = all.filter(item => ['queued', 'failed'].includes(item.status) && item.attempts < 5 && (item.nextAttemptAt ?? 0) <= Date.now())
                .sort((left, right) => left.createdAt - right.createdAt);
            for (const item of items) {
                if (currentAccountScope() !== accountScope || !navigator.onLine) break;
                await syncItem(item);
            }
        });
    } catch (error) {
        notify('carled:outbox-error', { message: error.message });
    } finally {
        syncing = false;
        notify('carled:outbox-changed');
    }
};

window.addEventListener('online', syncOutbox);
document.addEventListener('DOMContentLoaded', syncOutbox);
window.setInterval(() => { if (document.visibilityState === 'visible') syncOutbox(); }, 30000);
