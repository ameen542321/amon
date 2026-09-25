const DATABASE_NAME = 'carled-client';
const DATABASE_VERSION = 1;
const DRAFT_STORE = 'drafts';

const openDatabase = () => new Promise((resolve, reject) => {
    if (!('indexedDB' in window)) {
        reject(new Error('IndexedDB is not supported'));
        return;
    }

    const request = window.indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
    request.onerror = () => reject(request.error ?? new Error('Unable to open IndexedDB'));
    request.onupgradeneeded = () => {
        const database = request.result;
        if (!database.objectStoreNames.contains(DRAFT_STORE)) {
            const store = database.createObjectStore(DRAFT_STORE, { keyPath: 'key' });
            store.createIndex('savedAt', 'savedAt');
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

export const getDraft = (key) => withStore('readonly', (store) => store.get(key));

export const putDraft = (key, payload) => withStore('readwrite', (store) => store.put({ key, ...payload }));

export const deleteDraft = (key) => withStore('readwrite', (store) => store.delete(key));

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
