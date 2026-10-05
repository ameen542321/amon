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
        $laborCostMigration = file_get_contents(base_path('database/migrations/2026_10_04_000001_add_labor_cost_to_sales_and_cost_usage.php'));
        $laborBreakdownMigration = file_get_contents(base_path('database/migrations/2026_10_05_000001_add_labor_cost_breakdown_to_sales_table.php'));
        $quickSale = file_get_contents(base_path('app/Http/Controllers/Cashier/QuickSaleController.php'));

        self::assertStringContainsString('betweenAccountingDates', $service);
        self::assertStringContainsString('$item->total_cost', $service);
        self::assertStringContainsString("'sales_total'", $service);
        self::assertStringContainsString("'products_cost'", $service);
        self::assertStringContainsString("'labor_total'", $service);
        self::assertStringContainsString("'labor_cost'", $service);
        self::assertStringContainsString("'labor_cost_breakdown'", $service);
        self::assertStringContainsString("'total_cost'", $service);
        self::assertStringContainsString('calculateConfiguredLaborCost', $quickSale);
        self::assertStringContainsString("'labor_cost'       => \$laborCostCalculation['total']", $quickSale);
        self::assertStringContainsString("'labor_cost_breakdown' => \$laborCostCalculation['breakdown']", $quickSale);
        self::assertStringContainsString('insertOrIgnore', $service);
        self::assertStringContainsString("Route::get('/{store}/reports/sales-cost'", $routes);
        self::assertStringContainsString("Route::post('/{store}/reports/sales-cost/mark-used'", $routes);
        self::assertStringContainsString('استبعاد الأيام المستخدمة', $view);
        self::assertStringContainsString('تكلفة شغل اليد', $view);
        self::assertStringContainsString('إجمالي التكلفة', $view);
        self::assertStringNotContainsString('<style', $view);
        self::assertStringNotContainsString('style=', $view);
        self::assertStringContainsString("unique(['store_id', 'business_date']", $migration);
        self::assertStringContainsString("Schema::table('sales'", $laborCostMigration);
        self::assertStringContainsString("Schema::table('sales_cost_used_dates'", $laborCostMigration);
        self::assertStringContainsString('labor_cost_breakdown', $laborBreakdownMigration);
    }
}
