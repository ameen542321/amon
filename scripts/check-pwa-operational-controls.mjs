import fs from 'node:fs';

const read = (path) => fs.readFileSync(path, 'utf8');
const config = read('config/pwa.php');
const component = read('resources/views/components/pwa-runtime-config.blade.php');
const registration = read('resources/js/features/pwa/register-service-worker.js');
const sync = read('resources/js/features/pwa/outbox-sync.js');
const inventory = read('resources/js/features/accountant/inventory-count.js');
const env = read('.env.example');
const layouts = ['resources/views/dashboard/app.blade.php', 'resources/views/layouts/auth.blade.php', 'resources/views/welcome.blade.php'];

const checks = [
    ['PWA switches are configurable and enabled by default', ['service_worker_enabled', 'updates_enabled', 'outbox_enabled'].every((key) => config.includes(`'${key}'`))],
    ['environment example documents every PWA switch', ['PWA_SERVICE_WORKER_ENABLED', 'PWA_UPDATES_ENABLED', 'PWA_OUTBOX_ENABLED'].every((key) => env.includes(`${key}=true`))],
    ['runtime component exposes booleans without inline scripts', component.includes('pwa-service-worker-enabled') && component.includes('pwa-updates-enabled') && component.includes('pwa-outbox-enabled') && !component.includes('<script')],
    ['all application shells expose one shared runtime contract', layouts.every((path) => read(path).includes('<x-pwa-runtime-config />'))],
    ['disabled service worker is unregistered', registration.includes("announceStatus('disabled')") && registration.includes('registration.unregister()')],
    ['update prompt obeys its switch', registration.includes('!pwaRuntimeConfig.updatesEnabled')],
    ['outbox synchronization obeys its switch', sync.includes('!pwaRuntimeConfig.outboxEnabled')],
    ['offline inventory remains a local draft when outbox is disabled', inventory.includes('الحفظ المؤجل متوقف مؤقتًا') && inventory.includes('!pwaRuntimeConfig.outboxEnabled')],
];

for (const [label, passed] of checks) {
    if (!passed) throw new Error(`FAIL: ${label}`);
    console.log(`PASS: ${label}`);
}
console.log(`PWA operational controls passed (${checks.length} checks).`);
