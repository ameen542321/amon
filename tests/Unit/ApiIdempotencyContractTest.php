<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ApiIdempotencyContractTest extends TestCase
{
    public function test_idempotency_storage_and_middleware_contracts_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root.'/database/migrations/2026_09_25_000001_create_api_idempotency_keys_table.php');
        $middleware = file_get_contents($root.'/app/Http/Middleware/EnsureIdempotentRequest.php');
        $config = file_get_contents($root.'/config/idempotency.php');
        $environment = file_get_contents($root.'/.env.example');

        self::assertStringContainsString("Schema::create('api_idempotency_keys'", $migration);
        self::assertStringContainsString("unique(['actor_type', 'actor_id', 'key_hash']", $migration);
        self::assertStringContainsString("header('Idempotency-Key')", $middleware);
        self::assertStringContainsString('hash_equals($record->request_hash, $requestHash)', $middleware);
        self::assertStringContainsString("'X-Idempotent-Replayed', 'true'", $middleware);
        self::assertStringContainsString('$record->delete();', $middleware);
        self::assertStringContainsString("'retention_hours'", $config);
        self::assertStringContainsString("'processing_timeout_minutes'", $config);
        self::assertStringContainsString("'max_response_bytes'", $config);
        self::assertStringContainsString('IDEMPOTENCY_RETENTION_HOURS=24', $environment);
        self::assertStringContainsString('IDEMPOTENCY_PROCESSING_TIMEOUT_MINUTES=5', $environment);
    }

    public function test_only_selected_session_api_mutations_enable_idempotency(): void
    {
        $root = dirname(__DIR__, 2);
        $ownerRoutes = file_get_contents($root.'/routes/user.php');
        $accountantRoutes = file_get_contents($root.'/routes/accountant.php');
        $bootstrap = file_get_contents($root.'/bootstrap/app.php');
        $duplicateGuard = file_get_contents($root.'/app/Http/Middleware/PreventDuplicateRequest.php');

        foreach ([$ownerRoutes, $accountantRoutes] as $routes) {
            self::assertStringContainsString("Route::patch('/{notification}/read', [ApiNotificationController::class, 'markRead'])->middleware('idempotency')", $routes);
            self::assertStringContainsString("Route::delete('/{notification}', [ApiNotificationController::class, 'hide'])->middleware('idempotency')", $routes);
        }

        self::assertStringContainsString("'idempotency' => \\App\\Http\\Middleware\\EnsureIdempotentRequest::class", $bootstrap);
        self::assertStringContainsString("in_array('idempotency'", $duplicateGuard);
    }

    public function test_idempotency_cleanup_is_bounded_and_scheduled(): void
    {
        $root = dirname(__DIR__, 2);
        $command = file_get_contents($root.'/app/Console/Commands/CleanupApiIdempotencyKeys.php');
        $schedule = file_get_contents($root.'/routes/console.php');

        self::assertStringContainsString('idempotency:cleanup', $command);
        self::assertStringContainsString('--dry-run', $command);
        self::assertStringContainsString('min(5000', $command);
        self::assertStringContainsString("Schedule::command('idempotency:cleanup')", $schedule);
        self::assertStringContainsString('->hourly()', $schedule);
        self::assertStringContainsString('->withoutOverlapping()', $schedule);
    }
}
