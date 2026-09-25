import { readFileSync, existsSync } from 'node:fs';

const failures = [];
const fail = (message) => failures.push(message);

const requiredFiles = [
    'public/manifest.webmanifest',
    'public/sw.js',
    'public/offline.html',
    'public/css/offline.css',
    'public/js/offline.js',
    'public/carled.svg',
    'scripts/check-pwa-deployment.mjs',
];

for (const file of requiredFiles) {
    if (!existsSync(file)) fail(`missing ${file}`);
}

for (const file of ['public/icons/carled-192.png', 'public/icons/carled-512.png']) {
    if (existsSync(file)) fail(`binary PWA icon must not be committed: ${file}`);
}

const manifest = JSON.parse(readFileSync('public/manifest.webmanifest', 'utf8'));
if (manifest.display !== 'standalone') fail('manifest display must be standalone');
if (manifest.dir !== 'rtl' || manifest.lang !== 'ar') fail('manifest must declare Arabic RTL');
if (manifest.icons?.length !== 1
    || manifest.icons[0].sizes !== 'any'
    || manifest.icons[0].type !== 'image/svg+xml') {
    fail('manifest must use the single scalable SVG icon');
}

const icon = readFileSync('public/carled.svg', 'utf8');
if (!icon.includes('viewBox="0 0 512 512"')) fail('SVG icon viewBox is invalid');

const apache = readFileSync('public/.htaccess', 'utf8');
if (!apache.includes('AddType application/manifest+json .webmanifest')) {
    fail('Apache manifest MIME type is missing');
}
if (!apache.includes('Header set Cache-Control "no-cache, no-store, must-revalidate"')) {
    fail('Apache service-worker cache protection is missing');
}

const deploymentCheck = readFileSync('scripts/check-pwa-deployment.mjs', 'utf8');
if (!deploymentCheck.includes("candidate.protocol !== 'https:'")) fail('deployment check does not require HTTPS');
if (!deploymentCheck.includes("fetchPath('/sw.js'")) fail('deployment check does not inspect the service worker');

const worker = readFileSync('public/sw.js', 'utf8');
for (const prefix of ['/admin', '/user', '/accountant', '/api', '/device-token']) {
    if (!worker.includes(`'${prefix}'`)) fail(`private prefix ${prefix} is not excluded from cache`);
}
if (!worker.includes("caches.match('/offline.html')")) fail('offline navigation fallback is missing');
if (!worker.includes('isStaticAsset')) fail('runtime cache is not limited to static assets');
if (!worker.includes("event.data?.type === 'SKIP_WAITING'")) {
    fail('worker updates must require an explicit user action');
}

const app = readFileSync('resources/js/app.js', 'utf8');
if (!app.includes("./features/pwa/register-service-worker")) fail('service worker registration is not imported');

const registration = readFileSync('resources/js/features/pwa/register-service-worker.js', 'utf8');
for (const eventName of ['beforeinstallprompt', 'appinstalled', 'offline', 'online', 'controllerchange']) {
    if (!registration.includes(`'${eventName}'`)) fail(`PWA lifecycle event is missing: ${eventName}`);
}
if (!registration.includes("postMessage({ type: 'SKIP_WAITING' })")) {
    fail('service worker update must wait for an explicit user action');
}

const panel = readFileSync('resources/views/components/pwa-install-panel.blade.php', 'utf8');
if (panel.includes('<style') || panel.includes('style="')) fail('PWA panel contains inline CSS');
if (!panel.includes('data-pwa-install') || !panel.includes('data-pwa-update')) {
    fail('PWA panel install/update controls are missing');
}

const draftStore = readFileSync('resources/js/features/pwa/draft-store.js', 'utf8');
const inventoryDraft = readFileSync('resources/js/features/accountant/inventory-count.js', 'utf8');
if (!draftStore.includes('window.indexedDB.open(DATABASE_NAME')) fail('IndexedDB draft store is missing');
if (!draftStore.includes("createObjectStore(DRAFT_STORE, { keyPath: 'key' })")) {
    fail('IndexedDB drafts object store contract is missing');
}
if (inventoryDraft.includes('window.localStorage.setItem')) fail('inventory drafts must not write to localStorage');
if (!inventoryDraft.includes('migrateLegacyDraft')) fail('legacy localStorage draft migration is missing');
if (!inventoryDraft.includes('draft.serverVersion === serverVersion')) {
    fail('inventory drafts must reject stale server versions');
}

for (const layoutPath of [
    'resources/views/welcome.blade.php',
    'resources/views/dashboard/app.blade.php',
    'resources/views/layouts/auth.blade.php',
]) {
    const layout = readFileSync(layoutPath, 'utf8');
    if (!layout.includes('manifest.webmanifest')) fail(`${layoutPath} does not link the manifest`);
    if (!layout.includes('carled.svg')) fail(`${layoutPath} does not link the SVG icon`);
}

const welcome = readFileSync('resources/views/welcome.blade.php', 'utf8');
if (!welcome.includes('<x-pwa-install-panel />')) fail('public home page does not expose the PWA install panel');

if (failures.length) {
    for (const message of failures) console.error(`FAIL: ${message}`);
    process.exit(1);
}

console.log('PWA contract passed with text-only SVG assets.');
