const WORKER_VERSION = new URL(self.location.href).searchParams.get('v') || 'development';
const CACHE_VERSION = `carled-shell-${WORKER_VERSION}`;
const OFFLINE_ASSETS = [
    '/offline.html',
    '/css/offline.css',
    '/js/offline.js',
    '/fonts/cairo/cairo-arabic-wght-normal.woff2',
    '/carled.svg',
];
const PRIVATE_PREFIXES = ['/admin', '/user', '/accountant', '/api', '/device-token'];

const isPrivateApplicationRequest = (url) => PRIVATE_PREFIXES.some(
    (prefix) => url.pathname === prefix || url.pathname.startsWith(`${prefix}/`),
);

const MAX_STATIC_ENTRIES = 160;
const MAX_ASSET_BYTES = 2 * 1024 * 1024;
const isStaticAsset = url => !url.search && (OFFLINE_ASSETS.includes(url.pathname)
    || /^\/build\/assets\/[A-Za-z0-9._-]+\.(js|css|woff2?|ttf)$/.test(url.pathname)
    || /^\/fonts\/(Cairo|Amiri)-[A-Za-z-]+\.ttf$/.test(url.pathname)
    || /^\/icons\/carled-(192|512|180)\.png$/.test(url.pathname));

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(OFFLINE_ASSETS)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then(keys => {
                // نبقي إصدارًا سابقًا واحدًا لخدمة الأصول ذات الأسماء المرقمة للتبويبات المفتوحة.
                const previous = keys.filter(key => key.startsWith('carled-shell-') && key !== CACHE_VERSION).at(-1);
                return Promise.all(keys.filter(key => key.startsWith('carled-shell-') && key !== CACHE_VERSION && key !== previous).map(key => caches.delete(key)));
            })
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'GET_VERSION') {
        event.ports[0]?.postMessage({ version: WORKER_VERSION, cache: CACHE_VERSION });
        return;
    }

    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request, { cache: 'no-store' }).catch(() => caches.match('/offline.html', { cacheName: CACHE_VERSION })));
        return;
    }

    if (isPrivateApplicationRequest(url) || !isStaticAsset(url)) return;

    event.respondWith(caches.open(CACHE_VERSION).then(cache => cache.match(request)).then(async (cached) => {
        if (!cached && url.pathname.startsWith('/build/assets/')) cached = await caches.match(request);
        if (cached) return cached;

        const response = await fetch(request);
        const policy = response.headers.get('Cache-Control') ?? '';
        const type = response.headers.get('Content-Type') ?? '';
        if (response.ok && response.type === 'basic' && !response.redirected && !/private|no-store/i.test(policy)
            && /javascript|text\/css|font|image\/png|image\/svg/i.test(type)) {
            try {
                const copy = response.clone();
                const bytes = await copy.arrayBuffer();
                const cache = await caches.open(CACHE_VERSION);
                if (bytes.byteLength <= MAX_ASSET_BYTES && (await cache.keys()).length < MAX_STATIC_ENTRIES) {
                    await cache.put(request, new Response(bytes, { status: response.status, headers: response.headers }));
                }
            } catch { /* فشل Cache لا يمنع وصول استجابة الشبكة للمستخدم. */ }
        }

        return response;
    }));
});
