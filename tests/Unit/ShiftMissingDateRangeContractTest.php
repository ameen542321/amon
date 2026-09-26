<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ShiftMissingDateRangeContractTest extends TestCase
{
    public function test_missing_shift_scope_covers_current_month_and_last_ten_days_of_previous_month(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/ShiftLifecycleService.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/user/stores/shift-gaps.blade.php');

        self::assertStringContainsString('PREVIOUS_MONTH_MISSING_DAYS = 10', $service);
        self::assertStringContainsString("->startOfMonth()->subDay()->startOfDay()", $service);
        self::assertStringContainsString('subDays(self::PREVIOUS_MONTH_MISSING_DAYS - 1)', $service);
        self::assertStringContainsString("->subDay()->startOfDay()", $service);
        self::assertStringNotContainsString('MISSING_DAYS_LOOKBACK = 15', $service);
        self::assertStringContainsString('أيام الشهر الحالي المكتملة غير المغلقة', $view);
        self::assertStringContainsString('آخر عشرة أيام من الشهر السابق', $view);
        self::assertStringNotContainsString('آخر 15 يومًا', $view);
    }
}
