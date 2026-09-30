<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missing = collect([
            'show_quick_sale_tint',
            'quick_sale_tint_label',
            'quick_sale_tint_description',
            'show_latest_quick_sale',
        ])->reject(fn (string $column): bool => Schema::hasColumn('stores', $column));

        if ($missing->isEmpty()) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) use ($missing) {
            if ($missing->contains('show_quick_sale_tint')) {
                $table->boolean('show_quick_sale_tint')->default(true);
            }

            if ($missing->contains('quick_sale_tint_label')) {
                $table->string('quick_sale_tint_label', 100)->default('تضليل');
            }

            if ($missing->contains('quick_sale_tint_description')) {
                $table->string('quick_sale_tint_description', 180)->default('إضافة عملية تضليل سريعة إلى السلة');
            }

            if ($missing->contains('show_latest_quick_sale')) {
                $table->boolean('show_latest_quick_sale')->default(true);
            }
        });
    }

    public function down(): void
    {
        $columns = collect([
            'show_quick_sale_tint',
            'quick_sale_tint_label',
            'quick_sale_tint_description',
            'show_latest_quick_sale',
        ])->filter(fn (string $column): bool => Schema::hasColumn('stores', $column))->all();

        if ($columns !== []) {
            Schema::table('stores', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
