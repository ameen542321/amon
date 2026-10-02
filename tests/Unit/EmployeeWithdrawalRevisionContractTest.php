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
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_10_02_000002_track_accountant_withdrawal_actions_per_employee.php');

        self::assertStringContainsString("Schema::create('employee_withdrawal_accountant_actions'", $migration);
        self::assertStringContainsString("['store_id', 'person_id', 'person_type', 'business_date', 'action']", $migration);
        self::assertStringContainsString('assertAccountantCanReviseWithdrawal', $controller);
        self::assertStringContainsString('consumeAccountantWithdrawalAction', $controller);
        self::assertStringContainsString('$withdrawalBusinessDate !== $currentBusinessDate', $controller);
        self::assertStringContainsString('! is_null($withdrawal->daily_balance_id)', $controller);
        self::assertStringContainsString("'action' => \$action", $controller);
        self::assertStringContainsString("\$action === 'update' ? 'التعديل' : 'الحذف'", $controller);
        self::assertStringContainsString("contains('action', 'update')", $controller);
        self::assertStringContainsString("contains('action', 'delete')", $controller);
        self::assertStringContainsString("->name('withdrawal.update')", $routes);
        self::assertStringContainsString("->name('withdrawal.destroy')", $routes);
        self::assertStringContainsString('فرصة تعديل واحدة وفرصة حذف واحدة، وهما مستقلتان', $view);
        self::assertStringContainsString('يمكن استخدام الفرص نفسها لموظف آخر', $view);
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
