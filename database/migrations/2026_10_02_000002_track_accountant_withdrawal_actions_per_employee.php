<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_withdrawal_accountant_actions')) {
            Schema::create('employee_withdrawal_accountant_actions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('person_id');
                $table->string('person_type', 100);
                $table->unsignedBigInteger('accountant_id');
                $table->unsignedBigInteger('withdrawal_id')->nullable();
                $table->date('business_date');
                $table->string('action', 20);
                $table->timestamps();

                // لكل موظف: تعديل واحد وحذف واحد في اليوم المحاسبي، ويمكن تكرارهما لموظف آخر.
                $table->unique(
                    ['store_id', 'person_id', 'person_type', 'business_date', 'action'],
                    'employee_withdrawal_accountant_daily_action_unique'
                );
            });
        }

        $legacyColumns = collect(['accountant_revision_count', 'accountant_revised_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('employee_withdrawals', $column))
            ->all();

        if ($legacyColumns !== []) {
            Schema::table('employee_withdrawals', fn (Blueprint $table) => $table->dropColumn($legacyColumns));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_withdrawal_accountant_actions');
    }
};
