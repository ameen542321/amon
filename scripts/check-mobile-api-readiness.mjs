import { existsSync, readFileSync } from 'node:fs';

const failures = [];
const passes = [];
const source = (path) => existsSync(path) ? readFileSync(path, 'utf8') : '';
const pass = (message) => passes.push(message);
const fail = (message) => failures.push(message);

const required = [
    'app/Models/ApiAccessToken.php',
    'app/Services/ApiAccountAccessService.php',
    'app/Http/Middleware/AuthenticateApiToken.php',
    'app/Http/Middleware/RequireApiAbility.php',
    'app/Http/Controllers/Api/AuthTokenController.php',
    'app/Http/Controllers/Api/MobileContextController.php',
    'app/Console/Commands/CleanupApiAccessTokens.php',
    'database/migrations/2026_09_25_000002_create_api_access_tokens_table.php',
    'config/mobile_api.php',
    'routes/api.php',
];
for (const file of required) existsSync(file) ? pass(`required file exists: ${file}`) : fail(`missing ${file}`);

const migration = source(required[7]);
if (migration.includes("char('token_hash', 64)->unique()") && !migration.includes("string('token'")) {
    pass('database stores only a unique token hash');
} else fail('token hash storage contract is unsafe');

const auth = source(required[4]);
if (auth.includes("'carled_'.Str::random(64)") && auth.includes("hash('sha256', $plainToken)")) {
    pass('plain token is generated once and persisted only as SHA-256');
} else fail('token generation/hash contract is incomplete');
if (auth.includes('Hash::check') && auth.includes('INVALID_CREDENTIALS')) pass('login verifies hashed credentials generically');
else fail('login credential verification contract is missing');
if (auth.includes('max_devices_per_account') && auth.includes("whereNull('revoked_at')")) pass('active device sessions are bounded');
else fail('device session limit is missing');

const tokenMiddleware = source(required[2]);
if (tokenMiddleware.includes("bearerToken()") && tokenMiddleware.includes("hash('sha256', $plainToken)")) {
    pass('Bearer authentication resolves tokens by hash');
} else fail('Bearer token lookup is unsafe');
if (tokenMiddleware.includes('denialCode($actor)') && tokenMiddleware.includes("'revoked_at' => now()")) {
    pass('invalid account state revokes the device token');
} else fail('account state is not enforced for API tokens');
if (auth.includes("version_compare($validated['app_version']") && tokenMiddleware.includes('UPDATE_REQUIRED')) {
    pass('minimum app version is enforced at login and on token use');
} else fail('minimum app version enforcement is missing');
const notifications = source('app/Http/Controllers/Api/NotificationController.php');
const idempotency = source('app/Http/Middleware/EnsureIdempotentRequest.php');
if (notifications.includes("attributes->get('api_actor')") && idempotency.includes("attributes->get('api_actor')")) {
    pass('Bearer actor takes precedence over any unrelated browser session cookie');
} else fail('Bearer actor precedence is not enforced');

const abilities = source(required[3]);
const routes = source('routes/api.php');
if (abilities.includes('TOKEN_ABILITY_DENIED') && routes.includes('ability:notifications:write')) {
    pass('token abilities protect mutation routes');
} else fail('token ability enforcement is incomplete');
if (routes.includes("middleware('throttle:mobile-login')") && routes.includes("middleware(['auth.api-token', 'throttle:mobile-api'])")) {
    pass('login and authenticated APIs are rate limited');
} else fail('mobile API rate limits are missing');
const provider = source('app/Providers/AppServiceProvider.php');
if (provider.includes("RateLimiter::for('mobile-login'") && provider.includes("RateLimiter::for('mobile-api'")) {
    pass('mobile rate limits are keyed by login identity and Bearer token');
} else fail('mobile named rate limiters are missing');
if (routes.includes("middleware(['ability:notifications:write', 'idempotency'])")) {
    pass('mobile notification mutations require idempotency');
} else fail('mobile mutation idempotency is missing');

const access = source(required[1]);
for (const state of ['ACCOUNT_ORPHANED', 'ACCOUNT_INACTIVE', 'SUBSCRIPTION_EXPIRED', 'STORE_INACTIVE']) {
    access.includes(state) ? pass(`account access rejects ${state}`) : fail(`account access misses ${state}`);
}

const schedule = source('routes/console.php');
if (schedule.includes("Schedule::command('api-tokens:cleanup')") && schedule.includes("dailyAt('03:00')")) {
    pass('expired token cleanup is scheduled');
} else fail('token cleanup schedule is missing');

for (const message of passes) console.log(`PASS: ${message}`);
for (const message of failures) console.error(`FAIL: ${message}`);
if (failures.length) {
    console.error(`Mobile API readiness failed with ${failures.length} issue(s).`);
    process.exit(1);
}
console.log(`Mobile API readiness passed (${passes.length} checks).`);
