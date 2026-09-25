import { existsSync, readFileSync } from 'node:fs';

const failures = [];
const warnings = [];
const checks = [];

const pass = (message) => checks.push(message);
const fail = (message) => failures.push(message);
const warn = (message) => warnings.push(message);
const source = (path) => existsSync(path) ? readFileSync(path, 'utf8') : '';

const requiredFiles = [
    'app/Jobs/SendOneSignalNotification.php',
    'app/Services/OneSignalService.php',
    'app/Services/NotificationMaintenanceService.php',
    'app/Console/Commands/CleanupNotifications.php',
    'app/Support/Notifications/NotificationRecipient.php',
    'app/Support/Notifications/NotificationPayload.php',
    'app/Http/Controllers/Api/NotificationController.php',
    'routes/console.php',
    'routes/user.php',
    'routes/accountant.php',
    'phpunit.xml',
];

for (const file of requiredFiles) {
    if (existsSync(file)) pass(`required file exists: ${file}`);
    else fail(`missing required file: ${file}`);
}

const job = source('app/Jobs/SendOneSignalNotification.php');
if (job.includes('implements ShouldQueue')) pass('OneSignal delivery uses a queued job');
else fail('OneSignal delivery job must implement ShouldQueue');
if (job.includes('public int $tries = 3;') && job.includes('public int $timeout = 30;')) {
    pass('queued delivery has bounded tries and timeout');
} else fail('queued delivery limits changed or are missing');
if (job.includes('public function backoff(): array')) pass('queued delivery defines backoff');
else fail('queued delivery backoff is missing');

const oneSignal = source('app/Services/OneSignalService.php');
if (oneSignal.includes('connectTimeout(5)') && oneSignal.includes('->timeout(10)')) {
    pass('OneSignal HTTP connection and response timeouts are bounded');
} else fail('OneSignal HTTP timeouts changed or are missing');
if (oneSignal.includes('->retry(2, 250, throw: false)')) pass('OneSignal HTTP retry is bounded');
else fail('OneSignal HTTP retry contract changed or is missing');
if (!oneSignal.includes("'api_key' =>") && !oneSignal.includes("'Authorization' => $config->api_key")) {
    pass('OneSignal API key is not written to structured logs');
} else fail('OneSignal API key may be exposed to logs');

const schedule = source('routes/console.php');
if (schedule.includes("Schedule::command('notifications:cleanup')")
    && schedule.includes("dailyAt('02:30')")
    && schedule.includes('withoutOverlapping()')) {
    pass('notification cleanup is scheduled without overlap');
} else fail('notification cleanup schedule contract changed');

const cleanup = source('app/Console/Commands/CleanupNotifications.php');
if (cleanup.includes('{--dry-run') && cleanup.includes('{--chunk=500')) {
    pass('cleanup supports preview and bounded chunk input');
} else fail('cleanup dry-run or chunk option is missing');

const notificationModel = source('app/Models/Notification.php');
if (!notificationModel.includes('static::retrieved') && !notificationModel.includes('rand(')) {
    pass('notification reads have no random cleanup side effects');
} else fail('notification reads contain cleanup side effects');
if (notificationModel.includes('scopeVisibleTo') && notificationModel.includes('hiddenMarker()')) {
    pass('notification visibility is recipient scoped');
} else fail('recipient-scoped notification visibility is missing');

const api = source('app/Http/Controllers/Api/NotificationController.php');
if (api.includes('->visibleTo($recipient)') && api.includes('private, no-store')) {
    pass('notification API scopes records and disables shared caching');
} else fail('notification API scoping or cache protection changed');

for (const routeFile of ['routes/user.php', 'routes/accountant.php']) {
    const routes = source(routeFile);
    if (routes.includes("prefix('api/v1/notifications')") && routes.includes("middleware('throttle:120,1')")) {
        pass(`notification API is throttled in ${routeFile}`);
    } else fail(`notification API throttle or prefix is missing in ${routeFile}`);
}

const phpunit = source('phpunit.xml');
if (phpunit.includes('name="DB_CONNECTION" value="sqlite" force="true"')
    && phpunit.includes('name="DB_DATABASE" value=":memory:" force="true"')) {
    pass('PHPUnit is forced to use in-memory SQLite');
} else fail('PHPUnit may connect to a non-test database');

const pwaTextAssets = [
    'public/manifest.webmanifest',
    'public/sw.js',
    'public/offline.html',
    'public/carled.svg',
    'resources/js/features/pwa/register-service-worker.js',
];
if (pwaTextAssets.every(existsSync)) pass('text-only PWA foundation is present');
else fail('one or more text-only PWA foundation files are missing');
if (!existsSync('public/icons/carled-192.png') && !existsSync('public/icons/carled-512.png')) {
    pass('legacy binary PWA icons remain absent');
} else fail('binary PWA icons must be replaced by the SVG asset');

if (process.argv.includes('--staging-env')) {
    const appEnv = process.env.APP_ENV;
    const queue = process.env.QUEUE_CONNECTION;

    if (appEnv === 'staging') pass('APP_ENV is staging');
    else fail('APP_ENV must be staging for the staging gate');

    if (queue && !['sync', 'null'].includes(queue)) pass(`QUEUE_CONNECTION is asynchronous: ${queue}`);
    else fail('QUEUE_CONNECTION must be an asynchronous driver, not sync/null');

    if (!process.env.ONESIGNAL_APP_ID && !process.env.ONESIGNAL_CONFIG_IN_DATABASE) {
        warn('OneSignal configuration was not declared to this process; verify it from the protected admin settings page');
    }
}

for (const message of checks) console.log(`PASS: ${message}`);
for (const message of warnings) console.warn(`WARN: ${message}`);
for (const message of failures) console.error(`FAIL: ${message}`);

if (failures.length > 0) {
    console.error(`Notification readiness failed with ${failures.length} issue(s).`);
    process.exit(1);
}

console.log(`Notification readiness passed (${checks.length} checks, ${warnings.length} warnings).`);
