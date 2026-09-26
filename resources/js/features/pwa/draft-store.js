const DATABASE_NAME = 'carled-client';
const DATABASE_VERSION = 3;
const DRAFT_STORE = 'drafts';
const OUTBOX_STORE = 'outbox';
const MAX_DRAFT_BYTES = 256 * 1024;
const MAX_DRAFTS_PER_ACCOUNT = 50;

const openDatabase = () => new Promise((resolve, reject) => {
    if (!('indexedDB' in window)) {
        reject(new Error('IndexedDB is not supported'));
        return;
    }

    const request = window.indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
    request.onerror = () => reject(request.error ?? new Error('Unable to open IndexedDB'));
    request.onupgradeneeded = () => {
        const database = request.result;
        const store = database.objectStoreNames.contains(DRAFT_STORE)
            ? request.transaction.objectStore(DRAFT_STORE)
            : database.createObjectStore(DRAFT_STORE, { keyPath: 'key' });

        if (!store.indexNames.contains('savedAt')) store.createIndex('savedAt', 'savedAt');
        if (!store.indexNames.contains('accountScope')) store.createIndex('accountScope', 'accountScope');

        store.openCursor().onsuccess = (event) => {
            const cursor = event.target.result;
            if (!cursor) return;
            const draft = cursor.value;
            const legacyMatch = /^inventory-count:(\d+):/.exec(draft.key ?? '');
            if (!draft.accountScope && legacyMatch) {
                cursor.update({
                    ...draft,
                    schemaVersion: 2,
                    accountScope: `accountant:${legacyMatch[1]}`,
                    accountType: 'accountant',
                    draftType: 'inventory-count',
                });
            }
            cursor.continue();
        };
        if (!database.objectStoreNames.contains(OUTBOX_STORE)) {
            const outbox = database.createObjectStore(OUTBOX_STORE, { keyPath: 'localId' });
            outbox.createIndex('accountScope', 'accountScope');
            outbox.createIndex('status', 'status');
            outbox.createIndex('createdAt', 'createdAt');
        }
    };
    request.onsuccess = () => resolve(request.result);
});

const withStore = async (mode, operation) => {
    const database = await openDatabase();

    try {
        return await new Promise((resolve, reject) => {
            const transaction = database.transaction(DRAFT_STORE, mode);
            const store = transaction.objectStore(DRAFT_STORE);
            const request = operation(store);
            let result = null;
            request.onsuccess = () => {
                result = request.result ?? null;
            };
            request.onerror = () => reject(request.error ?? new Error('IndexedDB operation failed'));
            transaction.oncomplete = () => resolve(result);
            transaction.onabort = () => reject(transaction.error ?? new Error('IndexedDB transaction aborted'));
        });
    } finally {
        database.close();
    }
};

export const openClientDatabase = openDatabase;
export const clientDatabaseName = DATABASE_NAME;
export const clientDatabaseVersion = DATABASE_VERSION;
export const outboxStoreName = OUTBOX_STORE;

export const getDraft = (key) => withStore('readonly', (store) => store.get(key));

const enforceAccountLimit = async (accountScope) => {
    const database = await openDatabase();

    try {
        await new Promise((resolve, reject) => {
            const transaction = database.transaction(DRAFT_STORE, 'readwrite');
            const store = transaction.objectStore(DRAFT_STORE);
            const request = store.index('accountScope').getAll(accountScope);
            request.onerror = () => reject(request.error ?? new Error('Unable to inspect account drafts'));
            request.onsuccess = () => request.result
                .sort((left, right) => (right.savedAt ?? 0) - (left.savedAt ?? 0))
                .slice(MAX_DRAFTS_PER_ACCOUNT)
                .forEach((draft) => store.delete(draft.key));
            transaction.oncomplete = () => resolve();
            transaction.onabort = () => reject(transaction.error ?? new Error('Draft limit transaction aborted'));
        });
    } finally {
        database.close();
    }
};

export const putDraft = async (key, payload) => {
    if (!payload.accountScope) throw new Error('Draft account scope is required');
    if (new TextEncoder().encode(JSON.stringify(payload)).byteLength > MAX_DRAFT_BYTES) {
        throw new Error('Draft exceeds the local size limit');
    }

    await withStore('readwrite', (store) => store.put({ ...payload, key }));
    await enforceAccountLimit(payload.accountScope);
};

export const deleteDraft = (key) => withStore('readwrite', (store) => store.delete(key));

export const deleteDraftsForAccount = async (accountScope) => {
    if (!accountScope) return;

    const database = await openDatabase();
    try {
        await new Promise((resolve, reject) => {
            const transaction = database.transaction(DRAFT_STORE, 'readwrite');
            const request = transaction.objectStore(DRAFT_STORE).index('accountScope').openCursor(accountScope);
            request.onerror = () => reject(request.error ?? new Error('Unable to clear account drafts'));
            request.onsuccess = () => {
                const cursor = request.result;
                if (!cursor) return;
                cursor.delete();
                cursor.continue();
            };
            transaction.oncomplete = () => resolve();
            transaction.onabort = () => reject(transaction.error ?? new Error('Account draft cleanup aborted'));
        });
    } finally {
        database.close();
    }
};

export const pruneDrafts = async (oldestAllowedTimestamp) => {
    const database = await openDatabase();

    try {
        await new Promise((resolve, reject) => {
            const transaction = database.transaction(DRAFT_STORE, 'readwrite');
            const index = transaction.objectStore(DRAFT_STORE).index('savedAt');
            const request = index.openCursor(IDBKeyRange.upperBound(oldestAllowedTimestamp, true));
            request.onerror = () => reject(request.error ?? new Error('Unable to prune IndexedDB drafts'));
            request.onsuccess = () => {
                const cursor = request.result;
                if (!cursor) return;
                cursor.delete();
                cursor.continue();
            };
            transaction.oncomplete = () => resolve();
            transaction.onabort = () => reject(transaction.error ?? new Error('Draft pruning aborted'));
        });
    } finally {
        database.close();
    }
};
