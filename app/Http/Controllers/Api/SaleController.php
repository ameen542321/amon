<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\ApiStoreScopeService;
use App\Support\Api\ApiResponse;
use App\Support\Api\SaleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private readonly ApiStoreScopeService $stores) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required','integer','min:1'], 'business_date' => ['nullable','date'],
            'sale_type' => ['nullable','in:cash,card,credit,mixed'], 'has_invoice' => ['nullable','boolean'],
            'limit' => ['nullable','integer','min:1','max:100'], 'cursor' => ['nullable','string'],
        ]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $query = Sale::query()->where('store_id', $store->id)->excludeManualInvoiceEntries();
        if (!empty($validated['business_date'])) { $query->whereDate('business_date', $validated['business_date']); }
        if (!empty($validated['sale_type'])) { $query->where('sale_type', $validated['sale_type']); }
        if (isset($validated['has_invoice'])) { $query->where('has_invoice', (bool) $validated['has_invoice']); }
        $rows = $query->latest('id')->cursorPaginate($validated['limit'] ?? 50);
        return ApiResponse::success(collect($rows->items())->map(fn ($sale) => SaleResource::sale($sale))->values(), [
            'next_cursor' => $rows->nextCursor()?->encode(), 'per_page' => $rows->perPage(),
        ]);
    }

    public function show(Request $request, int $sale): JsonResponse
    {
        $store = $this->store($request);
        $record = Sale::query()->with(['items.product:id,name,product_type,is_splittable,items_per_unit', 'invoice'])
            ->where('store_id', $store->id)->excludeManualInvoiceEntries()->findOrFail($sale);
        return ApiResponse::success(SaleResource::sale($record, true));
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate(['store_id'=>['required','integer','min:1'], 'business_date'=>['required','date']]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $row = Sale::query()->where('store_id', $store->id)->whereDate('business_date', $validated['business_date'])
            ->excludeManualInvoiceEntries()->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(final_total),0) as total, COALESCE(SUM(cash_amount),0) as cash, COALESCE(SUM(card_amount),0) as card, COALESCE(SUM(remaining_amount),0) as remaining')->first();
        $money = fn ($v) => number_format((float) $v, 2, '.', '');
        return ApiResponse::success([
            'business_date'=>$validated['business_date'], 'sales_count'=>(int)$row->sales_count,
            'total'=>$money($row->total), 'cash'=>$money($row->cash), 'card'=>$money($row->card), 'remaining'=>$money($row->remaining),
        ]);
    }

    private function store(Request $request)
    {
        $validated=$request->validate(['store_id'=>['required','integer','min:1']]);
        return $this->stores->resolve($request->attributes->get('api_actor'), (int)$validated['store_id']);
    }
}
