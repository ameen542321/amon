<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmployeeEditArabicValidationContractTest extends TestCase
{
    public function test_employee_edit_uses_arabic_validation_and_requires_custom_salary_date(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Employees/EmployeeService.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/employees/edit.blade.php');
        $interface = file_get_contents(dirname(__DIR__, 2).'/resources/js/features/employees/edit-interface.js');
        $app = file_get_contents(dirname(__DIR__, 2).'/resources/js/app.js');

        self::assertStringContainsString("'salary_effective_date.required_if' => 'يجب تحديد تاريخ سريان الراتب عند اختيار تاريخ مخصص.'", $service);
        self::assertStringContainsString("'accountant_password.confirmed' => 'تأكيد كلمة المرور الجديدة غير مطابق.'", $service);
        self::assertStringContainsString("'transfer_effective_date.date' => 'تاريخ نقل الموظف غير صحيح.'", $service);
        self::assertStringContainsString('data-employee-edit-form', $view);
        self::assertStringContainsString('data-salary-effective-date', $view);
        self::assertStringContainsString("old('salary_effective_mode'", $view);
        self::assertStringContainsString("toggleAttribute('required', customMode)", $interface);
        self::assertStringContainsString('يجب تحديد تاريخ سريان الراتب عند اختيار تاريخ مخصص.', $interface);
        self::assertStringContainsString('يجب إدخال اسم الموظف.', $interface);
        self::assertStringContainsString('يجب إدخال بريد إلكتروني صحيح للمحاسب.', $interface);
        self::assertStringContainsString('راجع قيمة هذا الحقل ثم أعد المحاولة.', $interface);
        self::assertStringContainsString("import './features/employees/edit-interface'", $app);
    }
}
