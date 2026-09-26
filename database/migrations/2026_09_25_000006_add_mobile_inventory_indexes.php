<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['store_id', 'id'], 'stock_movements_store_cursor_index');
            $table->index(['store_id', 'product_id', 'id'], 'stock_movements_product_cursor_index');
            $table->index(['store_id', 'business_date', 'id'], 'stock_movements_mobile_business_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_store_cursor_index');
            $table->dropIndex('stock_movements_product_cursor_index');
            $table->dropIndex('stock_movements_mobile_business_date_index');
        });
    }
};
