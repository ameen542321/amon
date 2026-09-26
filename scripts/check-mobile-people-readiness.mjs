import { existsSync, readFileSync } from 'node:fs';

const passed = [], failed = [];
const read = (file) => existsSync(file) ? readFileSync(file, 'utf8') : '';
const check = (condition, message) => (condition ? passed : failed).push(message);
const required = [
  'app/Http/Controllers/Api/PeopleController.php',
  'app/Support/Api/PeopleResource.php',
  'docs/roadmap-systems/10-employees-accountants.md',
  'tests/Unit/MobilePeopleApiContractTest.php',
];
required.forEach((file) => check(existsSync(file), `required file exists: ${file}`));

const routes = read('routes/api.php');
const controller = read(required[0]);
const resource = read(required[1]);
const auth = read('app/Http/Controllers/Api/AuthTokenController.php');
const context = read('app/Http/Controllers/Api/MobileContextController.php');

check(routes.includes("prefix('people')") && routes.includes('ability:people:read'), 'people routes require a dedicated read ability');
check(!routes.includes("Route::post('/employees") && !routes.includes("Route::patch('/employees") && !routes.includes("Route::delete('/employees"), 'mobile people API is read only');
check(controller.includes("where('store_id', $storeId)") && controller.includes('ApiStoreScopeService'), 'all people reads are store scoped');
check(controller.includes("whereKey($actor->getAuthIdentifier())"), 'accountants can only inspect their own login account');
check(controller.includes('cursorPaginate') && controller.includes("'max:100'"), 'people lists use bounded cursor pagination');
check(resource.includes('if ($includeContact)') && !resource.includes("'salary'") && !resource.includes("'suspension_reason'"), 'resource minimizes contact and financial data');
check(auth.includes("'people:read'"), 'device sessions receive people read ability');
check(context.includes("'people_directory_read' => true") && context.includes("'people_finance_read' => false") && context.includes("'people_write' => false"), 'feature flags declare the privacy-safe read-only boundary');

passed.forEach((message) => console.log(`PASS: ${message}`));
failed.forEach((message) => console.error(`FAIL: ${message}`));
if (failed.length) process.exit(1);
console.log(`Mobile people readiness passed (${passed.length} checks).`);
