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

        $businessDate = ($sale->business_date ?? $sale->created_at)->toDateString();
        $dateWasUsed = SalesCostUsedDate::query()
            ->where('store_id', $store->id)
            ->whereDate('business_date', $businessDate)
            ->exists();

        if ($dateWasUsed) {
            return back()->with('error', 'لا يمكن تعديل تكلفة عملية من يوم تم تحديده كمستخدم.');
        }

        $previousCost = $sale->labor_cost;
        $newCost = round((float) $validated['labor_cost'], 2);
        $sale->update([
            'labor_cost' => $newCost,
            'labor_cost_breakdown' => [[
                'label' => 'تعديل يدوي من تقرير التكلفة',
                'cost' => $newCost,
                'children' => [],
                'total_cost' => $newCost,
            ]],
        ]);

        Log::info('تم تحديث تكلفة خيارات العمل لعملية سابقة من تقرير التكلفة', [
            'store_id' => $store->id,
            'sale_id' => $sale->id,
            'user_id' => $request->user()->id,
            'previous_labor_cost' => $previousCost,
            'new_labor_cost' => $newCost,
        ]);

        $filters = collect($validated)->only(['from', 'to', 'q', 'exclude_used'])->filter(
            fn ($value): bool => $value !== null && $value !== ''
        )->all();

        return redirect()
            ->route('user.stores.reports.sales-cost', ['store' => $store->id] + $filters)
            ->with('success', 'تم حفظ تكلفة العمل للعملية السابقة.');
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
