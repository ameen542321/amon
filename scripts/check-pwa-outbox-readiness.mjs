import { existsSync, readFileSync } from 'node:fs';

const passed = [], failed = [];
const read = (file) => existsSync(file) ? readFileSync(file, 'utf8') : '';
const check = (condition, message) => (condition ? passed : failed).push(message);
const required = ['resources/js/features/pwa/outbox-store.js', 'resources/js/features/pwa/outbox-sync.js', 'docs/roadmap-systems/04-offline-outbox.md', 'tests/Unit/PwaOutboxContractTest.php'];
required.forEach((file) => check(existsSync(file), `required file exists: ${file}`));
const store = read(required[0]);
const sync = read(required[1]);
const drafts = read('resources/js/features/pwa/draft-store.js');
const lifecycle = read('resources/js/features/pwa/client-storage-lifecycle.js');
const inventory = read('resources/js/features/accountant/inventory-count.js');
const routes = read('routes/accountant.php');

check(drafts.includes('DATABASE_VERSION = 3') && drafts.includes("OUTBOX_STORE = 'outbox'"), 'IndexedDB contains a versioned outbox store');
check(store.includes("new Set(['inventory-count-draft'])") && store.includes('ALLOWED_PATH'), 'outbox uses an explicit operation and path allowlist');
check(store.includes('MAX_OUTBOX_ITEMS_PER_ACCOUNT = 25') && store.includes('MAX_PAYLOAD_BYTES = 256 * 1024'), 'outbox item count and payload size are bounded');
check(!store.includes('_token') && !store.includes('accessToken') && !store.includes('Authorization'), 'outbox store contains no authentication secrets');
check(sync.includes("['queued', 'failed']") && sync.includes('item.attempts < 5'), 'sync retries are stateful and bounded');
check(sync.includes("status: conflict ? 'conflict' : 'failed'"), 'server conflicts are preserved for user review');
check(lifecycle.includes('deleteOutboxForAccount') && lifecycle.includes('Promise.all'), 'logout clears drafts and outbox together');
check(inventory.includes('navigator.onLine') && inventory.includes('putOutboxItem') && inventory.includes('session_version'), 'inventory form queues only offline draft saves with a server version');
check(routes.includes("middleware('idempotency')->name('items.bulk-update')"), 'web draft save is protected by server idempotency');
check(read('resources/views/inventory-counts/accountant/show.blade.php').includes('name="_idempotency_key"'), 'non-JavaScript form fallback carries an idempotency key');

passed.forEach((message) => console.log(`PASS: ${message}`));
failed.forEach((message) => console.error(`FAIL: ${message}`));
if (failed.length) process.exit(1);
console.log(`PWA outbox readiness passed (${passed.length} checks).`);
