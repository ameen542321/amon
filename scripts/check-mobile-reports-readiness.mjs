import { existsSync, readFileSync } from 'node:fs';

const passed = [], failed = [];
const read = (file) => existsSync(file) ? readFileSync(file, 'utf8') : '';
const check = (condition, message) => (condition ? passed : failed).push(message);
const required = ['app/Http/Controllers/Api/ReportController.php', 'docs/roadmap-systems/12-reports-pdf.md', 'tests/Unit/MobileReportApiContractTest.php'];
required.forEach((file) => check(existsSync(file), `required file exists: ${file}`));
const routes = read('routes/api.php');
const controller = read(required[0]);
const auth = read('app/Http/Controllers/Api/AuthTokenController.php');
const context = read('app/Http/Controllers/Api/MobileContextController.php');

check(routes.includes("prefix('reports')") && routes.includes('ability:reports:read'), 'report API requires a dedicated read ability');
check(routes.includes("middleware(['api.contract', 'signed', 'throttle:30,1'])"), 'download route requires no-store API headers, a valid signature, and rate limit');
check(routes.includes("middleware('idempotency')"), 'download-link creation is idempotent');
check(controller.includes('MonthlyStoreReportService') && controller.includes('StoreTransferReportService'), 'API reuses canonical report services');
check(controller.includes('temporarySignedRoute') && controller.includes('report_download_ttl_minutes'), 'PDF links are short-lived and signed');
check(controller.includes("where('user_id', $validated['owner_id'])") && controller.includes("where('status', 'active')"), 'download revalidates active store ownership');
check(controller.includes('OWNER_REQUIRED') && controller.includes('diffInDays($to) > 366'), 'financial reports are owner-only and bounded');
check(auth.includes("'reports:read'"), 'device sessions receive report ability');
check(context.includes("'report_signed_downloads' => true") && context.includes("'report_offline_cache' => false"), 'feature flags advertise secure online-only reports');

passed.forEach((message) => console.log(`PASS: ${message}`));
failed.forEach((message) => console.error(`FAIL: ${message}`));
if (failed.length) process.exit(1);
console.log(`Mobile report readiness passed (${passed.length} checks).`);
