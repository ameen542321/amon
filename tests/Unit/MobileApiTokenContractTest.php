<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileApiTokenContractTest extends TestCase
{
    public function test_tokens_are_hashed_expiring_revocable_and_device_scoped(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root.'/database/migrations/2026_09_25_000002_create_api_access_tokens_table.php');
        $auth = file_get_contents($root.'/app/Http/Controllers/Api/AuthTokenController.php');
        $middleware = file_get_contents($root.'/app/Http/Middleware/AuthenticateApiToken.php');
        $notificationController = file_get_contents($root.'/app/Http/Controllers/Api/NotificationController.php');
        $idempotency = file_get_contents($root.'/app/Http/Middleware/EnsureIdempotentRequest.php');

        self::assertStringContainsString("char('token_hash', 64)->unique()", $migration);
        self::assertStringNotContainsString("string('token'", $migration);
        self::assertStringContainsString("'carled_'.Str::random(64)", $auth);
        self::assertStringContainsString("hash('sha256', $plainToken)", $auth);
        self::assertStringContainsString('Hash::check', $auth);
        self::assertStringContainsString("version_compare(\$validated['app_version']", $auth);
        self::assertStringContainsString('max_devices_per_account', $auth);
        self::assertStringContainsString('bearerToken()', $middleware);
        self::assertStringContainsString("'revoked_at' => now()", $middleware);
        self::assertStringContainsString('UPDATE_REQUIRED', $middleware);
        self::assertStringContainsString("attributes->get('api_actor')", $notificationController);
        self::assertStringContainsString("attributes->get('api_actor')", $idempotency);
    }

    public function test_api_account_state_and_token_abilities_are_enforced(): void
    {
        $root = dirname(__DIR__, 2);
        $access = file_get_contents($root.'/app/Services/ApiAccountAccessService.php');
        $abilities = file_get_contents($root.'/app/Http/Middleware/RequireApiAbility.php');
        $routes = file_get_contents($root.'/routes/api.php');
        $provider = file_get_contents($root.'/app/Providers/AppServiceProvider.php');

        foreach (['ACCOUNT_ORPHANED', 'ACCOUNT_INACTIVE', 'SUBSCRIPTION_EXPIRED', 'STORE_INACTIVE'] as $code) {
            self::assertStringContainsString($code, $access);
        }
        self::assertStringContainsString('TOKEN_ABILITY_DENIED', $abilities);
        self::assertStringContainsString("middleware('throttle:mobile-login')", $routes);
        self::assertStringContainsString("middleware(['auth.api-token', 'throttle:mobile-api'])", $routes);
        self::assertStringContainsString("middleware(['ability:notifications:write', 'idempotency'])", $routes);
        self::assertStringContainsString("RateLimiter::for('mobile-login'", $provider);
        self::assertStringContainsString("RateLimiter::for('mobile-api'", $provider);
    }

    public function test_mobile_api_exposes_only_the_first_read_focused_scope(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/api.php');
        $context = file_get_contents($root.'/app/Http/Controllers/Api/MobileContextController.php');

        foreach (['/auth/login', '/auth/logout', '/auth/logout-all', '/me', '/app-config', '/stores', '/devices', 'notifications'] as $path) {
            self::assertStringContainsString($path, $routes);
        }
        self::assertStringContainsString("'offline_sensitive_mutations' => false", $context);
        self::assertStringContainsString("'token_authentication' => true", $context);
        self::assertStringContainsString("'inventory_outbox' => false", $context);
        self::assertStringContainsString("'store_transfer_outbox' => false", $context);
        self::assertStringNotContainsString('invoices', $routes);
        self::assertStringNotContainsString('quick-sale', $routes);
    }
}
