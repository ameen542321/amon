import { readFileSync, existsSync } from 'node:fs';

const failures = [];
const fail = (message) => failures.push(message);

const requiredFiles = [
    'public/manifest.webmanifest',
    'public/sw.js',
    'public/offline.html',
    'public/css/offline.css',
    'public/js/offline.js',
    'public/icons/carled.svg',
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

const icon = readFileSync('public/icons/carled.svg', 'utf8');
if (!icon.includes('viewBox="0 0 512 512"')) fail('SVG icon viewBox is invalid');

const worker = readFileSync('public/sw.js', 'utf8');
for (const prefix of ['/admin', '/user', '/accountant', '/api', '/device-token']) {
    if (!worker.includes(`'${prefix}'`)) fail(`private prefix ${prefix} is not excluded from cache`);
}
if (!worker.includes("caches.match('/offline.html')")) fail('offline navigation fallback is missing');
if (!worker.includes('isStaticAsset')) fail('runtime cache is not limited to static assets');
if (worker.includes('skipWaiting')) fail('worker must not replace an active form session immediately');

const app = readFileSync('resources/js/app.js', 'utf8');
if (!app.includes("./features/pwa/register-service-worker")) fail('service worker registration is not imported');

for (const layoutPath of ['resources/views/dashboard/app.blade.php', 'resources/views/layouts/auth.blade.php']) {
    const layout = readFileSync(layoutPath, 'utf8');
    if (!layout.includes('manifest.webmanifest')) fail(`${layoutPath} does not link the manifest`);
    if (!layout.includes('icons/carled.svg')) fail(`${layoutPath} does not link the SVG icon`);
}

if (failures.length) {
    for (const message of failures) console.error(`FAIL: ${message}`);
    process.exit(1);
}

console.log('PWA contract passed with text-only SVG assets.');
