<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TintAndInventoryCountUiContractTest extends TestCase
{
    public function test_tint_summary_exposes_edit_and_delete_actions(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/cashier/quick-sale/partials/tint-modal.blade.php');
        $script = file_get_contents(dirname(__DIR__, 2).'/resources/js/features/cashier/tint-sale-interface.js');

        self::assertStringContainsString('@click="editResolvedPart(part)"', $view);
        self::assertStringContainsString('@click="removeResolvedPart(part)"', $view);
        self::assertStringContainsString('editResolvedPart(part)', $script);
        self::assertStringContainsString('removeResolvedPart(part)', $script);
    }

    public function test_owner_inventory_session_list_exposes_the_protected_delete_route(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/inventory-counts/owner/index.blade.php');
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/user.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/InventoryCountController.php');

        self::assertStringContainsString("route('user.stores.inventory-counts.destroy'", $view);
        self::assertStringContainsString("Route::delete('/{inventoryCount}'", $routes);
        self::assertStringContainsString("in_array(\$inventoryCount->status, ['draft', 'cancelled'], true)", $controller);
        self::assertStringContainsString('$this->ownerStore($store)', $controller);
    }
}
