<?php
namespace App\Support\Api;

use App\Models\StockMovement;

final class InventoryResource
{
    public static function movement(StockMovement $movement): array
    {
        $decimal = static fn (mixed $value): ?string => $value === null ? null : number_format((float) $value, 6, '.', '');
        return [
            'id' => (int) $movement->id,
            'store_id' => (int) $movement->store_id,
            'product_id' => $movement->product_id ? (int) $movement->product_id : null,
            'product_name' => $movement->product_name_snapshot ?: $movement->product?->name,
            'type' => (string) $movement->type,
            'operation' => $movement->operation_label,
            'quantity' => $decimal($movement->quantity),
            'requested_quantity' => $decimal($movement->requested_quantity),
            'unit' => $movement->snapshotUnitLabel($movement->product),
            'balance_before' => $decimal($movement->balance_before),
            'balance_after' => $decimal($movement->balance_after),
            'business_date' => $movement->business_date?->toDateString(),
            'note' => $movement->note,
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }
}
