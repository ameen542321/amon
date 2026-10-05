<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PurchaseOrderFinalNoticeContractTest extends TestCase
{
    public function test_inventory_approval_notice_is_visible_for_only_twenty_four_hours(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Modules/PurchaseOrders/Services/StorePurchaseOrderService.php');
        $dashboard = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Accountant/DashboardController.php');
        $notification = file_get_contents(dirname(__DIR__, 2).'/app/Models/Notification.php');
        $alertsView = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/purchase-order-alerts-button.blade.php');

        self::assertStringContainsString("'final_notice_until' => now()->addHours(24)", $service);
        self::assertStringContainsString("'template_key' => 'purchase_order_approved_and_supplied'", $service);
        self::assertStringContainsString("where('workflow_status', 'approved_and_supplied')", $dashboard);
        self::assertStringContainsString("where('final_notice_until', '>', now())", $dashboard);
        self::assertStringContainsString("orWhere('created_at', '>=', now()->subHours(24))", $notification);
        self::assertStringContainsString('تم الاعتماد والاستلام المخزني', $alertsView);
    }
}
