<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuickSaleStoreDisplaySettingsContractTest extends TestCase
{
    public function test_owner_can_configure_the_tint_shortcut_and_latest_sale_visibility(): void
    {
        $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/Store.php');
        $storeController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/StoreController.php');
        $quickSaleController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Cashier/QuickSaleController.php');
        $storeForm = file_get_contents(dirname(__DIR__, 2).'/resources/views/user/stores/includes/store-form.blade.php');
        $quickSaleView = file_get_contents(dirname(__DIR__, 2).'/resources/views/cashier/quick-sale/index.blade.php');

        foreach (['show_quick_sale_tint', 'quick_sale_tint_label', 'quick_sale_tint_description', 'show_latest_quick_sale'] as $setting) {
            self::assertStringContainsString("'{$setting}'", $model);
            self::assertStringContainsString($setting, $storeController);
            self::assertStringContainsString("name=\"{$setting}\"", $storeForm);
        }

        self::assertStringContainsString('$showTintShortcut ? $this->tintProductsForStore', $quickSaleController);
        self::assertStringContainsString('$showLatestQuickSale ? $this->latestQuickSaleOperationForShift', $quickSaleController);
        self::assertStringContainsString("{{ \$tintShortcutLabel ?? 'تضليل' }}", $quickSaleView);
        self::assertStringContainsString("{{ \$tintShortcutDescription ?? 'إضافة عملية تضليل سريعة إلى السلة' }}", $quickSaleView);
        self::assertStringContainsString('@if(($showLatestQuickSale ?? true) && !empty($latestShiftOperation))', $quickSaleView);
    }
}
