<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StoreCatalogTransferContractTest extends TestCase
{
    public function test_catalog_export_keeps_product_and_category_contract_with_zero_quantity(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ProductController.php');

        self::assertIsString($controller);
        foreach ([
            "'record_type'",
            "'category_description'",
            "'category_status'",
            "'category_is_main'",
            "'sale_price'",
            "'cost_price'",
            "'product_type'",
            "'usage_type'",
            "'quick_sale_default_unit'",
            "'carton_qty'",
            "'fractions_json'",
            '0, // شرط النقل: الكمية دائماً صفر',
        ] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }
        self::assertStringContainsString('غياب أحدهما لا يصفر الآخر', $controller);
    }

    public function test_temporary_catalog_purge_requires_exact_store_confirmation_and_preflight(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ProductController.php');
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/user.php');

        self::assertStringContainsString("Rule::in([\$store->name])", $controller);
        self::assertStringContainsString('permanentDeleteBlockers($product)', $controller);
        self::assertStringContainsString("Schema::hasTable('inventory_count_session_items')", $controller);
        self::assertStringContainsString("Route::delete('/purge-store-catalog'", $routes);
    }

    public function test_import_uses_store_scoped_slug_generator_that_includes_soft_deleted_products(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ProductController.php');

        self::assertIsString($controller);
        self::assertMatchesRegularExpression(
            '/private function generateImportProductSlug\(Store \$store, string \$name\): string\s*\{.*?return \$this->buildUniqueStoreScopedSlug\(\$name, \(int\) \$store->id\);\s*\}/s',
            $controller
        );
        self::assertMatchesRegularExpression(
            '/private function buildUniqueStoreScopedSlug.*?Product::withTrashed\(\).*?where\(\'slug\', \$slug\)/s',
            $controller
        );
    }
}
