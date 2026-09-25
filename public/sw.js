const CACHE_VERSION = 'carled-shell-v3';
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

const isStaticAsset = (url) => ['/build/', '/fonts/', '/icons/', '/images/', '/css/', '/js/']
    .some((prefix) => url.pathname.startsWith(prefix));

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_VERSION).then((cache) => cache.addAll(OFFLINE_ASSETS)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || isPrivateApplicationRequest(url)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    if (!isStaticAsset(url)) return;

    event.respondWith(caches.match(request).then(async (cached) => {
        if (cached) return cached;

        const response = await fetch(request);
        if (response.ok && response.type === 'basic') {
            const copy = response.clone();
            await caches.open(CACHE_VERSION).then((cache) => cache.put(request, copy));
        }

        return response;
    }));
});
