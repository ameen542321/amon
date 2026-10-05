<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmployeeWithdrawalDateInputContractTest extends TestCase
{
    public function test_owner_withdrawal_uses_a_direct_native_date_input(): void
    {
        $form = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/employee/withdrawal-form.blade.php');
        $actions = file_get_contents(dirname(__DIR__, 2).'/resources/views/employees/actions.blade.php');

        self::assertSame(1, substr_count($form, 'type="date"'));
        self::assertStringContainsString('name="date"', $form);
        self::assertStringContainsString("old('date', now()->toDateString())", $form);
        self::assertStringNotContainsString('data-ui-sync-value=', $form);
        self::assertStringNotContainsString('opacity-0', $form);
        self::assertStringContainsString("['employee' => \$employee]", $actions);
    }
}
