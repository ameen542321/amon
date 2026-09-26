<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\InventoryCountSession;
use App\Models\Product;
use App\Services\ApiStoreScopeService;
use App\Services\InventoryCountService;
use App\Services\ShiftLifecycleService;
use App\Support\Api\ApiResponse;
use App\Support\Api\InventoryCountResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryCountController extends Controller
{
    public function __construct(
        private readonly ApiStoreScopeService $stores,
        private readonly InventoryCountService $counts,
        private readonly ShiftLifecycleService $shifts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(array_merge(InventoryCountSession::OPEN_STATUSES, ['approved', 'cancelled']))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $sessions = $this->visibleQuery($request, (int) $store->id)
            ->with(['store:id,name', 'accountant:id,name'])
            ->withCount('items')
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($sessions->items())->map(fn (InventoryCountSession $session): array => InventoryCountResource::session($session))->values(),
            ['next_cursor' => $sessions->nextCursor()?->encode(), 'per_page' => $sessions->perPage()]
        );
    }

    public function show(Request $request, int $session): JsonResponse
    {
        $store = $this->resolveStore($request);
        $actor = $request->attributes->get('api_actor');
        $record = $this->visibleQuery($request, (int) $store->id)->findOrFail($session);
        $this->loadVisibleDetails($record, $actor);

        return ApiResponse::success(InventoryCountResource::session($record, true, ! $actor instanceof Accountant));
    }

    public function saveDraft(Request $request, int $session): JsonResponse
    {
        $actor = $request->attributes->get('api_actor');
        if (! $actor instanceof Accountant) {
            return ApiResponse::error('ACCOUNTANT_REQUIRED', 'حفظ مسودة الجرد متاح للمحاسب فقط.', 403);
        }

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'min:1'],
            'session_version' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.accountant_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_type' => ['required', Rule::in(['piece', 'kit', 'meter', 'roll'])],
            'items.*.accountant_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $store = $this->stores->resolve($actor, (int) $validated['store_id']);
        $record = $this->visibleQuery($request, (int) $store->id)->findOrFail($session);
        $editableItems = $record->items()
            ->when(
                $record->status === 'returned_to_accountant',
                fn (Builder $query) => $query->whereIn('decision', ['returned', 'recounted']),
                fn (Builder $query) => $query->where('decision', 'pending')
            )
            ->with('product')
            ->whereIn('id', array_keys($validated['items']))
            ->get()
            ->keyBy('id');

        if ($editableItems->count() !== count($validated['items'])) {
            throw ValidationException::withMessages(['items' => 'بعض منتجات الجلسة لم تعد متاحة للحفظ.']);
        }
        foreach ($editableItems as $item) {
            if (! in_array($validated['items'][$item->id]['unit_type'], $this->allowedUnits($item->product), true)) {
                throw ValidationException::withMessages(["items.{$item->id}.unit_type" => 'وحدة العد لا تناسب هذا المنتج.']);
            }
        }

        $businessDate = $this->shifts->currentShiftContext($record->store_id)['business_date'];
        $saved = $this->counts->saveAccountantCounts(
            $record,
            $validated['items'],
            $businessDate,
            $validated['session_version'],
            (int) $actor->getAuthIdentifier(),
        );
        $this->loadVisibleDetails($saved, $actor);

        return ApiResponse::success(InventoryCountResource::session($saved, true, false));
    }

    private function visibleQuery(Request $request, int $storeId): Builder
    {
        $actor = $request->attributes->get('api_actor');
        $query = InventoryCountSession::query()->where('store_id', $storeId);

        return $actor instanceof Accountant
            ? $query->where('accountant_id', $actor->getAuthIdentifier())
            : $query->where('owner_id', $actor->getAuthIdentifier());
    }

    private function resolveStore(Request $request)
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);

        return $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
    }

    private function allowedUnits(Product $product): array
    {
        if ($product->product_type === 'fractional') {
            return ['roll', 'meter'];
        }
        if ($product->is_splittable) {
            return ['kit', 'piece'];
        }

        return ['piece'];
    }

    private function loadVisibleDetails(InventoryCountSession $session, mixed $actor): void
    {
        $session->load([
            'store:id,name',
            'accountant:id,name',
            'items' => function (Builder $query) use ($session, $actor): void {
                if ($actor instanceof Accountant) {
                    $session->status === 'returned_to_accountant'
                        ? $query->whereIn('decision', ['returned', 'recounted'])
                        : $query->where('decision', 'pending');
                }
                $query->with('product');
            },
        ]);
    }
}
