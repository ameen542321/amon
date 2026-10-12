import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { IDBFactory, IDBKeyRange } from 'fake-indexeddb';

const root = resolve(import.meta.dirname, '../..');
const json = (data) =>
    new Response(JSON.stringify({ data, meta: {} }), {
        headers: { 'Content-Type': 'application/json' },
    });
const operation = (overrides = {}) => ({
    localId: 'inventory-count:accountant:1:store:9:session:3:server-draft',
    operationType: 'inventory-count-draft',
    accountScope: 'accountant:1',
    storeId: 9,
    serverVersion: '2026-10-12T10:00:00Z',
    url: '/accountant/inventory-counts/3/items',
    method: 'PUT',
    payload: {
        session_version: '2026-10-12T10:00:00Z',
        'items[1][accountant_quantity]': '5',
    },
    idempotencyKey: 'test-stable-id-123',
    status: 'queued',
    attempts: 0,
    createdAt: Date.now(),
    ...overrides,
});
const storage = (initial) => {
    const values = new Map(Object.entries(initial ?? {}));
    return new Proxy(
        {
            getItem: (key) => values.get(key) ?? null,
            setItem: (key, value) => values.set(key, String(value)),
            removeItem: (key) => values.delete(key),
        },
        {
            ownKeys: () => [...values.keys()],
            getOwnPropertyDescriptor: () => ({
                enumerable: true,
                configurable: true,
            }),
        },
    );
};
const harness = (options = {}) => {
    const window = new EventTarget();
    const document = new EventTarget();
    const scope = options.scope ?? 'accountant:1';
    const localStorage =
        options.storage ?? storage({ 'carled-active-account': scope });
    const location = {
        origin: 'https://carled.test',
        hostname: 'carled.test',
        href: 'https://carled.test/accountant/dashboard',
        reload: () => {},
    };
    Object.assign(window, {
        location,
        setTimeout,
        clearTimeout,
        setInterval: () => 0,
        indexedDB: options.indexedDB ?? new IDBFactory(),
        crypto: globalThis.crypto,
        isSecureContext: true,
    });
    document.querySelector = (selector) => {
        if (selector === '[data-client-account-scope]')
            return { dataset: { clientAccountScope: scope } };
        if (selector === '[data-pwa-session-ended]') return {};
        if (selector.startsWith('meta[name="pwa-')) return { content: 'true' };
        return null;
    };
    document.querySelectorAll = () => [];
    document.visibilityState = 'visible';
    document.readyState = 'loading';
    document.body = { classList: { add() {}, remove() {} } };
    const navigator = {
        onLine: true,
        locks: options.locks ?? {
            request: async (_key, config, task) =>
                (typeof config === 'function' ? config : task)({ name: _key }),
        },
    };
    const context = vm.createContext({
        window,
        document,
        navigator,
        location,
        localStorage,
        sessionStorage: storage(),
        URL,
        URLSearchParams,
        Headers,
        Response,
        AbortController,
        TextEncoder,
        CustomEvent,
        Event,
        EventTarget,
        IDBKeyRange,
        console,
        setTimeout,
        clearTimeout,
        fetch: (...args) =>
            (
                options.fetch ??
                (() =>
                    json({
                        saved: true,
                        session_id: 3,
                        session_version: 'new',
                    }))
            )(...args),
        HTMLFormElement: class {},
        HTMLElement: class {},
        FormData,
    });
    const modules = new Map();
    const get = (path) => {
        path = resolve(path);
        if (!path.endsWith('.js')) path += '.js';
        if (modules.has(path)) return modules.get(path);
        const module = new vm.SourceTextModule(readFileSync(path, 'utf8'), {
            context,
            identifier: path,
        });
        modules.set(path, module);
        return module;
    };
    const load = async (name) => {
        const module = get(resolve(root, 'resources/js/features', name));
        if (module.status === 'unlinked')
            await module.link((specifier, parent) =>
                get(resolve(dirname(parent.identifier), specifier)),
            );
        if (module.status !== 'evaluated') await module.evaluate();
        return module.namespace;
    };
    return { load, context, window, document, navigator, localStorage };
};

