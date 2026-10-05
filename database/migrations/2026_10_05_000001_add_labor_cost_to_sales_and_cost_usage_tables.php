<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnIfMissing('sales', 'labor_cost', function (Blueprint $table): void {
                // تبقى null للعمليات السابقة حتى لا توحي قيمة صفرية بأن تكلفتها التاريخية كانت معروفة.
                $table->decimal('labor_cost', 12, 2)->nullable()->after('labor_total');
        });

        $this->addColumnIfMissing('sales', 'labor_cost_breakdown', function (Blueprint $table): void {
            $table->json('labor_cost_breakdown')->nullable()->after('labor_cost');
        });

        $this->addColumnIfMissing('sales_cost_used_dates', 'labor_cost', function (Blueprint $table): void {
            $table->decimal('labor_cost', 12, 2)->default(0)->after('labor_total');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_cost_used_dates', 'labor_cost')) {
            Schema::table('sales_cost_used_dates', function (Blueprint $table): void {
                $table->dropColumn('labor_cost');
            });
        }

        if (Schema::hasColumn('sales', 'labor_cost_breakdown')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropColumn('labor_cost_breakdown');
            });
        }

        if (Schema::hasColumn('sales', 'labor_cost')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropColumn('labor_cost');
            });
        }
    }

    /**
     * يدعم قواعد البيانات التي تعيد نتيجة قديمة من فحص الأعمدة بعد محاولة migration جزئية.
     */
    private function addColumnIfMissing(string $tableName, string $columnName, callable $definition): void
    {
        if (Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($definition): void {
                $definition($table);
            });
        } catch (QueryException $exception) {
            $mysqlErrorCode = (int) ($exception->errorInfo[1] ?? 0);

            if ($mysqlErrorCode !== 1060) {
                throw $exception;
            }
        }
    }
};
