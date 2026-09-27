<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileApiSessionLifecycleContractTest extends TestCase
{
    public function test_refresh_tokens_are_hashed_rotated_and_device_bound(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root.'/database/migrations/2026_09_25_000003_add_refresh_lifecycle_to_api_access_tokens.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/Api/AuthTokenController.php');

        self::assertStringContainsString("char('refresh_token_hash', 64)->nullable()->unique()", $migration);
        self::assertStringContainsString("'carled_refresh_'.Str::random(80)", $controller);
        self::assertStringContainsString("hash('sha256', $plainRefreshToken)", $controller);
        self::assertStringContainsString('lockForUpdate()', $controller);
        self::assertStringContainsString("hash_equals(\$token->device_uuid, \$validated['device_uuid'])", $controller);
        self::assertStringContainsString('last_refreshed_at', $controller);
    }

    public function test_push_subscription_is_owned_by_the_authenticated_api_session(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root.'/database/migrations/2026_09_25_000004_link_device_tokens_to_api_sessions.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/Api/PushSubscriptionController.php');
        $routes = file_get_contents($root.'/routes/api.php');

        self::assertStringContainsString("unsignedBigInteger('api_access_token_id')", $migration);
        self::assertStringContainsString("where('api_access_token_id'", $controller);
        self::assertStringContainsString("where('token', \$validated['token'])->delete()", $controller);
        self::assertStringContainsString("ability:push:manage", $routes);
        self::assertStringContainsString("['ability:push:manage', 'idempotency']", $routes);
    }

    public function test_logout_and_remote_revoke_remove_push_delivery(): void
    {
        $root = dirname(__DIR__, 2);
        $auth = file_get_contents($root.'/app/Http/Controllers/Api/AuthTokenController.php');
        $context = file_get_contents($root.'/app/Http/Controllers/Api/MobileContextController.php');

        self::assertStringContainsString("DeviceToken::query()->where('api_access_token_id'", $auth);
        self::assertStringContainsString("DeviceToken::query()->whereIn('api_access_token_id'", $auth);
        self::assertStringContainsString("DeviceToken::query()->where('api_access_token_id'", $context);
        self::assertStringContainsString("'refresh_token_hash' => null", $auth);
    }
}
