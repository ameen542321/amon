import { pwaRuntimeConfig } from './runtime-config';
import { apiRequest } from './api-client';
import { deleteOutboxItem, listOutboxForAccount, updateOutboxItem } from './outbox-store';

let syncing = false;

const syncItem = async (item) => {
    await updateOutboxItem(item.localId, { status: 'sending', attempts: item.attempts + 1, lastAttemptAt: Date.now() });
    try {
        await apiRequest(item.url, {
            method: item.method,
            body: new URLSearchParams(item.payload),
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            idempotencyKey: item.idempotencyKey,
        });
        await deleteOutboxItem(item.localId);
        window.dispatchEvent(new CustomEvent('carled:outbox-synced', { detail: { localId: item.localId } }));
    } catch (error) {
        const conflict = error.status === 409 || error.status === 422;
        await updateOutboxItem(item.localId, {
            status: conflict ? 'conflict' : 'failed',
            lastError: { code: error.code, message: error.message, requestId: error.requestId },
        });
        if (conflict) window.dispatchEvent(new CustomEvent('carled:outbox-conflict', { detail: { localId: item.localId } }));
        if (error.code === 'NETWORK_ERROR' || error.code === 'REQUEST_TIMEOUT') throw error;
    }
};

export const syncOutbox = async () => {
    const accountScope = document.querySelector('[data-client-account-scope]')?.dataset.clientAccountScope;
    if (!pwaRuntimeConfig.outboxEnabled || syncing || !navigator.onLine || !accountScope) return;
    syncing = true;
    try {
        const items = (await listOutboxForAccount(accountScope))
            .filter((item) => ['queued', 'failed'].includes(item.status) && item.attempts < 5)
            .sort((left, right) => left.createdAt - right.createdAt);
        for (const item of items) await syncItem(item);
    } finally {
        syncing = false;
    }
};

window.addEventListener('online', syncOutbox);
document.addEventListener('DOMContentLoaded', syncOutbox);
