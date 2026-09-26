<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Services\ApiStoreScopeService;
use App\Support\Api\ApiResponse;
use App\Support\Api\InventoryResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(private readonly ApiStoreScopeService $stores) {}

    public function summary(Request $request): JsonResponse
    {
        $store = $this->store($request);
        $products = Product::query()->forStore($store->id)->sellable()->where('status', 'active');
        $latest = StockMovement::query()->where('store_id', $store->id)->latest('id')->first();
        return ApiResponse::success([
            'products_count' => (clone $products)->count(),
            'low_stock_count' => (clone $products)->lowStock()->count(),
            'negative_stock_count' => (clone $products)->where('quantity', '<', 0)->count(),
            'movements_today' => StockMovement::query()->where('store_id', $store->id)->whereDate('created_at', today())->count(),
            'latest_movement_id' => $latest?->id,
            'latest_movement_at' => $latest?->created_at?->toIso8601String(),
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'min:1'], 'product_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'in:increase,decrease'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'], 'cursor' => ['nullable', 'string'],
        ]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $query = StockMovement::query()->with('product:id,name,product_type,is_splittable,items_per_unit,roll_length')
            ->where('store_id', $store->id);
        if (!empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (!empty($validated['from'])) {
            $query->whereDate('business_date', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $query->whereDate('business_date', '<=', $validated['to']);
        }
        $rows = $query->latest('id')->cursorPaginate($validated['limit'] ?? 50);
        return ApiResponse::success(collect($rows->items())->map(fn ($row) => InventoryResource::movement($row))->values(), [
            'next_cursor' => $rows->nextCursor()?->encode(), 'per_page' => $rows->perPage(),
        ]);
    }

    public function integrity(Request $request): JsonResponse
    {
        $store = $this->store($request);
        $latestIds = StockMovement::query()->where('store_id', $store->id)->whereNotNull('product_id')
            ->selectRaw('MAX(id)')->groupBy('product_id');
        $mismatches = DB::table('stock_movements as movement')->join('products as product', 'product.id', '=', 'movement.product_id')
            ->whereIn('movement.id', $latestIds)->whereRaw('ABS(COALESCE(movement.balance_after, movement.meters, 0) - product.quantity) > 0.0001')->count();
        $negativeProducts = Product::query()->forStore($store->id)->where('quantity', '<', 0)->count();
        $missingBalances = StockMovement::query()->where('store_id', $store->id)
            ->where(fn (Builder $q) => $q->whereNull('balance_before')->orWhereNull('balance_after'))->count();
        return ApiResponse::success([
            'negative_products' => $negativeProducts,
            'movements_missing_balances' => $missingBalances,
            'latest_balance_mismatches' => $mismatches,
            'healthy' => $mismatches === 0 && $negativeProducts === 0 && $missingBalances === 0,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function store(Request $request): Store
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);
        return $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
    }
}
