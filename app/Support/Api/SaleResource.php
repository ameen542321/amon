<?php
namespace App\Support\Api;

use App\Models\Sale;
use App\Models\SaleItem;

final class SaleResource
{
    private static function money(mixed $value): string { return number_format((float) $value, 2, '.', ''); }

    public static function sale(Sale $sale, bool $details = false): array
    {
        $data = [
            'id' => (int) $sale->id, 'store_id' => (int) $sale->store_id,
            'accountant_id' => (int) $sale->accountant_id, 'employee_id' => $sale->employee_id ? (int) $sale->employee_id : null,
            'sale_type' => $sale->sale_type, 'business_date' => $sale->business_date?->toDateString(),
            'products_total' => self::money($sale->products_total), 'labor_total' => self::money($sale->labor_total),
            'final_total' => self::money($sale->final_total ?: $sale->total), 'paid_amount' => self::money($sale->paid_amount),
            'cash_amount' => self::money($sale->cash_amount), 'card_amount' => self::money($sale->card_amount),
            'remaining_amount' => self::money($sale->remaining_amount), 'tax_rate' => (int) $sale->tax_rate,
            'has_invoice' => (bool) $sale->has_invoice, 'created_at' => $sale->created_at?->toIso8601String(),
        ];
        if ($details) {
            $data['description'] = $sale->description;
            $data['items'] = $sale->items->map(fn (SaleItem $item): array => self::item($item))->values();
            $data['invoice'] = $sale->invoice ? [
                'id' => (int) $sale->invoice->id, 'invoice_number' => $sale->invoice->invoice_number,
                'status' => $sale->invoice->status, 'customer_name' => $sale->invoice->customer_name,
                'total_amount' => self::money($sale->invoice->total_amount),
            ] : null;
        }
        return $data;
    }

    private static function item(SaleItem $item): array
    {
        return [
            'id' => (int) $item->id, 'product_id' => $item->product_id ? (int) $item->product_id : null,
            'name' => $item->historical_product_name, 'unit' => $item->historical_unit_label,
            'quantity' => number_format((float) ($item->quantity_snapshot ?? $item->quantity), 4, '.', ''),
            'unit_price' => self::money($item->sale_price_snapshot ?? $item->price), 'total' => self::money($item->total),
            'custom' => (bool) $item->is_custom,
        ];
    }
}
