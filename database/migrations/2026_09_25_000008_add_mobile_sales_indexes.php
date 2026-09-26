<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['store_id','business_date','id'], 'sales_mobile_business_date_index');
            $table->index(['store_id','sale_type','id'], 'sales_mobile_type_index');
            $table->index(['store_id','has_invoice','id'], 'sales_mobile_invoice_index');
        });
    }
    public function down(): void {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_mobile_business_date_index'); $table->dropIndex('sales_mobile_type_index'); $table->dropIndex('sales_mobile_invoice_index');
        });
    }
};
