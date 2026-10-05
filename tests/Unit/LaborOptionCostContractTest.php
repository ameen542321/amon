<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LaborOptionCostContractTest extends TestCase
{
    public function test_labor_option_cost_is_calculated_on_the_server_and_not_exposed_to_cashier(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Cashier/QuickSaleController.php'));
        $view = file_get_contents(base_path('resources/views/cashier/quick-sale/index.blade.php'));
        $sale = file_get_contents(base_path('app/Models/Sale.php'));
        $migration = file_get_contents(base_path('database/migrations/2026_10_05_000001_add_labor_cost_to_sales_and_cost_usage_tables.php'));
        $styles = file_get_contents(base_path('resources/css/app.css'));

        self::assertStringContainsString('calculateLaborCost', $controller);
        self::assertStringContainsString("'labor_selections' => 'nullable|json'", $controller);
        self::assertStringContainsString("'label' => \$group['label']", $controller);
        self::assertStringNotContainsString("'cost' => \$group['cost']", $controller);
        self::assertStringContainsString('name="labor_selections"', $view);
        self::assertStringContainsString("'labor_cost'", $sale);
        self::assertStringContainsString("'labor_cost_breakdown'", $sale);
        self::assertStringContainsString("Schema::table('sales'", $migration);
        self::assertStringContainsString("addColumnIfMissing('sales', 'labor_cost'", $migration);
        self::assertStringContainsString("addColumnIfMissing('sales', 'labor_cost_breakdown'", $migration);
        self::assertStringContainsString("addColumnIfMissing('sales_cost_used_dates', 'labor_cost'", $migration);
        self::assertStringContainsString('addColumnIfMissing', $migration);
        self::assertStringContainsString('information_schema.columns', $migration);
        self::assertStringContainsString("\$sqlState === '42S21'", $migration);
        self::assertStringContainsString("str_contains(strtolower($exception->getMessage()), 'duplicate column')", $migration);
        self::assertStringContainsString("->nullable()->after('labor_total')", $migration);
        self::assertStringContainsString('html.dark .ui-input:is([type="date"]', $styles);
        self::assertStringContainsString('color-scheme: dark', $styles);
    }
}