test('HTML and malformed JSON cannot confirm a successful save', async () => {
    for (const response of [
        new Response('<html>Login</html>'),
        new Response('{}', { headers: { 'Content-Type': 'application/json' } }),
    ]) {
        const h = harness({ fetch: () => response });
        const api = await h.load('pwa/api-client');
        await assert.rejects(
            api.apiRequest('/test', { method: 'PUT' }),
            (error) => error.code === 'INVALID_RESPONSE',
        );
    }
});
test('API preserves the idempotency key and rejects redirects/cache reuse', async () => {
    let captured;
    const h = harness({
        fetch: (_url, options) => {
            captured = options;
            return json({ saved: true });
        },
    });
    const api = await h.load('pwa/api-client');
    await api.apiRequest('/test', {
        method: 'PUT',
        idempotencyKey: 'unchanged-key',
    });
    assert.equal(captured.headers.get('Idempotency-Key'), 'unchanged-key');
    assert.equal(captured.redirect, 'error');
    assert.equal(captured.cache, 'no-store');
});
test('Outbox refuses foreign origins and inconsistent server versions', async () => {
    const h = harness();
    const store = await h.load('pwa/outbox-store');
    await assert.rejects(
        store.putOutboxItem(
            operation({
                url: 'https://other.test/accountant/inventory-counts/3/items',
            }),
        ),
    );
    await assert.rejects(
        store.putOutboxItem(operation({ serverVersion: 'different' })),
    );
});
test('Drafts and pending items are isolated and account cleanup preserves another account', async () => {
    const h = harness();
    const drafts = await h.load('pwa/draft-store');
    const box = await h.load('pwa/outbox-store');
    const account = await h.load('pwa/account-context');
    await account.accountReady;
    await drafts.putDraft('a', {
        accountScope: 'accountant:1',
        storeId: 9,
        savedAt: 1,
    });
    h.localStorage.setItem('carled-active-account', 'accountant:2');
    await drafts.putDraft('b', {
        accountScope: 'accountant:2',
        storeId: 10,
        savedAt: 1,
    });
    h.localStorage.setItem('carled-active-account', 'accountant:1');
    await box.putOutboxItem(operation());
    await account.clearAccountData('accountant:1');
    assert.equal((await drafts.listDraftsForAccount('accountant:1')).length, 0);
    assert.equal((await drafts.listDraftsForAccount('accountant:2')).length, 1);
    assert.equal((await box.listOutboxForAccount('accountant:1')).length, 0);
});
test('An interrupted sending item is recovered with the SAME idempotency key', async () => {
    let key;
    const h = harness({
        fetch: (url, options) => {
            if (url.endsWith('app-context'))
                return json({ account: { type: 'accountant', id: 1 } });
            key = options.headers.get('Idempotency-Key');
            return json({ saved: true, session_id: 3, session_version: 'new' });
        },
    });
    const box = await h.load('pwa/outbox-store');
    await box.putOutboxItem(operation());
    await box.updateOutboxItem(operation().localId, {
        status: 'sending',
        lastAttemptAt: Date.now() - 61000,
        attempts: 1,
    });
    const sync = await h.load('pwa/outbox-sync');
    await sync.syncOutbox();
    assert.equal(key, 'test-stable-id-123');
    assert.equal((await box.listOutboxForAccount('accountant:1')).length, 0);
});
test('An incorrect session acknowledgement keeps the request for review', async () => {
    const h = harness({
        fetch: (url) =>
            url.endsWith('app-context')
                ? json({ account: { type: 'accountant', id: 1 } })
                : json({ saved: true, session_id: 99, session_version: 'new' }),
    });
    const box = await h.load('pwa/outbox-store');
    await box.putOutboxItem(operation());
    const sync = await h.load('pwa/outbox-sync');
    await sync.syncOutbox();
    const items = await box.listOutboxForAccount('accountant:1');
    assert.equal(items.length, 1);
    assert.equal(items[0].lastError.code, 'INVALID_RESPONSE');
});
test('Busy idempotency responses are retryable while validation conflicts are preserved', async () => {
    for (const status of [409, 422]) {
        const h = harness({
            fetch: (url) =>
                url.endsWith('app-context')
                    ? json({ account: { type: 'accountant', id: 1 } })
                    : new Response(
                          JSON.stringify({
                              error: {
                                  code: 'IDEMPOTENCY_ERROR',
                                  message: 'busy',
                              },
                          }),
                          {
                              status,
                              headers: { 'Content-Type': 'application/json' },
                          },
                      ),
        });
        const box = await h.load('pwa/outbox-store');
        await box.putOutboxItem(operation());
        const sync = await h.load('pwa/outbox-sync');
        await sync.syncOutbox();
        assert.equal(
            (await box.listOutboxForAccount('accountant:1'))[0].status,
            status === 409 ? 'failed' : 'conflict',
        );
    }
});
test('No mutation is sent if server identity differs or the tab does not own the lock', async () => {
    for (const noLock of [false, true]) {
        let mutations = 0;
        const h = harness({
            fetch: (url) => {
                if (!url.endsWith('app-context')) mutations++;
                return json({ account: { type: 'accountant', id: 2 } });
            },
        });
        const box = await h.load('pwa/outbox-store');
        await box.putOutboxItem(operation());
        if (noLock)
            h.navigator.locks.request = async (_key, _options, callback) =>
                callback(null);
        const sync = await h.load('pwa/outbox-sync');
        await sync.syncOutbox();
        assert.equal(mutations, 0);
    }
});
test('Sending or previously attempted requests cannot be overwritten with a new key', async () => {
    const h = harness();
    const box = await h.load('pwa/outbox-store');
    await box.putOutboxItem(operation());
    await box.updateOutboxItem(operation().localId, {
        status: 'failed',
        lastAttemptAt: Date.now(),
    });
    await assert.rejects(
        box.putOutboxItem(operation({ idempotencyKey: 'replacement-key' })),
    );
    assert.equal(
        (await box.listOutboxForAccount('accountant:1'))[0].idempotencyKey,
        'test-stable-id-123',
    );
});
test('Five failed attempts remain visible but do not automatically send again', async () => {
    let sent = false;
    const h = harness({
        fetch: (url) => {
            if (!url.endsWith('app-context')) sent = true;
            return json({ account: { type: 'accountant', id: 1 } });
        },
    });
    const box = await h.load('pwa/outbox-store');
    await box.putOutboxItem(operation({ attempts: 5, status: 'failed' }));
    const sync = await h.load('pwa/outbox-sync');
    await sync.syncOutbox();
    assert.equal(sent, false);
    assert.equal((await box.listOutboxForAccount('accountant:1')).length, 1);
});

