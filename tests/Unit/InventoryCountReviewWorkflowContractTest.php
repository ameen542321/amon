<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InventoryCountReviewWorkflowContractTest extends TestCase
{
    public function test_accountant_counting_has_an_offline_draft_contract_but_final_submit_stays_online(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/inventory-counts/accountant/show.blade.php');
        $script = file_get_contents(dirname(__DIR__, 2).'/resources/js/features/accountant/inventory-count.js');

        self::assertStringContainsString('data-inventory-count-connection-status', $view);
        self::assertStringContainsString('data-inventory-count-submit', $view);
        self::assertStringContainsString('putOutboxItem', $script);
        self::assertStringContainsString("operationType: 'inventory-count-draft'", $script);
        self::assertStringContainsString("submitButton.disabled = !online", $script);
        self::assertStringContainsString('الإرسال النهائي يتطلب الإنترنت', $script);
    }

    public function test_owner_review_orders_states_and_returns_to_the_next_pending_item(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/InventoryCountController.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/inventory-counts/owner/show.blade.php');

        self::assertStringContainsString("'pending' => 0", $controller);
        self::assertStringContainsString("'approved', 'adjusted_approved' => 2", $controller);
        self::assertStringContainsString('ownerReviewUrl', $controller);
        self::assertStringContainsString("'inventory-item-'.\$pending", $controller);
        self::assertStringContainsString('id="inventory-review-summary"', $view);
        self::assertStringContainsString('id="inventory-item-{{ $item->id }}"', $view);
        self::assertStringContainsString('معاد للمحاسب', $view);
        self::assertStringContainsString('المنتجات المعتمدة', $view);
        self::assertStringContainsString('اعتماد كمية المحاسب', $view);
        self::assertStringContainsString('تعديل واعتماد', $view);
        self::assertStringContainsString('إعادة للمحاسب', $view);
    }
}
