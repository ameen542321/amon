<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmployeePdfReportContractTest extends TestCase
{
    public function test_employee_pdf_keeps_the_complete_employee_history_sections(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/pdf/employee-pdf.blade.php');

        self::assertIsString($view);
        foreach ([
            'بيانات العامل',
            'المتاجر التي عمل بها خلال شهر التقرير',
            'رصيد المديونية الحالي',
            'السحوبات',
            'الغيابات',
            'المديونيات والتحصيلات',
            'البيع الآجل غير المحصل',
            'البيع الآجل المحصل والتحصيلات',
            'سجل النقل الكامل بين المتاجر',
        ] as $requiredSection) {
            self::assertStringContainsString($requiredSection, $view);
        }
    }

    public function test_transfer_history_is_not_limited_to_the_selected_report_month(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Employees/EmployeeReports.php');
        $transferQuery = strstr($controller, '$transfers = EmployeeLog::withTrashed()');

        self::assertIsString($transferQuery);
        $transferQuery = explode('$assignmentSegments', $transferQuery, 2)[0];
        self::assertStringNotContainsString("->whereBetween('created_at', [\$periodStart, \$periodEnd])", $transferQuery);
        self::assertStringContainsString("->where('action_name', 'employee_transferred')", $transferQuery);
    }
}
