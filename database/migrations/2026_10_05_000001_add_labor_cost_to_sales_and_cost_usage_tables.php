<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        if ($this->columnExists($tableName, $columnName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($definition): void {
                $definition($table);
            });
        } catch (QueryException $exception) {
            $previous = $exception->getPrevious();
            $mysqlErrorCode = (int) ($exception->errorInfo[1] ?? $previous?->errorInfo[1] ?? 0);
            $sqlState = (string) ($exception->errorInfo[0] ?? $previous?->errorInfo[0] ?? $exception->getCode());
            $isDuplicateColumn = $mysqlErrorCode === 1060
                || $sqlState === '42S21'
                || str_contains(strtolower($exception->getMessage()), 'duplicate column');

            if (! $isDuplicateColumn) {
                throw $exception;
            }
        }
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $result = DB::selectOne(
                'SELECT COUNT(*) AS aggregate FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$tableName, $columnName]
            );

            return (int) ($result->aggregate ?? 0) > 0;
        }

        return Schema::hasColumn($tableName, $columnName);
    }
};
