<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Sale;
use App\Models\SalesCostUsedDate;
use App\Services\Reports\SalesCostReportService;
use App\Services\Stores\StoreAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesCostReportController extends Controller
{
    public function index(Request $request, Store $store, StoreAccessService $access, SalesCostReportService $reports): View
    {
        $access->ensureOwnerCanAccess($request->user(), $store);
        $filters = $this->validatedFilters($request);

        return view('user.stores.reports.sales-cost', $reports->build($store, $filters));
    }

    public function markUsed(Request $request, Store $store, StoreAccessService $access, SalesCostReportService $reports): RedirectResponse
    {
        $access->ensureOwnerCanAccess($request->user(), $store);
        $filters = $this->validatedFilters($request);
        $created = $reports->markResultDatesUsed($store, $filters, (int) $request->user()->id);

        return redirect()
            ->route('user.stores.reports.sales-cost', ['store' => $store->id] + $filters)
            ->with($created > 0 ? 'success' : 'info', $created > 0
                ? "تم تحديد {$created} يوم من نتائج التقرير كمستخدم."
                : 'كل أيام نتائج التقرير محددة كمستخدمة مسبقًا.');
    }

    public function updateLaborCost(Request $request, Store $store, Sale $sale, StoreAccessService $access): RedirectResponse
    {
        $access->ensureOwnerCanAccess($request->user(), $store);
        abort_unless((int) $sale->store_id === (int) $store->id, 404);
        abort_unless($sale->canEditLaborCost(), 422, 'يمكن تعديل تكلفة شغل اليد فقط عندما تكون صفرًا أو غير مسجلة.');

        $validated = $request->validate([
            'labor_cost' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'exclude_used' => ['nullable', 'boolean'],
        ]);

        $this->persistLaborCosts($store, [$sale->id => $validated['labor_cost']], (int) $request->user()->id);

        return $this->savedResponse($store, $validated, (int) $sale->id, 'تم حفظ تكلفة شغل اليد.');
    }

    public function updateSelectedLaborCosts(Request $request, Store $store, StoreAccessService $access): RedirectResponse
    {
        $access->ensureOwnerCanAccess($request->user(), $store);
        $filters = $this->validatedFilters($request);
        $validated = $request->validate([
            'costs' => ['required', 'array', 'min:1', 'max:100'],
            'costs.*' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'return_sale' => ['nullable', 'integer', 'min:1'],
        ]);
        foreach (array_keys($validated['costs']) as $id) {
            if (!ctype_digit((string) $id) || (int) $id < 1) {
                throw ValidationException::withMessages(['costs' => 'اختر عمليات صالحة للحفظ.']);
            }
        }
        $this->persistLaborCosts($store, $validated['costs'], (int) $request->user()->id);
        $ids = array_keys($validated['costs']);
        $returnSale = (int) ($validated['return_sale'] ?? end($ids));
        if (!array_key_exists($returnSale, $validated['costs'])) {
            $returnSale = (int) end($ids);
        }

        return $this->savedResponse($store, $filters, $returnSale, 'تم حفظ تكلفة شغل اليد للعمليات المحددة.');
    }

    private function persistLaborCosts(Store $store, array $costs, int $userId): void
    {
        DB::transaction(function () use ($store, $costs, $userId): void {
            $sales = Sale::query()->where('store_id', $store->id)
                ->whereIn('id', array_keys($costs))->orderBy('id')->lockForUpdate()->get();
            abort_unless($sales->count() === count($costs), 404);
            $usedDates = SalesCostUsedDate::query()->where('store_id', $store->id)
                ->whereIn('business_date', $sales->map(fn (Sale $sale): string =>
                    ($sale->business_date ?? $sale->created_at)->toDateString()
                ))->get()->map(fn (SalesCostUsedDate $usage): string => $usage->business_date->toDateString());

            foreach ($sales as $sale) {
                $date = ($sale->business_date ?? $sale->created_at)->toDateString();
                if (!$sale->canEditLaborCost() || $usedDates->contains($date)) {
                    throw ValidationException::withMessages([
                        'costs.'.$sale->id => 'لا يمكن تعديل تكلفة العملية #'.$sale->id.'؛ راجع قيمتها وحالة اليوم.',
                    ]);
                }
            }

            foreach ($sales as $sale) {
                $previousCost = $sale->labor_cost;
                $newCost = round((float) $costs[$sale->id], 2);
                $sale->update([
                    'labor_cost' => $newCost,
                    'labor_cost_breakdown' => [[
                        'label' => 'تعديل يدوي من تقرير التكلفة', 'cost' => $newCost,
                        'children' => [], 'total_cost' => $newCost,
                    ]],
                ]);
                Log::info('تم تحديث تكلفة خيارات العمل من تقرير التكلفة', [
                    'store_id' => $store->id, 'sale_id' => $sale->id, 'user_id' => $userId,
                    'previous_labor_cost' => $previousCost, 'new_labor_cost' => $newCost,
                ]);
            }
        });
    }

    private function savedResponse(Store $store, array $validated, int $saleId, string $message): RedirectResponse
    {
        $filters = collect($validated)->only(['from', 'to', 'q', 'exclude_used'])
            ->filter(fn ($value): bool => $value !== null && $value !== '')->all();
        $url = route('user.stores.reports.sales-cost', ['store' => $store->id] + $filters);

        return redirect($url.'#sale-'.$saleId)->with('success', $message);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'exclude_used' => ['nullable', 'boolean'],
        ]);
    }
}
