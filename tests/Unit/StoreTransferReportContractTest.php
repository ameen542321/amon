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
        self::assertStringContainsString("CASE WHEN status = 'rejected' THEN 3 WHEN sender_store_id = ? THEN 1 ELSE 2 END ASC", $service);
        self::assertStringContainsString('مرسل إلى', $view);
        self::assertStringContainsString('وارد من', $view);
        self::assertStringContainsString('سبب الرفض', $view);
        self::assertStringContainsString('$transfer->notes', $view);
        self::assertStringContainsString('$transfer->items', $view);
        self::assertStringContainsString('تحميل PDF', $view);
        self::assertFileExists(dirname(__DIR__, 2).'/resources/views/pdf/store-transfer-report.blade.php');
        $pdf = file_get_contents(dirname(__DIR__, 2).'/resources/views/pdf/store-transfer-report.blade.php');
        self::assertStringContainsString('class="brand">CARLED', $pdf);
        self::assertStringContainsString('document-alert-title', $pdf);
        self::assertStringContainsString('document-help-title', $pdf);
        self::assertStringContainsString('الصادر أولًا، ثم النقل الوارد، ثم العمليات المرفوضة', $pdf);
        self::assertStringNotContainsString('نوع الوثيقة: تقرير نقل مخزني', $pdf);
        self::assertStringContainsString("'title' => 'الطلبات الصادرة'", $pdf);
        self::assertStringContainsString("'title' => 'الطلبات الواردة'", $pdf);
        self::assertStringContainsString("'title' => 'الطلبات المرفوضة'", $pdf);
        self::assertStringContainsString('تاريخ الإرسال:', $pdf);
        self::assertStringContainsString('تاريخ الاستلام:', $pdf);
        self::assertStringContainsString('<th>الملاحظات</th><th>الحالة</th>', $pdf);
        self::assertStringContainsString("\$summary['outgoing_cost']", $pdf);
        self::assertStringContainsString("\$summary['incoming_cost']", $pdf);
        self::assertStringContainsString('بسعر التكلفة المحفوظ وقت إنشاء طلب النقل', $pdf);
        self::assertStringContainsString("'outgoing_cost'", $service);
        self::assertStringContainsString("'incoming_cost'", $service);
    }
}
