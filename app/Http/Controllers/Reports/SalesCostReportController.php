<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Reports\SalesCostReportService;
use App\Services\Stores\StoreAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
