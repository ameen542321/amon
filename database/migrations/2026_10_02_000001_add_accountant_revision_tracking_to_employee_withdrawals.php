<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = collect(['accountant_revision_count', 'accountant_revised_at'])
            ->reject(fn (string $column): bool => Schema::hasColumn('employee_withdrawals', $column));

        if ($missing->isEmpty()) {
            return;
        }

        Schema::table('employee_withdrawals', function (Blueprint $table) use ($missing): void {
            if ($missing->contains('accountant_revision_count')) {
                $table->unsignedTinyInteger('accountant_revision_count')->default(0);
            }

            if ($missing->contains('accountant_revised_at')) {
                $table->timestamp('accountant_revised_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = collect(['accountant_revision_count', 'accountant_revised_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('employee_withdrawals', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('employee_withdrawals', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
