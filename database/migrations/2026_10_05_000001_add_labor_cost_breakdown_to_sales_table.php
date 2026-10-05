<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'labor_cost_breakdown')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->json('labor_cost_breakdown')->nullable()->after('labor_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'labor_cost_breakdown')) {
            Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('labor_cost_breakdown'));
        }
    }
};
