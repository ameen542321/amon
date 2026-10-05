<?php

namespace App\Services\Reports;

use App\Models\Sale;
use App\Models\SalesCostUsedDate;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesCostReportService
{
    private const INCLUDED_SALE_TYPES = ['cash', 'card', 'credit', 'mixed'];

    /**
     * يبني تقريرًا بسيطًا من عمليات البيع نفسها دون تعديل الأرباح أو المخزون.
     */
    public function build(Store $store, array $filters): array
    {
        $from = Carbon::parse($filters['from'] ?? now()->startOfMonth())->toDateString();
        $to = Carbon::parse($filters['to'] ?? now())->toDateString();
        $search = trim((string) ($filters['q'] ?? ''));
        $excludeUsed = (bool) ($filters['exclude_used'] ?? false);

        $sales = Sale::query()
            ->where('store_id', $store->id)
            ->whereIn('sale_type', self::INCLUDED_SALE_TYPES)
            ->excludeManualInvoiceEntries()
            ->betweenAccountingDates($from, $to)
            ->with(['accountant:id,name', 'items.product:id,name,description,barcode'])
            ->orderByRaw('COALESCE(business_date, DATE(created_at)) DESC')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Sale $sale): bool => $this->matches($sale, $search))
            ->values();

        $usedDates = SalesCostUsedDate::query()
            ->where('store_id', $store->id)
            ->whereBetween('business_date', [$from, $to])
            ->orderBy('business_date')
            ->get()
            ->keyBy(fn (SalesCostUsedDate $usage): string => $usage->business_date->toDateString());

        $matchedDates = $sales->map(fn (Sale $sale): string => $this->businessDate($sale))->unique()->sort()->values();
        $overlappingUsedDates = $matchedDates->filter(fn (string $date): bool => $usedDates->has($date))->values();

        if ($excludeUsed) {
            $sales = $sales->reject(fn (Sale $sale): bool => $usedDates->has($this->businessDate($sale)))->values();
        }

        $rows = $sales->map(fn (Sale $sale): array => $this->row($sale));
        $summary = [
            'sales_total' => round((float) $rows->sum('sales_total'), 2),
            'products_cost' => round((float) $rows->sum('products_cost'), 2),
            'labor_total' => round((float) $rows->sum('labor_total'), 2),
            'labor_cost' => round((float) $rows->sum('labor_cost'), 2),
            'total_cost' => round((float) $rows->sum(fn (array $row): float => $row['products_cost'] + $row['labor_cost']), 2),
            'operations_count' => $rows->count(),
        ];

        return compact(
            'store',
            'from',
            'to',
            'search',
            'excludeUsed',
            'rows',
            'summary',
            'usedDates',
            'matchedDates',
            'overlappingUsedDates',
        );
    }

    /**
     * يعلم أيام النتائج غير المستخدمة فقط؛ إعادة نفس الطلب لا تنشئ سجلات مكررة.
     */
    public function markResultDatesUsed(Store $store, array $filters, int $userId): int
    {
        $report = $this->build($store, array_merge($filters, ['exclude_used' => false]));
        $rowsByDate = $report['rows']->groupBy('business_date');

        return DB::transaction(function () use ($store, $filters, $userId, $rowsByDate): int {
            $created = 0;

            foreach ($rowsByDate as $businessDate => $rows) {
                $inserted = SalesCostUsedDate::query()->insertOrIgnore([
                    'store_id' => $store->id,
                    'user_id' => $userId,
                    'business_date' => $businessDate,
                    'search_term' => trim((string) ($filters['q'] ?? '')) ?: null,
                    'sales_total' => round((float) $rows->sum('sales_total'), 2),
                    'products_cost' => round((float) $rows->sum('products_cost'), 2),
                    'labor_total' => round((float) $rows->sum('labor_total'), 2),
                    'labor_cost' => round((float) $rows->sum('labor_cost'), 2),
                    'operations_count' => $rows->count(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created += (int) $inserted;
            }

            return $created;
        });
    }

    private function row(Sale $sale): array
    {
        $items = $sale->items;
        $allItemsHaveSavedCost = $items->isNotEmpty()
            && $items->every(fn ($item): bool => $item->total_cost !== null);

        // total_cost هو المصدر التاريخي الأدق؛ fallback يحافظ على قابلية قراءة العمليات القديمة.
        $productsCost = $allItemsHaveSavedCost
            ? (float) $items->sum('total_cost')
            : max(0, (float) ($sale->products_total ?? 0) + (float) ($sale->labor_total ?? 0) - (float) ($sale->profit ?? 0));

        return [
            'id' => (int) $sale->id,
            'business_date' => $this->businessDate($sale),
            'description' => $sale->description ?: $sale->internal_notes ?: 'عملية بيع',
            'products' => $items->map(fn ($item): string => $item->historical_product_name)->filter()->implode('، '),
            'sales_total' => (float) ($sale->final_total ?? $sale->total ?? 0),
            'products_cost' => round($productsCost, 2),
            'labor_total' => (float) ($sale->labor_total ?? 0),
            'labor_cost' => (float) ($sale->labor_cost ?? 0),
            'labor_cost_breakdown' => collect($sale->labor_cost_breakdown ?? [])->map(fn (array $entry): array => [
                'label' => (string) ($entry['label'] ?? ''),
                'cost' => round((float) ($entry['cost'] ?? 0), 2),
            ])->filter(fn (array $entry): bool => $entry['label'] !== '')->values()->all(),
            'accountant' => $sale->accountant?->name ?: 'غير محدد',
            'cost_source' => $allItemsHaveSavedCost ? 'محفوظة وقت البيع' : 'تقديرية لعملية قديمة',
        ];
    }

    private function businessDate(Sale $sale): string
    {
        return ($sale->business_date ?? $sale->created_at)->toDateString();
    }

    private function matches(Sale $sale, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystack = collect([
            $sale->description,
            $sale->internal_notes,
            $sale->accountant?->name,
            ...$sale->items->flatMap(fn ($item): array => [
                $item->historical_product_name,
                $item->custom_name,
                $item->product?->description,
                $item->product?->barcode,
            ])->all(),
        ])->filter()->implode(' ');

        return str_contains($this->normalize($haystack), $this->normalize($search));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['أ', 'إ', 'آ', 'ى', 'ة', 'ؤ', 'ئ'], ['ا', 'ا', 'ا', 'ي', 'ه', 'و', 'ي'], $value);
        $value = preg_replace('/(.)\1+/u', '$1', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
