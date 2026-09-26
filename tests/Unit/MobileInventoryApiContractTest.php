<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileInventoryApiContractTest extends TestCase
{
    public function test_inventory_api_is_read_only_store_scoped_and_auditable(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/api.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/Api/InventoryController.php');
        $resource = file_get_contents($root.'/app/Support/Api/InventoryResource.php');

        self::assertStringContainsString("prefix('inventory')", $routes);
        self::assertStringContainsString('ability:inventory:read', $routes);
        self::assertStringNotContainsString("Route::post('/inventory", $routes);
        self::assertStringContainsString("attributes->get('api_actor')", $controller);
        self::assertStringContainsString('latest_balance_mismatches', $controller);
        self::assertStringContainsString('movements_missing_balances', $controller);
        self::assertStringNotContainsString('cost_price_snapshot', $resource);
        self::assertStringContainsString("'balance_before'", $resource);
        self::assertStringContainsString("'balance_after'", $resource);
    }
}
