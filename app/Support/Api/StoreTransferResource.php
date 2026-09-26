<?php

namespace App\Support\Api;

use App\Models\StoreTransfer;
use App\Models\StoreTransferItem;

final class StoreTransferResource
{
    public static function transfer(StoreTransfer $transfer, int $storeId, bool $details = false): array
    {
        $outgoing = (int) $transfer->sender_store_id === $storeId;
        $businessDate = $outgoing
            ? ($transfer->request_business_date ?? $transfer->created_at)
            : ($transfer->action_business_date
                ?? $transfer->request_business_date
                ?? $transfer->completed_at
                ?? $transfer->acted_at
                ?? $transfer->created_at);
        $businessDateSource = $outgoing
            ? ($transfer->request_business_date ? 'request_business_date' : 'created_at_fallback')
            : match (true) {
                $transfer->action_business_date !== null => 'action_business_date',
                $transfer->request_business_date !== null => 'request_business_date',
                $transfer->completed_at !== null => 'completed_at_fallback',
                $transfer->acted_at !== null => 'acted_at_fallback',
                default => 'created_at_fallback',
            };
        $data = [
            'id' => (int) $transfer->id, 'direction' => $outgoing ? 'outgoing' : 'incoming', 'status' => (string) $transfer->status,
            'counterparty_store' => [
                'id' => (int) ($outgoing ? $transfer->receiver_store_id : $transfer->sender_store_id),
                'name' => $outgoing ? $transfer->receiverStore?->name : $transfer->senderStore?->name,
            ],
            'items_count' => (int) ($transfer->items_count ?? $transfer->items->count()),
            'business_date' => $businessDate?->toDateString(),
            'business_date_source' => $businessDateSource,
            'request_business_date' => $transfer->request_business_date?->toDateString(),
            'action_business_date' => $transfer->action_business_date?->toDateString(),
            'created_at' => $transfer->created_at?->toIso8601String(), 'updated_at' => $transfer->updated_at?->toIso8601String(),
        ];
        if ($details) {
            $data += [
                'sender_store' => ['id' => (int) $transfer->sender_store_id, 'name' => $transfer->senderStore?->name],
                'receiver_store' => ['id' => (int) $transfer->receiver_store_id, 'name' => $transfer->receiverStore?->name],
                'notes' => $transfer->notes, 'rejection_reason' => $transfer->rejection_reason,
                'completed_at' => $transfer->completed_at?->toIso8601String(),
                'rejected_at' => $transfer->rejected_at?->toIso8601String(),
                'cancelled_at' => $transfer->cancelled_at?->toIso8601String(),
                'items' => $transfer->items->map(fn (StoreTransferItem $item): array => self::item($item))->values(),
            ];
        }
        return $data;
    }

    private static function item(StoreTransferItem $item): array
    {
        return [
            'id' => (int) $item->id, 'sender_product_id' => (int) $item->sender_product_id,
            'receiver_product_id' => $item->receiver_product_id ? (int) $item->receiver_product_id : null,
            'product_name' => $item->product_name_snapshot ?? $item->senderProduct?->name,
            'receiver_product_name' => $item->receiverProduct?->name, 'unit_type' => (string) $item->unit_type,
            'unit_label' => $item->unit_label_snapshot, 'product_type' => $item->product_type_snapshot,
            'requested_quantity' => self::decimal($item->requested_quantity, 3),
            'normalized_quantity' => self::decimal($item->normalized_quantity, 3),
            'cost_price' => self::decimal($item->cost_price),
            'total_cost' => self::decimal((float) $item->normalized_quantity * (float) $item->cost_price),
        ];
    }

    private static function decimal(mixed $value, int $scale = 2): string
    {
        return number_format((float) $value, $scale, '.', '');
    }
}
