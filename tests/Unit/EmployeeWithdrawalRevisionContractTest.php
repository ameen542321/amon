<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmployeeWithdrawalRevisionContractTest extends TestCase
{
    public function test_accountant_gets_one_revision_during_the_open_accounting_day(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Store/EmployeeFinanceController.php');
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/accountant.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/accountants/pos/withdrawals.blade.php');
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_02_000001_add_accountant_revision_tracking_to_employee_withdrawals.php');

        self::assertStringContainsString("unsignedTinyInteger('accountant_revision_count')->default(0)", $migration);
        self::assertStringContainsString('assertAccountantCanReviseWithdrawal', $controller);
        self::assertStringContainsString('$withdrawalBusinessDate !== $currentBusinessDate', $controller);
        self::assertStringContainsString('! is_null($withdrawal->daily_balance_id)', $controller);
        self::assertStringContainsString('(int) $withdrawal->accountant_revision_count >= 1', $controller);
        self::assertStringContainsString("'accountant_revision_count' => 1", $controller);
        self::assertStringContainsString("->name('withdrawal.update')", $routes);
        self::assertStringContainsString("->name('withdrawal.destroy')", $routes);
        self::assertStringContainsString('يمكن تعديل السحب أو حذفه مرة واحدة فقط', $view);
        self::assertStringContainsString('data-ui-confirm-title="تأكيد حذف السحب"', $view);
    }

    public function test_owner_can_update_or_delete_withdrawals_without_the_accountant_limit(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/EmployeeActionsController.php');
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/user.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/employee/withdrawal-form.blade.php');

        self::assertStringContainsString('public function updateWithdrawal', $controller);
        self::assertStringContainsString('public function destroyWithdrawal', $controller);
        self::assertStringContainsString("'withdrawal_owner_updated'", $controller);
        self::assertStringContainsString("'withdrawal_owner_deleted'", $controller);
        self::assertStringNotContainsString('accountant_revision_count', $controller);
        self::assertStringContainsString("->name('withdrawal.update')", $routes);
        self::assertStringContainsString("->name('withdrawal.destroy')", $routes);
        self::assertStringContainsString('المالك غير مقيد بعدد مرات التعديل', $view);
        self::assertStringContainsString("route('user.employees.withdrawal.update'", $view);
        self::assertStringContainsString("route('user.employees.withdrawal.destroy'", $view);
    }
}
