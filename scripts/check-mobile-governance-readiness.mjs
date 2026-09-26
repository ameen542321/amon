import { existsSync, readFileSync } from 'node:fs';

const passed = [], failed = [];
const read = (file) => existsSync(file) ? readFileSync(file, 'utf8') : '';
const check = (condition, message) => (condition ? passed : failed).push(message);
const required = ['app/Http/Controllers/Api/GovernanceController.php', 'docs/roadmap-systems/13-subscriptions-administration-security.md', 'tests/Unit/MobileGovernanceApiContractTest.php'];
required.forEach((file) => check(existsSync(file), `required file exists: ${file}`));
const routes = read('routes/api.php');
const controller = read(required[0]);
const auth = read('app/Http/Controllers/Api/AuthTokenController.php');
const context = read('app/Http/Controllers/Api/MobileContextController.php');

check(routes.includes("prefix('governance')") && routes.includes('ability:governance:read'), 'governance endpoints require a dedicated ability');
check(!routes.includes("Route::post('/governance") && !routes.includes("Route::patch('/governance") && !routes.includes("Route::delete('/governance"), 'governance API is read only');
check(controller.includes('$actor instanceof Accountant') && controller.includes('متاحة للمالك فقط'), 'governance endpoints are owner-only');
check(controller.includes("where('user_id', $owner->id)") && controller.includes("where('actor_type', 'user')"), 'subscriptions and sessions are owner scoped');
check(controller.includes("orWhereIn('store_id', $storeIds)"), 'audit records are limited to owner stores');
check(!controller.includes('token_hash') && !controller.includes('refresh_token_hash') && !controller.includes('last_ip') && !controller.includes('last_user_agent'), 'session responses omit secrets and network fingerprints');
check(!controller.includes("'details' =>") && !controller.includes("'description' =>"), 'audit response omits sensitive log payloads');
check(auth.includes("'governance:read'"), 'device sessions receive governance read ability');
check(context.includes("'subscription_write' => false") && context.includes("'security_settings_write' => false"), 'feature flags keep sensitive administration disabled');

passed.forEach((message) => console.log(`PASS: ${message}`));
failed.forEach((message) => console.error(`FAIL: ${message}`));
if (failed.length) process.exit(1);
console.log(`Mobile governance readiness passed (${passed.length} checks).`);
