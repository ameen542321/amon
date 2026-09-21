<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StoreTransferReportContractTest extends TestCase
{
    public function test_owner_report_exposes_period_status_direction_and_transfer_details(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/user.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/StoreController.php');
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/Reports/StoreTransferReportService.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/user/stores/reports/store-transfers.blade.php');

        self::assertStringContainsString("name('reports.store-transfers')", $routes);
        self::assertStringContainsString("name('reports.store-transfers.pdf')", $routes);
        self::assertStringContainsString('function reportsStoreTransfers(', $controller);
        self::assertStringContainsString('function reportsStoreTransfersPdf(', $controller);
        self::assertStringContainsString("'to' => 'required|date_format:Y-m-d|after_or_equal:from'", $controller);
        self::assertStringContainsString("->whereBetween('request_business_date'", $service);
        self::assertStringContainsString("->whereBetween('action_business_date'", $service);
        self::assertStringContainsString("'outgoing'", $service);
        self::assertStringContainsString("'incoming'", $service);
        self::assertStringContainsString("'rejected'", $service);
        self::assertStringContainsString('مرسل إلى', $view);
        self::assertStringContainsString('وارد من', $view);
        self::assertStringContainsString('سبب الرفض', $view);
        self::assertStringContainsString('$transfer->notes', $view);
        self::assertStringContainsString('$transfer->items', $view);
        self::assertStringContainsString('تحميل PDF', $view);
        self::assertFileExists(dirname(__DIR__, 2).'/resources/views/pdf/store-transfer-report.blade.php');
    }
}
