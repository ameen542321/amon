<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DailyBalanceDefaultNotesContractTest extends TestCase
{
    public function test_empty_balance_notes_fall_back_to_the_effective_reference_date(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Accountant/DashboardController.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/dashboard/accountant/index.blade.php');

        self::assertStringContainsString('balanceNotesOrReferenceDate', $controller);
        self::assertSame(2, substr_count($controller, '$this->balanceNotesOrReferenceDate('));
        self::assertStringContainsString("Carbon::parse(\$businessDate)->format('j-n')", $controller);
        self::assertStringContainsString("'notes' => \$balanceNotes", $controller);
        self::assertStringContainsString("'notes' => \$notes", $controller);
        self::assertStringContainsString('اتركه فارغًا لإضافة تاريخ اليوم المرجعي تلقائيًا، مثل 2-10', $view);
    }
}
