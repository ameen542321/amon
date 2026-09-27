<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileSupportingOperationApiContractTest extends TestCase
{
    public function test_supporting_operations_are_store_scoped_read_only_and_bounded(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/SupportingOperationController.php'));

        self::assertStringContainsString("prefix('operations')", $routes);
        self::assertStringContainsString('ability:operations:read', $routes);
        self::assertStringNotContainsString("Route::post('/expenses", $routes);
        self::assertStringNotContainsString("Route::patch('/expenses", $routes);
        self::assertStringNotContainsString("Route::delete('/expenses", $routes);
        self::assertStringContainsString('ApiStoreScopeService', $controller);
        self::assertStringContainsString("where('store_id', \$storeId)", $controller);
        self::assertStringContainsString('diffInDays($end) > 366', $controller);
        self::assertStringContainsString("'max:100'", $controller);
    }

    public function test_financial_privacy_and_accounting_date_contract_are_explicit(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/SupportingOperationController.php'));
        $resource = file_get_contents(base_path('app/Support/Api/SupportingOperationResource.php'));
        $context = file_get_contents(base_path('app/Http/Controllers/Api/MobileContextController.php'));

        self::assertStringContainsString('betweenAccountingDates($startDate, $endDate)', $controller);
        self::assertStringContainsString("whereNull('business_date')", $controller);
        self::assertStringContainsString('OWNER_REQUIRED', $controller);
        self::assertStringNotContainsString('cost_price', $resource);
        self::assertStringNotContainsString("'profit'", $resource);
        self::assertStringNotContainsString('internal_notes', $resource);
        self::assertStringContainsString("'supporting_operations_write' => false", $context);
        self::assertStringContainsString("'financial_approval_offline' => false", $context);
    }
}
