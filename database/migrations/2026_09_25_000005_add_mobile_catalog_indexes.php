<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['store_id', 'status', 'usage_type', 'id'], 'products_mobile_catalog_index');
            $table->index(['store_id', 'barcode'], 'products_store_barcode_index');
            $table->index(['store_id', 'category_id', 'status'], 'products_store_category_status_index');
            $table->index(['store_id', 'updated_at', 'id'], 'products_store_updated_sync_index');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->index(['store_id', 'status', 'name'], 'categories_mobile_catalog_index');
            $table->index(['store_id', 'updated_at', 'id'], 'categories_store_updated_sync_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_mobile_catalog_index');
            $table->dropIndex('products_store_barcode_index');
            $table->dropIndex('products_store_category_status_index');
            $table->dropIndex('products_store_updated_sync_index');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_mobile_catalog_index');
            $table->dropIndex('categories_store_updated_sync_index');
        });
    }
};
