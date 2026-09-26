import { existsSync, readFileSync } from 'node:fs';

const failures = [];
const passes = [];
const source = (path) => existsSync(path) ? readFileSync(path, 'utf8') : '';
const check = (condition, message) => (condition ? passes : failures).push(message);

const required = [
    'database/migrations/2026_09_25_000003_add_refresh_lifecycle_to_api_access_tokens.php',
    'database/migrations/2026_09_25_000004_link_device_tokens_to_api_sessions.php',
    'app/Http/Controllers/Api/PushSubscriptionController.php',
    'tests/Unit/MobileApiSessionLifecycleContractTest.php',
];
for (const file of required) check(existsSync(file), `required file exists: ${file}`);

const auth = source('app/Http/Controllers/Api/AuthTokenController.php');
const token = source('app/Models/ApiAccessToken.php');
const routes = source('routes/api.php');
const push = source('app/Http/Controllers/Api/PushSubscriptionController.php');
const cleanup = source('app/Console/Commands/CleanupApiAccessTokens.php');
const provider = source('app/Providers/AppServiceProvider.php');
const client = source('resources/js/features/pwa/api-client.js');
const sqliteSchema = source('database/testing/sqlite-schema.sql');

check(auth.includes("'carled_refresh_'.Str::random(80)") && auth.includes("hash('sha256', $plainRefreshToken)"), 'refresh secrets are generated strongly and stored only as hashes');
check(auth.includes('lockForUpdate()') && auth.includes('last_refreshed_at'), 'refresh rotation is serialized and recorded');
check(auth.includes("hash_equals($token->device_uuid, $validated['device_uuid'])"), 'refresh token is bound to its installation identifier');
check(token.includes('canRefresh()') && token.includes('refresh_expires_at?->isFuture()'), 'refresh eligibility checks revocation and expiry');
check(routes.includes("/auth/refresh") && routes.includes('throttle:mobile-refresh'), 'refresh route has a dedicated throttle');
check(provider.includes("RateLimiter::for('mobile-refresh'"), 'refresh rate limiter is registered');
check(routes.includes('/push-subscription') && routes.includes('ability:push:manage'), 'push registration is authenticated and ability-scoped');
check(push.includes("where('api_access_token_id'") && push.includes("where('token', $validated['token'])->delete()"), 'push subscription is session-owned and globally reassigned safely');
check(auth.includes("DeviceToken::query()->whereIn('api_access_token_id'") && auth.includes("'refresh_token_hash' => null"), 'logout-all revokes refresh and push delivery together');
check(cleanup.includes("where('refresh_expires_at', '<=', now())"), 'cleanup preserves refreshable sessions until refresh expiry');
check(client.includes("headers.set('Authorization', `Bearer ${accessToken}`)"), 'shared API client supports Bearer authorization');
check(sqliteSchema.includes('CREATE TABLE "api_access_tokens"') && sqliteSchema.includes('"refresh_token_hash" varchar(64)'), 'in-memory test schema includes the complete token lifecycle');
check(sqliteSchema.includes('CREATE TABLE "api_idempotency_keys"') && sqliteSchema.includes('device_tokens_api_access_token_id_index'), 'in-memory test schema includes idempotency and push-session ownership');

for (const message of passes) console.log(`PASS: ${message}`);
for (const message of failures) console.error(`FAIL: ${message}`);
if (failures.length) {
    console.error(`Mobile session lifecycle failed with ${failures.length} issue(s).`);
    process.exit(1);
}
console.log(`Mobile session lifecycle passed (${passes.length} checks).`);
