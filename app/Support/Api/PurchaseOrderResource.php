<?php

namespace App\Support\Api;

use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderEvent;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;
use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;

final class PurchaseOrderResource
{
    public static function order(StorePurchaseOrder $order, bool $details = false): array
    {
        $data = [
            'id' => (int) $order->id, 'reference' => $order->referenceCode(), 'store_id' => (int) $order->store_id,
            'supplier_name' => $order->supplier_name, 'status' => (string) $order->status,
            'workflow_status' => (string) $order->workflow_status,
            'workflow_label' => PurchaseOrderWorkflow::label($order->workflow_status),
            'inventory_review_status' => $order->inventory_review_status,
            'items_count' => (int) ($order->items_count ?? $order->items->count()),
            'sent_at' => $order->sent_at?->toIso8601String(), 'received_at' => $order->received_at?->toIso8601String(),
            'approved_at' => $order->approved_at?->toIso8601String(),
            'approved_business_date' => $order->approved_business_date?->toDateString(),
            'created_at' => $order->created_at?->toIso8601String(), 'updated_at' => $order->updated_at?->toIso8601String(),
        ];
        if ($details) {
            $data += [
                'store_name' => $order->store?->name, 'created_by' => $order->accountant?->name ?? $order->user?->name,
                'notes' => $order->notes, 'rejection_reason' => $order->rejection_reason,
                'reversal_reason' => $order->reversal_reason, 'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                'rejected_at' => $order->rejected_at?->toIso8601String(), 'reversed_at' => $order->reversed_at?->toIso8601String(),
                'items' => $order->items->map(fn (StorePurchaseOrderItem $item): array => self::item($item))->values(),
                'events' => $order->events->map(fn (StorePurchaseOrderEvent $event): array => self::event($event))->values(),
                'consistency_issues' => PurchaseOrderWorkflow::consistencyIssues($order),
            ];
        }
        return $data;
    }

    private static function item(StorePurchaseOrderItem $item): array
    {
        return [
            'id' => (int) $item->id, 'product_id' => $item->product_id ? (int) $item->product_id : null,
            'matched_product_id' => $item->matched_product_id ? (int) $item->matched_product_id : null,
            'name' => $item->productName(), 'matched_product_name' => $item->matchedProduct?->name,
            'unit_type' => (string) $item->unit_type, 'items_per_unit' => $item->items_per_unit ? (int) $item->items_per_unit : null,
            'roll_length' => self::decimal($item->roll_length, 4),
            'quantity_requested' => self::decimal($item->quantity_requested, 4),
            'quantity_received' => self::decimal($item->quantity_received, 4),
            'cost_price_at_order' => self::decimal($item->cost_price_at_order),
            'cost_price_at_receipt' => self::decimal($item->cost_price_at_receipt),
            'receipt_notes' => $item->receipt_notes, 'inventory_count_required' => (bool) $item->inventory_count_required,
            'excluded_after_count' => (bool) $item->excluded_after_count, 'exclusion_reason' => $item->exclusion_reason,
        ];
    }

    private static function event(StorePurchaseOrderEvent $event): array
    {
        return ['id' => (int) $event->id, 'event' => (string) $event->event, 'from_status' => $event->from_status,
            'to_status' => $event->to_status, 'note' => $event->note, 'created_at' => $event->created_at?->toIso8601String()];
    }

    private static function decimal(mixed $value, int $scale = 2): ?string
    {
        return $value === null ? null : number_format((float) $value, $scale, '.', '');
    }
}