test('Switching accounts clears prior account data and invalidates an old tab', async () => {
    const shared = storage({ 'carled-active-account': 'accountant:1' });
    const database = new IDBFactory();
    const first = harness({ storage: shared, indexedDB: database });
    const context = await first.load('pwa/account-context');
    await context.accountReady;
    const drafts = await first.load('pwa/draft-store');
    await drafts.putDraft('old', {
        accountScope: 'accountant:1',
        storeId: 9,
        savedAt: 1,
    });
    const second = harness({
        scope: 'accountant:2',
        storage: shared,
        indexedDB: database,
    });
    const next = await second.load('pwa/account-context');
    await next.accountReady;
    assert.equal(context.currentAccountScope(), null);
    assert.equal((await drafts.listDraftsForAccount('accountant:1')).length, 0);
    await assert.rejects(
        drafts.putDraft('resurrect', {
            accountScope: 'accountant:1',
            storeId: 9,
            savedAt: 2,
        }),
    );
});

test('Service worker preserves unrelated caches and only one previous application shell', async () => {
    const handlers = {};
    const deleted = [];
    const context = vm.createContext({
        URL,
        self: {
            location: {
                href: 'https://carled.test/sw.js?v=new',
                origin: 'https://carled.test',
            },
            clients: { claim: async () => {} },
            addEventListener: (type, callback) => {
                handlers[type] = callback;
            },
        },
        caches: {
            keys: async () => [
                'other-app',
                'carled-shell-old1',
                'carled-shell-old2',
                'carled-shell-new',
            ],
            delete: async (name) => {
                deleted.push(name);
            },
        },
    });
    vm.runInContext(
        readFileSync(resolve(root, 'public/sw.js'), 'utf8'),
        context,
    );
    let done;
    handlers.activate({
        waitUntil: (task) => {
            done = task;
        },
    });
    await done;
    assert.deepEqual(deleted, ['carled-shell-old1']);
});

test('Service worker ignores private paths and mutations and honors no-store static responses', async () => {
    const handlers = {};
    const writes = [];
    const entries = new Map();
    const response = new Response('body{}', {
        headers: { 'Content-Type': 'text/css', 'Cache-Control': 'no-store' },
    });
    Object.defineProperty(response, 'type', { value: 'basic' });
    const context = vm.createContext({
        URL,
        Response,
        self: {
            location: {
                href: 'https://carled.test/sw.js?v=new',
                origin: 'https://carled.test',
            },
            addEventListener: (type, callback) => {
                handlers[type] = callback;
            },
        },
        fetch: async () => response,
        caches: {
            match: async () => undefined,
            open: async () => ({
                match: async (request) => entries.get(request.url),
                keys: async () => [],
                put: async (...args) => {
                    writes.push(args);
                },
            }),
        },
    });
    vm.runInContext(
        readFileSync(resolve(root, 'public/sw.js'), 'utf8'),
        context,
    );
    for (const [method, path] of [
        ['POST', '/build/assets/app-123.js'],
        ['GET', '/accountant/dashboard'],
        ['GET', '/images/private-upload.jpg'],
    ]) {
        let intercepted = false;
        handlers.fetch({
            request: { method, url: 'https://carled.test' + path },
            respondWith: () => {
                intercepted = true;
            },
        });
        assert.equal(intercepted, false);
    }
    let done;
    handlers.fetch({
        request: {
            method: 'GET',
            url: 'https://carled.test/build/assets/app-123.css',
        },
        respondWith: (task) => {
            done = task;
        },
    });
    assert.equal(await done, response);
    assert.equal(writes.length, 0);
});

test('Blocked local storage disables offline account access but keeps online operation state in page memory', async () => {
    const unavailable = {
        getItem() {
            throw new Error('denied');
        },
        setItem() {
            throw new Error('denied');
        },
        removeItem() {
            throw new Error('denied');
        },
    };
    const h = harness({ storage: unavailable });
    const account = await h.load('pwa/account-context');
    await account.accountReady;
    assert.equal(account.currentAccountScope(), null);
    assert.equal(account.canUseCurrentPage(), true);
    const key = account.scopedStorageKey('quick-sale-pending', 9);
    account.writeOperationState(key, 'operation-id');
    assert.equal(account.readOperationState(key), 'operation-id');
    account.removeOperationState(key);
    assert.equal(account.readOperationState(key), null);
});
