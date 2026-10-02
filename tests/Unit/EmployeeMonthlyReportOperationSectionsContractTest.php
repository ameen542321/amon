<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmployeeMonthlyReportOperationSectionsContractTest extends TestCase
{
    public function test_report_exposes_four_month_scoped_operation_sections(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/StoreController.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/user/stores/reports/partials/employee-operation-sections.blade.php');

        foreach (['withdrawal_rows', 'absence_rows', 'credit_sale_rows', 'credit_collection_rows', 'debt_rows', 'debt_collection_rows'] as $rowsKey) {
            self::assertStringContainsString("['{$rowsKey}']", $controller.$view);
        }

        self::assertStringContainsString('betweenAccountingDates($start, $end)', $controller);
        self::assertStringContainsString("whereBetween('employee_credit_collections.collection_date'", $controller);
        self::assertSame(4, substr_count($view, '<button type="button"'));
        self::assertStringContainsString('سحوبات الشهر المحدد', $view);
        self::assertStringContainsString('غيابات الشهر المحدد', $view);
        self::assertStringContainsString('الأجل وتحصيلاته', $view);
        self::assertStringContainsString('المديونية وتحصيلاتها', $view);
    }
}
