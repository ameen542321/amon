<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ApiStoreScopeService;
use App\Support\Api\ApiResponse;
use App\Support\Api\CatalogResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CatalogController extends Controller
{
    public function __construct(private readonly ApiStoreScopeService $stores) {}

    public function categories(Request $request): JsonResponse
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $categories = Category::query()->forStore($store->id)
            ->where('status', 'active')
            ->withCount(['products' => fn (Builder $query) => $query->sellable()->where('status', 'active')])
            ->orderByDesc('is_main_category')->orderBy('name')->orderBy('id')->get();

        return ApiResponse::success($categories->map(
            static fn (Category $category): array => CatalogResource::category($category, true)
        )->values());
    }

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'low_stock' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string'],
        ]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $query = Product::query()->with('fractions:id,product_id,option_label,deduction_value,price')
            ->forStore($store->id)->sellable()->where('status', 'active');

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }
        if (!empty($validated['barcode'])) {
            $query->where('barcode', trim($validated['barcode']));
        }
        if (!empty($validated['low_stock'])) {
            $query->lowStock();
        }
        if ($term = $this->searchTerm($validated['q'] ?? null)) {
            $escaped = addcslashes($term, '%_\\');
            $query->where(function (Builder $query) use ($escaped): void {
                $query->where('name', 'like', "%{$escaped}%")
                    ->orWhere('description', 'like', "%{$escaped}%")
                    ->orWhere('barcode', $escaped);
            });
        }

        $products = $query->orderBy('id')->cursorPaginate($validated['limit'] ?? 25);

        return ApiResponse::success(
            collect($products->items())->map(static fn (Product $product): array => CatalogResource::product($product))->values(),
            ['next_cursor' => $products->nextCursor()?->encode(), 'per_page' => $products->perPage()],
        );
    }

    public function show(Request $request, int $product): JsonResponse
    {
        $validated = $request->validate(['store_id' => ['required', 'integer', 'min:1']]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $record = Product::query()->with('fractions:id,product_id,option_label,deduction_value,price')
            ->forStore($store->id)->sellable()->where('status', 'active')->findOrFail($product);

        return ApiResponse::success(CatalogResource::product($record));
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'min:1'],
            'since' => ['required', 'date'],
            'until' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'product_cursor' => ['nullable', 'string'],
            'category_cursor' => ['nullable', 'string'],
        ]);
        $store = $this->stores->resolve($request->attributes->get('api_actor'), (int) $validated['store_id']);
        $since = Carbon::parse($validated['since'])->utc();
        $until = isset($validated['until']) ? Carbon::parse($validated['until'])->utc() : now()->utc();
        if ($until->isFuture() || $since->greaterThanOrEqualTo($until)) {
            return ApiResponse::error('SYNC_WINDOW_INVALID', 'نافذة مزامنة الكتالوج غير صالحة.', 422);
        }
        if ($since->lt($until->copy()->subDays(31))) {
            return ApiResponse::error('FULL_SYNC_REQUIRED', 'يلزم تنزيل كتالوج كامل قبل متابعة المزامنة.', 409, [
                'maximum_window_days' => 31,
            ]);
        }
        $limit = $validated['limit'] ?? 100;

        $products = Product::withTrashed()->with('fractions:id,product_id,option_label,deduction_value,price')
            ->forStore($store->id)->sellable()
            ->where('updated_at', '>', $since)->where('updated_at', '<=', $until)
            ->orderBy('updated_at')->orderBy('id')
            ->cursorPaginate($limit, ['*'], 'product_cursor', $validated['product_cursor'] ?? null);
        $categories = Category::withTrashed()->forStore($store->id)
            ->where('updated_at', '>', $since)->where('updated_at', '<=', $until)
            ->orderBy('updated_at')->orderBy('id')
            ->cursorPaginate($limit, ['*'], 'category_cursor', $validated['category_cursor'] ?? null);

        return ApiResponse::success([
            'products' => collect($products->items())->map(static fn (Product $product): array => CatalogResource::product($product))->values(),
            'categories' => collect($categories->items())->map(static fn (Category $category): array => CatalogResource::category($category))->values(),
        ], [
            'watermark' => $until->toIso8601String(),
            'next_product_cursor' => $products->nextCursor()?->encode(),
            'next_category_cursor' => $categories->nextCursor()?->encode(),
        ]);
    }

    private function searchTerm(?string $term): ?string
    {
        $term = preg_replace('/\s+/u', ' ', trim((string) $term));
        return $term === '' ? null : mb_substr($term, 0, 100);
    }
}
