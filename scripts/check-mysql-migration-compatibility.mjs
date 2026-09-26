import { readFileSync } from 'node:fs';

const files = [
    'database/migrations/2026_09_25_000001_create_api_idempotency_keys_table.php',
    'database/migrations/2026_09_25_000002_create_api_access_tokens_table.php',
    'database/migrations/2026_09_25_000003_add_refresh_lifecycle_to_api_access_tokens.php',
    'database/migrations/2026_09_25_000004_link_device_tokens_to_api_sessions.php',
];
const failures = [];
const applicationManagedDates = [
    'expires_at',
    'refresh_expires_at',
    'last_used_at',
    'last_refreshed_at',
    'revoked_at',
    'last_seen_at',
];

for (const file of files) {
    const source = readFileSync(file, 'utf8');
    for (const column of applicationManagedDates) {
        if (source.includes(`timestamp('${column}')`)) {
            failures.push(`${file} uses TIMESTAMP for ${column}`);
        }
    }
}

const accessTokens = readFileSync(files[1], 'utf8');
if (!accessTokens.includes("dateTime('expires_at')->index()")) {
    failures.push('api access token expiry is not a required indexed DATETIME');
}
const idempotency = readFileSync(files[0], 'utf8');
if (!idempotency.includes("dateTime('expires_at')->index()")) {
    failures.push('idempotency expiry is not a required indexed DATETIME');
}

if (failures.length) {
    failures.forEach((failure) => console.error(`FAIL: ${failure}`));
    process.exit(1);
}

console.log('MySQL migration compatibility passed: application-managed API dates use DATETIME.');
