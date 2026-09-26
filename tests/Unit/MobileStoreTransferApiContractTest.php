<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
class MobileStoreTransferApiContractTest extends TestCase
{
    public function test_api_is_participant_scoped_and_read_only(): void
    {
        $routes=file_get_contents(base_path('routes/api.php')); $controller=file_get_contents(base_path('app/Http/Controllers/Api/StoreTransferController.php'));
        self::assertStringContainsString("prefix('store-transfers')",$routes); self::assertStringContainsString('ability:store-transfers:read',$routes);
        self::assertStringNotContainsString("Route::post('/store-transfers",$routes); self::assertStringContainsString('ApiStoreScopeService',$controller);
        self::assertStringContainsString("where('sender_store_id', \$storeId)",$controller); self::assertStringContainsString("orWhere('receiver_store_id', \$storeId)",$controller);
    }
    public function test_dates_follow_accounting_direction_contract(): void
    {
        $controller=file_get_contents(base_path('app/Http/Controllers/Api/StoreTransferController.php')); $resource=file_get_contents(base_path('app/Support/Api/StoreTransferResource.php'));
        self::assertStringContainsString("'request_business_date'",$controller); self::assertStringContainsString("'action_business_date'",$controller);
        self::assertStringContainsString('action_business_date ?? $transfer->request_business_date',$resource);
        self::assertStringNotContainsString("'sender_stock_before'",$resource); self::assertStringNotContainsString("'receiver_stock_after'",$resource);
    }
}
