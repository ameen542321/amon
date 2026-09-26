import { existsSync, readFileSync } from 'node:fs';

const passed = [], failed = [];
const read = (file) => existsSync(file) ? readFileSync(file, 'utf8') : '';
const check = (condition, message) => (condition ? passed : failed).push(message);
const required = [
  'app/Http/Controllers/Api/InventoryCountController.php',
  'app/Support/Api/InventoryCountResource.php',
  'tests/Unit/MobileInventoryCountApiContractTest.php',
];
required.forEach((file) => check(existsSync(file), `required file exists: ${file}`));

const routes = read('routes/api.php');
const controller = read(required[0]);
const resource = read(required[1]);
const service = read('app/Services/InventoryCountService.php');
const auth = read('app/Http/Controllers/Api/AuthTokenController.php');
const context = read('app/Http/Controllers/Api/MobileContextController.php');

check(routes.includes("prefix('inventory-counts')") && routes.includes('ability:inventory-counts:read'), 'inventory-count routes require read ability');
check(routes.includes("patch('/{session}/draft'") && routes.includes("['ability:inventory-counts:write', 'idempotency']"), 'draft save requires write ability and idempotency');
check(!routes.includes("post('/{session}/submit'") && !routes.includes("post('/{session}/approve'"), 'submit and inventory approval remain outside the mobile API');
check(controller.includes('visibleQuery') && controller.includes("where('accountant_id'") && controller.includes("where('owner_id'"), 'owner and accountant sessions are actor scoped');
check(controller.includes("whereIn('decision', ['returned', 'recounted'])") && controller.includes("where('decision', 'pending')"), 'accountants only receive editable session items');
check(controller.includes('session_version') && service.includes('expectedVersion') && service.includes('lockForUpdate'), 'draft save uses optimistic versioning and database locks');
check(controller.includes('currentShiftContext') && service.includes('count_business_date'), 'draft save derives the accounting business date on the server');
check(resource.includes('includeSystemSnapshot') && resource.includes("'system_quantity_snapshot'"), 'system snapshot disclosure is role-aware');
check(auth.includes("'inventory-counts:read'") && auth.includes("'inventory-counts:write'"), 'device sessions receive inventory-count abilities');
check(context.includes("'inventory_counts_draft_write' => true") && context.includes("'inventory_counts_approve' => false"), 'feature flags expose staged save but keep approval disabled');

passed.forEach((message) => console.log(`PASS: ${message}`));
failed.forEach((message) => console.error(`FAIL: ${message}`));
if (failed.length) process.exit(1);
console.log(`Mobile inventory-count readiness passed (${passed.length} checks).`);
