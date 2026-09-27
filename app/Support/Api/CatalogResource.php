<?php

namespace App\Support\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

final class CatalogResource
{
    public static function product(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'store_id' => (int) $product->store_id,
            'category_id' => $product->category_id ? (int) $product->category_id : null,
            'name' => (string) $product->name,
            'description' => $product->description,
            'barcode' => $product->barcode,
            'status' => (string) $product->status,
            'price' => self::decimal($product->price),
            'stock' => self::decimal($product->quantity, 6),
            'minimum_stock' => self::decimal($product->min_stock, 6),
            'product_type' => (string) $product->product_type,
            'usage_type' => $product->usage_type,
            'unit' => [
                'splittable' => (bool) $product->is_splittable,
                'items_per_unit' => (int) ($product->items_per_unit ?: 1),
                'piece_price' => self::nullableDecimal($product->piece_price),
                'roll_length' => self::nullableDecimal($product->roll_length, 4),
                'quick_sale_default_unit' => $product->quick_sale_default_unit,
            ],
            'fraction_options' => $product->relationLoaded('fractions')
                ? $product->fractions->map(static fn ($fraction): array => [
                    'id' => (int) $fraction->id,
                    'label' => (string) $fraction->option_label,
                    'deduction' => self::decimal($fraction->deduction_value, 4),
                    'price' => self::decimal($fraction->price),
                ])->values()
                : [],
            'image_url' => $product->image ? Storage::disk('public')->url($product->image) : null,
            'updated_at' => $product->updated_at?->toIso8601String(),
            'deleted_at' => $product->deleted_at?->toIso8601String(),
        ];
    }

    public static function category(Category $category, bool $withCount = false): array
    {
        return array_filter([
            'id' => (int) $category->id,
            'store_id' => (int) $category->store_id,
            'name' => (string) $category->name,
            'description' => $category->description,
            'status' => (string) $category->status,
            'is_main_category' => (bool) $category->is_main_category,
            'products_count' => $withCount ? (int) ($category->products_count ?? 0) : null,
            'updated_at' => $category->updated_at?->toIso8601String(),
            'deleted_at' => $category->deleted_at?->toIso8601String(),
        ], static fn (mixed $value, string $key): bool => $key !== 'products_count' || $withCount, ARRAY_FILTER_USE_BOTH);
    }

    private static function decimal(mixed $value, int $scale = 2): string
    {
        return number_format((float) $value, $scale, '.', '');
    }

    private static function nullableDecimal(mixed $value, int $scale = 2): ?string
    {
        return $value === null ? null : self::decimal($value, $scale);
    }
}
