import { clientDatabaseVersion, clientDatabaseName, openClientDatabase, outboxStoreName } from './draft-store';

const ALLOWED_OPERATION_TYPES = new Set(['inventory-count-draft']);
const ALLOWED_PATH = /^\/accountant\/inventory-counts\/\d+\/items$/;
const MAX_OUTBOX_ITEMS_PER_ACCOUNT = 25;
const MAX_PAYLOAD_BYTES = 256 * 1024;

const transact = async (mode, operation) => {
    const database = await openClientDatabase();
    try {
        return await new Promise((resolve, reject) => {
            const transaction = database.transaction(outboxStoreName, mode);
            const request = operation(transaction.objectStore(outboxStoreName));
            let result = null;
            request.onsuccess = () => { result = request.result ?? null; };
            request.onerror = () => reject(request.error ?? new Error('Outbox operation failed'));
            transaction.oncomplete = () => resolve(result);
            transaction.onabort = () => reject(transaction.error ?? new Error('Outbox transaction aborted'));
        });
    } finally {
        database.close();
    }
};

const validateOperation = (operation) => {
    if (!ALLOWED_OPERATION_TYPES.has(operation.operationType)) throw new Error('Operation type is not allowed offline');
    if (!operation.accountScope || !operation.storeId) throw new Error('Outbox account and store scope are required');
    if (!/^accountant:[1-9]\d*$/.test(operation.accountScope)) throw new Error('Only accountant inventory drafts may be queued');
    const target = new URL(operation.url, window.location.origin);
    if (target.origin !== window.location.origin || target.search || target.hash || !ALLOWED_PATH.test(target.pathname)) throw new Error('Outbox URL is not allowed');
    if (String(operation.payload?.session_version) !== String(operation.serverVersion)) throw new Error('Outbox version does not match payload');
    if (operation.method !== 'PUT') throw new Error('Only inventory draft PUT is allowed');
    if (!operation.idempotencyKey || !operation.serverVersion) throw new Error('Outbox idempotency key and source version are required');
    if (new TextEncoder().encode(JSON.stringify(operation.payload)).byteLength > MAX_PAYLOAD_BYTES) throw new Error('Outbox payload exceeds the local limit');
};

const writeOutboxItem = async (operation) => {
    validateOperation(operation);
    if (localStorage.getItem('carled-active-account') !== operation.accountScope) throw new Error('انتهت جلسة الجهاز؛ لن يجهز طلب للحساب السابق.');
    const existing = await listOutboxForAccount(operation.accountScope);
    const previous = existing.find(item => item.localId === operation.localId);
    if (previous?.lastAttemptAt) throw new Error('يوجد طلب سبق إرساله لهذه الجلسة. راجعه في مركز المزامنة قبل تجهيز طلب جديد.');
    if (!existing.some((item) => item.localId === operation.localId) && existing.length >= MAX_OUTBOX_ITEMS_PER_ACCOUNT) {
        throw new Error('Outbox account limit reached');
    }
    return transact('readwrite', (store) => store.put(operation));
};

export const putOutboxItem = operation => navigator.locks
    ? navigator.locks.request(`carled-client:${operation.accountScope}`, () => writeOutboxItem(operation))
    : writeOutboxItem(operation);

export const listOutboxForAccount = (accountScope) => transact('readonly', (store) => store.index('accountScope').getAll(accountScope));
export const deleteOutboxItem = (localId) => transact('readwrite', (store) => store.delete(localId));

export const deleteOutboxForAccount = async (accountScope) => {
    if (!accountScope) return;
    const items = await listOutboxForAccount(accountScope);
    await Promise.all(items.map((item) => deleteOutboxItem(item.localId)));
};

export const updateOutboxItem = async (localId, updates) => {
    const item = await transact('readonly', (store) => store.get(localId));
    if (!item) return null;
    const updated = { ...item, ...updates, localId };
    validateOperation(updated);
    await transact('readwrite', (store) => store.put(updated));
    return updated;
};

export const outboxContract = Object.freeze({
    databaseName: clientDatabaseName,
    databaseVersion: clientDatabaseVersion,
    allowedOperations: [...ALLOWED_OPERATION_TYPES],
    maxItemsPerAccount: MAX_OUTBOX_ITEMS_PER_ACCOUNT,
    maxPayloadBytes: MAX_PAYLOAD_BYTES,
});
