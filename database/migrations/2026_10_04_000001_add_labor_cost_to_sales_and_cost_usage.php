<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'labor_cost')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->decimal('labor_cost', 12, 2)->default(0)->after('labor_total');
            });
        }

        if (Schema::hasTable('sales_cost_used_dates') && ! Schema::hasColumn('sales_cost_used_dates', 'labor_cost')) {
            Schema::table('sales_cost_used_dates', function (Blueprint $table): void {
                $table->decimal('labor_cost', 12, 2)->default(0)->after('labor_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_cost_used_dates') && Schema::hasColumn('sales_cost_used_dates', 'labor_cost')) {
            Schema::table('sales_cost_used_dates', fn (Blueprint $table) => $table->dropColumn('labor_cost'));
        }
        if (Schema::hasColumn('sales', 'labor_cost')) {
            Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('labor_cost'));
        }
    }
};
