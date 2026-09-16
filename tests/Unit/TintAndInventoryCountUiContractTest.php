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

    public function test_inventory_session_product_selection_is_limited_to_the_current_page(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/inventory-counts/owner/create.blade.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/InventoryCountController.php');

        // يحمي العقد من إعادة ربط الزر بالإجراء القديم الذي كان يحدد كامل نتائج المتجر.
        self::assertStringContainsString('value="select_page">تحديد جميع منتجات هذه الصفحة', $view);
        self::assertStringContainsString("Rule::in(['page', 'select_page'])", $controller);
        self::assertStringContainsString("->whereIn('id', \$pageIds)", $controller);
        self::assertStringNotContainsString("selection_action'] ?? 'page') === 'all'", $controller);
    }

    public function test_inventory_session_products_are_ordered_by_oldest_audit_after_never_audited_products(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/inventory-counts/owner/create.blade.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/InventoryCountController.php');

        // يجب أن يضيف الاستعلام تاريخ الجرد المحسوب من دون إسقاط أعمدة المنتج اللازمة للبطاقات.
        self::assertStringContainsString('التي لم تُجرد من قبل أولًا، ثم المنتجات المجرودة من تاريخ الجرد الأقدم إلى الأحدث', $view);
        self::assertStringContainsString("->select('products.*')", $controller);
        self::assertStringContainsString("MAX(COALESCE(business_date, DATE(created_at)))", $controller);
        self::assertStringContainsString("->orderByRaw('last_audit_date IS NOT NULL')", $controller);
        self::assertStringContainsString("->orderBy('last_audit_date')", $controller);
    }
}
