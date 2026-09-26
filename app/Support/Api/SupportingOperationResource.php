<?php

namespace App\Support\Api;

use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;

final class SupportingOperationResource
{
    public static function expense(Expense $expense): array
    {
        return [
            'id' => (int) $expense->id,
            'store_id' => (int) $expense->store_id,
            'type' => (string) $expense->type,
            'description' => $expense->description,
            'amount' => self::money($expense->amount),
            'actor_type' => $expense->actor_type,
            'business_date' => $expense->business_date?->toDateString() ?? $expense->created_at?->toDateString(),
            'created_at' => $expense->created_at?->toIso8601String(),
            'updated_at' => $expense->updated_at?->toIso8601String(),
        ];
    }

    public static function internalUse(Sale $sale, bool $details = false): array
    {
        $data = [
            'id' => (int) $sale->id,
            'store_id' => (int) $sale->store_id,
            'accountant_id' => $sale->accountant_id !== null ? (int) $sale->accountant_id : null,
            'accountant_name' => $sale->accountant?->name,
            'total' => self::money($sale->final_total ?? $sale->total ?? 0),
            'items_count' => (int) ($sale->items_count ?? $sale->items->count()),
            'business_date' => $sale->business_date?->toDateString() ?? $sale->created_at?->toDateString(),
            'created_at' => $sale->created_at?->toIso8601String(),
            'updated_at' => $sale->updated_at?->toIso8601String(),
        ];

        if ($details) {
            $data['items'] = $sale->items->map(fn (SaleItem $item): array => [
                'id' => (int) $item->id,
                'product_id' => $item->product_id !== null ? (int) $item->product_id : null,
                'product_name' => $item->historical_product_name,
                'quantity' => self::decimal($item->quantity_snapshot ?? $item->quantity, 4),
                'unit_type' => $item->unit_type,
                'unit_label' => $item->historical_unit_label,
                'total' => self::money($item->total),
            ])->values();
        }

        return $data;
    }

    public static function ownerPurchase(Purchase $purchase): array
    {
        return [
            'id' => (int) $purchase->id,
            'store_id' => (int) $purchase->store_id,
            'product_id' => $purchase->product_id !== null ? (int) $purchase->product_id : null,
            'name' => (string) ($purchase->product_name_snapshot ?: $purchase->purchase_name ?: 'بند غير معروف'),
            'quantity' => self::decimal($purchase->quantity, 4),
            'cost' => self::money($purchase->cost),
            'description' => $purchase->description,
            'business_date' => $purchase->business_date?->toDateString() ?? $purchase->created_at?->toDateString(),
            'created_at' => $purchase->created_at?->toIso8601String(),
            'updated_at' => $purchase->updated_at?->toIso8601String(),
        ];
    }

    private static function money(mixed $value): string
    {
        return self::decimal($value, 2);
    }

    private static function decimal(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
