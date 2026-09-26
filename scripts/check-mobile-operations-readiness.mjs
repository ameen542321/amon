import { existsSync, readFileSync } from 'node:fs';

const passed = [], failed = [];
const read = (file) => existsSync(file) ? readFileSync(file, 'utf8') : '';
const check = (condition, message) => (condition ? passed : failed).push(message);
const required = [
  'app/Http/Controllers/Api/SupportingOperationController.php',
  'app/Support/Api/SupportingOperationResource.php',
  'docs/roadmap-systems/11-supporting-financial-operations.md',
  'tests/Unit/MobileSupportingOperationApiContractTest.php',
];
required.forEach((file) => check(existsSync(file), `required file exists: ${file}`));

const routes = read('routes/api.php');
const controller = read(required[0]);
const resource = read(required[1]);
const auth = read('app/Http/Controllers/Api/AuthTokenController.php');
const context = read('app/Http/Controllers/Api/MobileContextController.php');

check(routes.includes("prefix('operations')") && routes.includes('ability:operations:read'), 'supporting-operation routes require a dedicated read ability');
check(!routes.includes("Route::post('/expenses") && !routes.includes("Route::patch('/expenses") && !routes.includes("Route::delete('/expenses"), 'mobile supporting-operation API is read only');
check(controller.includes('ApiStoreScopeService') && controller.includes("where('store_id', $storeId)"), 'all operation reads are store scoped');
check(controller.includes("betweenAccountingDates($startDate, $endDate)") && controller.includes("whereNull('business_date')"), 'accounting dates include a bounded legacy fallback');
check(controller.includes('diffInDays($end) > 366') && controller.includes("'max:100'"), 'date range and cursor page size are bounded');
check(controller.includes('OWNER_REQUIRED') && controller.includes('$actor instanceof Accountant'), 'owner purchases remain owner-only');
check(!resource.includes('cost_price') && !resource.includes("'profit'") && !resource.includes('internal_notes'), 'internal cost, profit, and notes are not exposed');
check(auth.includes("'operations:read'"), 'device sessions receive supporting-operation read ability');
check(context.includes("'supporting_operations_write' => false") && context.includes("'financial_approval_offline' => false"), 'feature flags keep financial writes and offline approval disabled');

passed.forEach((message) => console.log(`PASS: ${message}`));
failed.forEach((message) => console.error(`FAIL: ${message}`));
if (failed.length) process.exit(1);
console.log(`Mobile supporting-operation readiness passed (${passed.length} checks).`);
