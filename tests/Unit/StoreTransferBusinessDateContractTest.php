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
        self::assertStringContainsString('تم الإرسال في', $views);
        self::assertStringContainsString('سيتم الاستلام في', $views);
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

    public function test_receiver_product_matching_shows_unit_and_rejection_uses_a_modal(): void
    {
        $picker = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/store-transfers/product-picker.blade.php');
        $modal = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/store-transfers/rejection-modal.blade.php');
        $script = file_get_contents(dirname(__DIR__, 2).'/resources/js/features/store-transfers/transfer-system.js');
        $accountantView = file_get_contents(dirname(__DIR__, 2).'/resources/views/accountants/store-transfers/index.blade.php');
        $ownerView = file_get_contents(dirname(__DIR__, 2).'/resources/views/user/store-transfers/index.blade.php');

        self::assertStringContainsString('هل المنتج المستلم هو:', $picker);
        self::assertStringContainsString('رول أو متر', $picker);
        self::assertStringContainsString('طقم أو حبة', $picker);
        self::assertStringContainsString('data-unit-label', $picker);
        self::assertStringContainsString('selectedOption.dataset.unitLabel', $script);
        self::assertStringContainsString('سبب الرفض', $modal);
        self::assertStringContainsString('data-ui-show="reject-transfer-', $accountantView);
        self::assertStringContainsString('data-ui-show="reject-transfer-', $ownerView);
    }

    public function test_transfer_pages_use_the_shared_visual_workflow_without_inline_css(): void
    {
        $form = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/store-transfer-form.blade.php');
        $accountantView = file_get_contents(dirname(__DIR__, 2).'/resources/views/accountants/store-transfers/index.blade.php');
        $ownerView = file_get_contents(dirname(__DIR__, 2).'/resources/views/user/store-transfers/index.blade.php');
        $views = $form."\n".$accountantView."\n".$ownerView;

        self::assertStringContainsString('بيانات النقل', $form);
        self::assertStringContainsString('بنود طلب النقل', $form);
        self::assertStringContainsString('الملاحظات والإرسال', $form);
        self::assertStringContainsString('وارد يحتاج إجراء', $accountantView);
        self::assertStringNotContainsString('جميع التواريخ أدناه هي أيام عمل المحاسبة', $ownerView);
        self::assertStringNotContainsString('إرسال وخصم من المصدر', $ownerView);
        self::assertStringContainsString('this.items.unshift(this.makeItem())', file_get_contents(dirname(__DIR__, 2).'/resources/js/features/store-transfers/transfer-system.js'));
        self::assertStringContainsString('ui-card', $views);
        self::assertStringNotContainsString('<style', $views);
        self::assertStringNotContainsString('style="', $views);
    }
}
