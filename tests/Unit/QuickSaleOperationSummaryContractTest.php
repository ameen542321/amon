<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuickSaleOperationSummaryContractTest extends TestCase
{
    public function test_quick_sale_uses_one_summary_without_changing_financial_expressions(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/cashier/quick-sale/index.blade.php');

        self::assertSame(1, substr_count($view, 'data-quick-sale-operation-summary'));
        self::assertSame(1, substr_count($view, '>ملخص العملية</h3>'));
        self::assertStringNotContainsString('ملخص العملية قبل التأكيد', $view);
        self::assertStringContainsString("Math.round(items_total) + ' ر.س'", $view);
        self::assertStringContainsString("Math.round(labor_total || 0) + ' ر.س'", $view);
        self::assertStringContainsString("Math.round(final_total) + ' ر.س'", $view);
        self::assertStringContainsString('Math.round(Math.max(0, remaining))', $view);
    }

    public function test_tax_summary_and_invoice_option_require_a_store_tax_number(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/cashier/quick-sale/index.blade.php');
        $summaryStart = strpos($view, 'data-quick-sale-operation-summary');
        $summaryEnd = strpos($view, 'x-show="pendingWarningVisible', $summaryStart);
        $summary = substr($view, $summaryStart, $summaryEnd - $summaryStart);

        self::assertStringContainsString('@if($hasApprovedTaxNumber ?? false)', $summary);
        self::assertStringContainsString('<span>الضريبة</span>', $summary);
        self::assertStringContainsString('إصدار فاتورة ضريبية للمطبوعات', $summary);
    }
}
