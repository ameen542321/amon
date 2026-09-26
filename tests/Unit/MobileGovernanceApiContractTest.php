<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileGovernanceApiContractTest extends TestCase
{
    public function test_governance_api_is_owner_scoped_and_read_only(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/GovernanceController.php'));

        self::assertStringContainsString("prefix('governance')", $routes);
        self::assertStringContainsString('ability:governance:read', $routes);
        self::assertStringNotContainsString("Route::post('/governance", $routes);
        self::assertStringNotContainsString("Route::patch('/governance", $routes);
        self::assertStringNotContainsString("Route::delete('/governance", $routes);
        self::assertStringContainsString('$actor instanceof Accountant', $controller);
        self::assertStringContainsString("where('user_id', \$owner->id)", $controller);
        self::assertStringContainsString("where('actor_type', 'user')", $controller);
    }

    public function test_governance_payloads_omit_secrets_and_sensitive_audit_details(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/GovernanceController.php'));
        $context = file_get_contents(base_path('app/Http/Controllers/Api/MobileContextController.php'));

        self::assertStringNotContainsString('token_hash', $controller);
        self::assertStringNotContainsString('refresh_token_hash', $controller);
        self::assertStringNotContainsString('last_ip', $controller);
        self::assertStringNotContainsString('last_user_agent', $controller);
        self::assertStringNotContainsString("'details' =>", $controller);
        self::assertStringNotContainsString("'description' =>", $controller);
        self::assertStringContainsString("'subscription_write' => false", $context);
        self::assertStringContainsString("'account_administration_write' => false", $context);
        self::assertStringContainsString("'security_settings_write' => false", $context);
    }
}
