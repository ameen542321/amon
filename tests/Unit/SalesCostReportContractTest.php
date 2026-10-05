<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SalesCostReportContractTest extends TestCase
{
    public function test_sales_cost_report_keeps_the_approved_simple_contract(): void
    {
        $service = file_get_contents(base_path('app/Services/Reports/SalesCostReportService.php'));
        $routes = file_get_contents(base_path('routes/user.php'));
        $view = file_get_contents(base_path('resources/views/user/stores/reports/sales-cost.blade.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_09_27_000001_create_sales_cost_used_dates_table.php'));

        self::assertStringContainsString('betweenAccountingDates', $service);
        self::assertStringContainsString('$item->total_cost', $service);
        self::assertStringContainsString("'sales_total'", $service);
        self::assertStringContainsString("'products_cost'", $service);
        self::assertStringContainsString("'labor_total'", $service);
        self::assertStringContainsString("'labor_cost'", $service);
        self::assertStringContainsString("'total_cost'", $service);
        self::assertStringContainsString("'missing_labor_cost_count'", $service);
        self::assertStringContainsString('insertOrIgnore', $service);
        self::assertStringContainsString("Route::get('/{store}/reports/sales-cost'", $routes);
        self::assertStringContainsString("Route::post('/{store}/reports/sales-cost/mark-used'", $routes);
        self::assertStringContainsString('استبعاد الأيام المستخدمة', $view);
        self::assertStringContainsString('تكلفة خيارات العمل', $view);
        self::assertStringContainsString('إجمالي التكلفة', $view);
        self::assertStringContainsString('غير متوفرة لعملية قديمة', $view);
        self::assertStringNotContainsString('<style', $view);
        self::assertStringNotContainsString('style=', $view);
        self::assertStringContainsString("unique(['store_id', 'business_date']", $migration);
    }
}
