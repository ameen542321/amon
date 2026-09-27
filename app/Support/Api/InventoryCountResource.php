<?php

namespace App\Support\Api;

use App\Models\InventoryCountSession;
use App\Models\InventoryCountSessionItem;

final class InventoryCountResource
{
    public static function session(InventoryCountSession $session, bool $details = false, bool $includeSystemSnapshot = false): array
    {
        $data = [
            'id' => (int) $session->id,
            'reference' => $session->referenceCode(),
            'store_id' => (int) $session->store_id,
            'store_name' => $session->store?->name,
            'accountant' => $session->accountant ? ['id' => (int) $session->accountant->id, 'name' => $session->accountant->name] : null,
            'status' => (string) $session->status,
            'status_label' => $session->statusLabel(),
            'items_count' => (int) ($session->items_count ?? $session->items->count()),
            'note' => $session->note,
            'version' => $session->updated_at?->toIso8601String(),
            'created_at' => $session->created_at?->toIso8601String(),
            'updated_at' => $session->updated_at?->toIso8601String(),
        ];

        if ($details) {
            $data['items'] = $session->items
                ->map(fn (InventoryCountSessionItem $item): array => self::item($item, $includeSystemSnapshot))
                ->values();
        }

        return $data;
    }

    private static function item(InventoryCountSessionItem $item, bool $includeSystemSnapshot): array
    {
        $data = [
            'id' => (int) $item->id,
            'product_id' => (int) $item->product_id,
            'product_name' => $item->product_name_snapshot ?? $item->product?->name,
            'product_description' => $item->product_description_snapshot,
            'product_type' => $item->product?->product_type,
            'is_splittable' => (bool) $item->product?->is_splittable,
            'items_per_unit' => $item->product?->items_per_unit ? (int) $item->product->items_per_unit : null,
            'roll_length' => $item->product?->roll_length !== null ? self::decimal($item->product->roll_length, 4) : null,
            'unit_type' => (string) $item->unit_type,
            'accountant_quantity' => $item->accountant_quantity !== null ? self::decimal($item->accountant_quantity, 4) : null,
            'accountant_note' => $item->accountant_note,
            'count_business_date' => $item->count_business_date?->toDateString(),
            'decision' => (string) $item->decision,
            'attempt' => (int) $item->attempt,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
        if ($includeSystemSnapshot) {
            $data['system_quantity_snapshot'] = $item->system_quantity_snapshot !== null
                ? self::decimal($item->system_quantity_snapshot, 4)
                : null;
            $data['system_snapshot_at'] = $item->system_snapshot_at?->toIso8601String();
            $data['owner_quantity'] = $item->owner_quantity !== null ? self::decimal($item->owner_quantity, 4) : null;
            $data['owner_adjustment_reason'] = $item->owner_adjustment_reason;
        }

        return $data;
    }

    private static function decimal(mixed $value, int $scale): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
