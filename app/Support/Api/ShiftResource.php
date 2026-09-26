<?php
namespace App\Support\Api;

use App\Models\DailyBalance;

final class ShiftResource
{
    public static function balance(DailyBalance $balance): array
    {
        $money = static fn (mixed $value): string => number_format((float) $value, 2, '.', '');
        return [
            'id' => (int) $balance->id,
            'store_id' => (int) $balance->store_id,
            'accountant_id' => $balance->accountant_id ? (int) $balance->accountant_id : null,
            'business_date' => $balance->business_date?->toDateString(),
            'system_sales_total' => $money($balance->system_sales_total),
            'system_cash_expected' => $money($balance->system_cash_expected),
            'actual_cash_submitted' => $money($balance->actual_cash_submitted),
            'difference' => $money($balance->difference),
            'start_time' => $balance->start_time?->toIso8601String(),
            'end_time' => $balance->end_time?->toIso8601String(),
            'closed_at' => $balance->closed_at?->toIso8601String(),
            'next_shift_business_date' => $balance->next_shift_business_date?->toDateString(),
            'next_shift_decision' => $balance->next_shift_decision,
        ];
    }
}
