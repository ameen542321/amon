<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileInventoryCountApiContractTest extends TestCase
{
    public function test_routes_are_scoped_and_only_staged_save_is_writable(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/InventoryCountController.php'));

        self::assertStringContainsString("prefix('inventory-counts')", $routes);
        self::assertStringContainsString('ability:inventory-counts:read', $routes);
        self::assertStringContainsString("['ability:inventory-counts:write', 'idempotency']", $routes);
        self::assertStringNotContainsString("post('/{session}/submit'", $routes);
        self::assertStringNotContainsString("post('/{session}/approve'", $routes);
        self::assertStringContainsString("where('accountant_id', \$actor->getAuthIdentifier())", $controller);
        self::assertStringContainsString("where('owner_id', \$actor->getAuthIdentifier())", $controller);
        self::assertStringContainsString("whereIn('decision', ['returned', 'recounted'])", $controller);
        self::assertStringContainsString("where('decision', 'pending')", $controller);
    }

    public function test_draft_save_detects_conflicts_and_hides_system_snapshot_from_accountants(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/InventoryCountController.php'));
        $service = file_get_contents(base_path('app/Services/InventoryCountService.php'));
        $resource = file_get_contents(base_path('app/Support/Api/InventoryCountResource.php'));

        self::assertStringContainsString("'session_version' => ['required', 'date']", $controller);
        self::assertStringContainsString('lockForUpdate()', $service);
        self::assertStringContainsString('expectedVersion', $service);
        self::assertStringContainsString('includeSystemSnapshot', $resource);
        self::assertStringContainsString('! $actor instanceof Accountant', $controller);
        self::assertStringContainsString('$lockedSession->touch()', $service);
    }
}
