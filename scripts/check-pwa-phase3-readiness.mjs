import { readFileSync } from 'node:fs';

const passes = [];
const failures = [];
const source = (path) => readFileSync(path, 'utf8');
const check = (condition, message) => (condition ? passes : failures).push(message);
const manifest = JSON.parse(source('public/manifest.webmanifest'));
const worker = source('public/sw.js');
const registration = source('resources/js/features/pwa/register-service-worker.js');
const draft = source('resources/js/features/accountant/inventory-count.js');

check(manifest.lang === 'ar' && manifest.dir === 'rtl' && manifest.display === 'standalone', 'manifest is Arabic RTL standalone');
check(Array.isArray(manifest.shortcuts) && manifest.shortcuts.length >= 2, 'manifest exposes role shortcuts');
check(worker.includes('WORKER_VERSION') && worker.includes('carled-shell-${WORKER_VERSION}'), 'worker cache is build-versioned');
check(worker.includes("fetch(request, { cache: 'no-store' })") && worker.includes("caches.match('/offline.html')"), 'navigation is network-first with offline fallback');
check(worker.includes('isPrivateApplicationRequest(url) || !isStaticAsset(url)'), 'private data and APIs are never runtime cached');
check(worker.includes("event.data?.type === 'GET_VERSION'") && worker.includes('event.ports[0]?.postMessage'), 'worker exposes its runtime identity');
check(registration.includes('pwa-build-version') && registration.includes('/sw.js?v=${encodeURIComponent(buildVersion)}'), 'registration requests an explicit build version');
check(registration.includes('carled:pwa-prepare-update') && registration.includes('Promise.allSettled'), 'update waits for draft preparation');
check(registration.includes('UPDATE_PREPARATION_TIMEOUT') && registration.includes("announceStatus('update-blocked'"), 'failed or stalled preparation blocks activation');
check(registration.includes("type: 'GET_VERSION'") && registration.includes("announceStatus('version-mismatch'"), 'registration verifies the deployed worker version');
check(draft.includes('carled:pwa-prepare-update') && draft.includes('pending?.push(persistDraft())'), 'inventory draft flush participates in update handshake');

for (const file of ['resources/views/dashboard/app.blade.php', 'resources/views/layouts/auth.blade.php', 'resources/views/welcome.blade.php']) {
    check(source(file).includes('name="pwa-build-version"'), `${file} exposes build version`);
}

passes.forEach((message) => console.log(`PASS: ${message}`));
failures.forEach((message) => console.error(`FAIL: ${message}`));
if (failures.length) process.exit(1);
console.log(`PWA phase 3 readiness passed (${passes.length} checks).`);
