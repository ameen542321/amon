<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
class MobilePurchaseOrderApiContractTest extends TestCase
{
    public function test_api_is_store_scoped_and_read_only(): void
    {
        $routes=file_get_contents(base_path('routes/api.php')); $controller=file_get_contents(base_path('app/Http/Controllers/Api/PurchaseOrderController.php'));
        self::assertStringContainsString("prefix('purchase-orders')",$routes); self::assertStringContainsString('ability:purchase-orders:read',$routes);
        self::assertStringNotContainsString("Route::post('/purchase-orders",$routes); self::assertStringContainsString('ApiStoreScopeService',$controller);
        self::assertStringContainsString("where('store_id', \$store->id)",$controller); self::assertStringContainsString('cursorPaginate',$controller);
    }
    public function test_resource_uses_workflow_without_stock_snapshots(): void
    {
        $resource=file_get_contents(base_path('app/Support/Api/PurchaseOrderResource.php'));
        self::assertStringContainsString('PurchaseOrderWorkflow::consistencyIssues',$resource); self::assertStringContainsString("'approved_business_date'",$resource);
        self::assertStringNotContainsString("'stock_quantity_before'",$resource); self::assertStringNotContainsString("'stock_quantity_after'",$resource);
    }
}
