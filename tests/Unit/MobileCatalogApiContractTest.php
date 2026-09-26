<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileCatalogApiContractTest extends TestCase
{
    public function test_catalog_routes_are_read_only_scoped_and_ability_protected(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/api.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/Api/CatalogController.php');
        $scope = file_get_contents($root.'/app/Services/ApiStoreScopeService.php');

        self::assertStringContainsString("prefix('catalog')", $routes);
        self::assertStringContainsString("middleware('ability:catalog:read')", $routes);
        self::assertStringNotContainsString("Route::post('/products", $routes);
        self::assertStringNotContainsString("Route::patch('/products", $routes);
        self::assertStringContainsString("attributes->get('api_actor')", $controller);
        self::assertStringContainsString("where('user_id', (int) \$actor->getAuthIdentifier())", $scope);
        self::assertStringContainsString("whereKey((int) \$actor->store_id)", $scope);
    }

    public function test_catalog_payload_omits_cost_and_exposes_stable_decimal_strings(): void
    {
        $root = dirname(__DIR__, 2);
        $resource = file_get_contents($root.'/app/Support/Api/CatalogResource.php');

        self::assertStringNotContainsString("'cost_price'", $resource);
        self::assertStringContainsString("'price' => self::decimal", $resource);
        self::assertStringContainsString("'stock' => self::decimal", $resource);
        self::assertStringContainsString("number_format((float) \$value", $resource);
        self::assertStringContainsString("'deleted_at'", $resource);
        self::assertStringContainsString("'fraction_options'", $resource);
        self::assertStringContainsString("'deduction' => self::decimal", $resource);
    }

    public function test_search_pagination_and_bounded_delta_sync_are_present(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root.'/app/Http/Controllers/Api/CatalogController.php');
        $migration = file_get_contents($root.'/database/migrations/2026_09_25_000005_add_mobile_catalog_indexes.php');

        self::assertStringContainsString('cursorPaginate', $controller);
        self::assertStringContainsString("with('fractions:id,product_id,option_label,deduction_value,price')", $controller);
        self::assertStringContainsString("'barcode' => ['nullable'", $controller);
        self::assertStringContainsString('FULL_SYNC_REQUIRED', $controller);
        self::assertStringContainsString("'watermark' =>", $controller);
        self::assertStringContainsString("where('updated_at', '>', \$since)", $controller);
        self::assertStringContainsString('products_store_updated_sync_index', $migration);
        self::assertStringContainsString('products_store_barcode_index', $migration);
        self::assertStringContainsString('categories_store_updated_sync_index', $migration);
    }
}
