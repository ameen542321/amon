<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PurchaseOrderReceiptValidationContractTest extends TestCase
{
    public function test_receipt_forms_explain_and_reveal_invalid_fields(): void
    {
        $view = file_get_contents(base_path('resources/views/modules/purchase-orders/user/show.blade.php'));
        $interface = file_get_contents(base_path('resources/js/features/purchase-orders/show-interface.js'));
        $styles = file_get_contents(base_path('resources/css/app.css'));
        $ownerController = file_get_contents(base_path('app/Modules/PurchaseOrders/Controllers/StorePurchaseOrderController.php'));
        $accountantController = file_get_contents(base_path('app/Modules/PurchaseOrders/Controllers/AccountantPurchaseOrderController.php'));

        self::assertGreaterThanOrEqual(2, substr_count($view, 'js-receipt-validation-summary'));
        self::assertStringContainsString('تعذر حفظ بيانات الاستلام', $view);
        self::assertStringContainsString('type="submit" class="ui-btn ui-btn-primary', $view);
        self::assertStringContainsString("form.addEventListener('invalid'", $interface);
        self::assertStringContainsString('unresolvedReceiptIssues', $interface);
        self::assertStringContainsString('revealReceiptTarget', $interface);
        self::assertStringContainsString('scrollIntoView', $interface);
        self::assertStringContainsString("activeOwnerProductCard.dataset.unresolved = '0'", $interface);
        self::assertStringContainsString('openButton.replaceWith(linkedBadge)', $interface);
        self::assertStringNotContainsString('window.location.reload()', $interface);
        self::assertStringContainsString('.ui-input[aria-invalid="true"]', $styles);
        self::assertStringContainsString('$receiptAttributes', $ownerController);
        self::assertStringContainsString("'owner_purchase_only'", $ownerController);
        self::assertStringContainsString('$receiptAttributes', $accountantController);
        self::assertStringNotContainsString('<style', $view);
        self::assertStringNotContainsString('style=', $view);
    }
}
