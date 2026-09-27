<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_cost_used_dates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('business_date');
            $table->string('search_term', 100)->nullable();
            $table->decimal('sales_total', 12, 2)->default(0);
            $table->decimal('products_cost', 12, 2)->default(0);
            $table->decimal('labor_total', 12, 2)->default(0);
            $table->unsignedInteger('operations_count')->default(0);
            $table->timestamps();

            // النسخة البسيطة تعتبر يوم العمل وحدة الاستخدام وتمنع تعليمه مرتين للمتجر نفسه.
            $table->unique(['store_id', 'business_date'], 'sales_cost_used_dates_store_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_cost_used_dates');
    }
};
