<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_balances', function (Blueprint $table): void {
            $table->index(['store_id', 'business_date', 'id'], 'daily_balances_mobile_history_index');
            $table->index(['store_id', 'end_time', 'id'], 'daily_balances_mobile_closed_index');
        });
    }
    public function down(): void
    {
        Schema::table('daily_balances', function (Blueprint $table): void {
            $table->dropIndex('daily_balances_mobile_history_index');
            $table->dropIndex('daily_balances_mobile_closed_index');
        });
    }
};
