<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StoreTransferBusinessDateContractTest extends TestCase
{
    public function test_transfer_stock_operations_derive_dates_from_store_business_days(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/StoreTransferService.php');

        self::assertStringContainsString('private ShiftLifecycleService $shiftLifecycle', $service);
        self::assertStringContainsString('$businessDate = $this->businessDateFor($senderStore);', $service);
        self::assertStringContainsString('$businessDate = $this->businessDateFor($lockedTransfer->receiverStore);', $service);
        self::assertStringContainsString("'request_business_date' => \$businessDate", $service);
        self::assertStringContainsString("'action_business_date' => \$businessDate", $service);
    }

    public function test_transfer_views_display_accounting_dates_without_editable_date_inputs(): void
    {
        $views = implode("\n", array_map(
            fn (string $path) => file_get_contents(dirname(__DIR__, 2).$path),
            [
                '/resources/views/components/store-transfer-form.blade.php',
                '/resources/views/accountants/store-transfers/index.blade.php',
                '/resources/views/user/store-transfers/index.blade.php',
            ]
        ));

        self::assertStringNotContainsString('name="business_date"', $views);
        self::assertStringContainsString('يوم عمل الإرسال', $views);
        self::assertStringContainsString('تاريخ الاستلام والإضافة للمخزون', $views);
        self::assertStringContainsString('request_business_date', $views);
        self::assertStringContainsString('action_business_date', $views);
    }

    public function test_monthly_transfer_reports_filter_and_display_business_dates(): void
    {
        $report = file_get_contents(dirname(__DIR__, 2).'/app/Services/Reports/MonthlyStoreReportService.php');

        self::assertStringContainsString("->whereBetween('action_business_date'", $report);
        self::assertStringContainsString("->whereBetween('request_business_date'", $report);
        self::assertStringContainsString("'request_date' => \$transfer->request_business_date?->format('Y-m-d')", $report);
        self::assertStringNotContainsString("optional(\$transfer->created_at)->format('Y-m-d')", $report);
    }
}
