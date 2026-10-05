<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            // تبقى null للعمليات السابقة حتى لا توحي قيمة صفرية بأن تكلفتها التاريخية كانت معروفة.
            $table->decimal('labor_cost', 12, 2)->nullable()->after('labor_total');
            $table->json('labor_cost_breakdown')->nullable()->after('labor_cost');
        });

        Schema::table('sales_cost_used_dates', function (Blueprint $table): void {
            $table->decimal('labor_cost', 12, 2)->default(0)->after('labor_total');
        });
    }

    public function down(): void
    {
        Schema::table('sales_cost_used_dates', function (Blueprint $table): void {
            $table->dropColumn('labor_cost');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['labor_cost', 'labor_cost_breakdown']);
        });
    }
};
