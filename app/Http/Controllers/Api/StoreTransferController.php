<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreTransfer;
use App\Services\ApiStoreScopeService;
use App\Support\Api\ApiResponse;
use App\Support\Api\StoreTransferResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreTransferController extends Controller
{
    public function __construct(private readonly ApiStoreScopeService $stores) {}

    public function index(Request $request): JsonResponse
    {
        [$store, $validated] = $this->storeAndFilters($request);
        $query = $this->visibleQuery((int) $store->id)->with(['senderStore:id,name', 'receiverStore:id,name'])->withCount('items');
        $this->applyFilters($query, (int) $store->id, $validated);
        $transfers = $query->orderByDesc('id')->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($transfers->items())->map(fn (StoreTransfer $transfer): array => StoreTransferResource::transfer($transfer, (int) $store->id))->values(),
            ['next_cursor' => $transfers->nextCursor()?->encode(), 'per_page' => $transfers->perPage()]
        );
    }

    public function show(Request $request, int $transfer): JsonResponse
    {
        $store = $this->resolveStore($request);
        $record = $this->visibleQuery((int) $store->id)->with([
            'senderStore:id,name', 'receiverStore:id,name',
            'items.senderProduct:id,name,product_type', 'items.receiverProduct:id,name,product_type',
        ])->findOrFail($transfer);

        return ApiResponse::success(StoreTransferResource::transfer($record, (int) $store->id, true));
    }

    public function summary(Request $request): JsonResponse
    {
        [$store, $validated] = $this->storeAndFilters($request, false);
        $query = $this->visibleQuery((int) $store->id);
        $this->applyFilters($query, (int) $store->id, $validated);

        return ApiResponse::success([
            'store_id' => (int) $store->id, 'total' => (clone $query)->count(),
            'outgoing' => (clone $query)->where('sender_store_id', $store->id)->count(),
            'incoming' => (clone $query)->where('receiver_store_id', $store->id)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
        ]);
    }

    private function visibleQuery(int $storeId): Builder
    {
        return StoreTransfer::query()->where(fn (Builder $query) => $query
            ->where('sender_store_id', $storeId)->orWhere('receiver_store_id', $storeId));
    }

    private function storeAndFilters(Request $request, bool $paginate = true): array
    {
        $rules = [
            'store_id' => ['required', 'integer', 'min:1'], 'direction' => ['nullable', 'in:incoming,outgoing'],
            'status' => ['nullable', 'in:pending,completed,rejected,cancelled'], 'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
        if ($paginate) {
            $rules += ['limit' => ['nullable', 'integer', 'min:1', 'max:100'], 'cursor' => ['nullable', 'string']];
        }
        $validated = $request->validate($rules);
        return [$this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']), $validated];
    }

    private function resolveStore(Request $request)
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);
        return $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
    }

    private function applyFilters(Builder $query, int $storeId, array $filters): void
    {
        if (($filters['direction'] ?? null) === 'outgoing') {
            $query->where('sender_store_id', $storeId);
        } elseif (($filters['direction'] ?? null) === 'incoming') {
            $query->where('receiver_store_id', $storeId);
        }
        $query->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status));
        if (! empty($filters['from']) || ! empty($filters['to'])) {
            $query->where(function (Builder $date) use ($storeId, $filters): void {
                $date->where(function (Builder $outgoing) use ($storeId, $filters): void {
                    $outgoing->where('sender_store_id', $storeId);
                    $this->applyDateBounds($outgoing, 'COALESCE(request_business_date, DATE(created_at))', $filters);
                })->orWhere(function (Builder $incoming) use ($storeId, $filters): void {
                    $incoming->where('receiver_store_id', $storeId);
                    $this->applyDateBounds(
                        $incoming,
                        'COALESCE(action_business_date, request_business_date, DATE(completed_at), DATE(acted_at), DATE(created_at))',
                        $filters
                    );
                });
            });
        }
    }

    private function applyDateBounds(Builder $query, string $dateExpression, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->whereRaw("{$dateExpression} >= ?", [$filters['from']]);
        }
        if (! empty($filters['to'])) {
            $query->whereRaw("{$dateExpression} <= ?", [$filters['to']]);
        }
    }
}
