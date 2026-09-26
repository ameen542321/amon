<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Modules\PurchaseOrders\Support\PurchaseOrderWorkflow;
use App\Services\ApiStoreScopeService;
use App\Support\Api\ApiResponse;
use App\Support\Api\PurchaseOrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly ApiStoreScopeService $stores) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(PurchaseOrderWorkflow::labels()))],
            'supplier' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $query = StorePurchaseOrder::query()->where('store_id', $store->id)->withCount('items');

        $query->when($validated['status'] ?? null, fn ($q, $status) => $q->where('workflow_status', $status));
        $query->when($validated['supplier'] ?? null, fn ($q, $supplier) => $q->where('supplier_name', 'like', '%'.addcslashes($supplier, '%_\\').'%'));
        $query->when($validated['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from));
        $query->when($validated['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        $orders = $query->orderByDesc('id')->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($orders->items())->map(fn (StorePurchaseOrder $order): array => PurchaseOrderResource::order($order))->values(),
            ['next_cursor' => $orders->nextCursor()?->encode(), 'per_page' => $orders->perPage()]
        );
    }

    public function show(Request $request, int $order): JsonResponse
    {
        $store = $this->resolveStore($request);
        $record = StorePurchaseOrder::query()
            ->with([
                'store:id,name', 'accountant:id,name', 'user:id,name',
                'items.product:id,name,product_type', 'items.matchedProduct:id,name,product_type',
                'events' => fn ($query) => $query->orderBy('id'),
            ])
            ->where('store_id', $store->id)
            ->findOrFail($order);

        return ApiResponse::success(PurchaseOrderResource::order($record, true));
    }

    public function summary(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        $counts = StorePurchaseOrder::query()->where('store_id', $store->id)
            ->selectRaw('workflow_status, COUNT(*) as aggregate')
            ->groupBy('workflow_status')->pluck('aggregate', 'workflow_status');

        return ApiResponse::success([
            'store_id' => (int) $store->id,
            'total' => (int) $counts->sum(),
            'by_status' => collect(PurchaseOrderWorkflow::labels())->map(fn (string $label, string $status): array => [
                'status' => $status, 'label' => $label, 'count' => (int) ($counts[$status] ?? 0),
            ])->values(),
        ]);
    }

    private function resolveStore(Request $request)
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);

        return $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
    }
}
