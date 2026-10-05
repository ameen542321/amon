<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LaborDescriptionHierarchyContractTest extends TestCase
{
    public function test_owner_can_configure_hierarchical_labor_descriptions_without_changing_sale_math(): void
    {
        $store = file_get_contents(dirname(__DIR__, 2).'/app/Models/Store.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/StoreController.php');
        $quickSaleController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Cashier/QuickSaleController.php');
        $storeForm = file_get_contents(dirname(__DIR__, 2).'/resources/views/user/stores/includes/store-form.blade.php');
        $storeEditor = file_get_contents(dirname(__DIR__, 2).'/resources/js/features/stores/labor-description-editor.js');
        $quickSaleView = file_get_contents(dirname(__DIR__, 2).'/resources/views/cashier/quick-sale/index.blade.php');
        $quickSaleInterface = file_get_contents(dirname(__DIR__, 2).'/resources/js/features/cashier/quick-sale-interface.js');

        self::assertStringContainsString('getLaborDescriptionGroupsListAttribute', $store);
        self::assertStringContainsString("=== 'counter' ? 'counter' : 'toggle'", $store);
        self::assertStringContainsString("labor_description_options.*.children.*.type", $controller);
        self::assertStringContainsString("labor_description_options.*.cost", $controller);
        self::assertStringContainsString("labor_description_options.*.children.*.cost", $controller);
        self::assertStringContainsString("'laborDescriptionGroups'", $quickSaleController);
        self::assertStringContainsString('laborDescriptionEditor', $storeForm);
        self::assertStringContainsString('إضافة خيار رئيسي', $storeForm);
        self::assertStringContainsString('عداد بالضغط', $storeForm);
        self::assertStringContainsString('تكلفة الرئيسي', $storeForm);
        self::assertStringContainsString('تكلفة الفرعي', $storeForm);
        self::assertStringContainsString("window.Alpine.data('laborDescriptionEditor'", $storeEditor);
        self::assertStringContainsString('selectLaborGroup(groupIndex)', $quickSaleView);
        self::assertStringContainsString('selectLaborChild(groupIndex, childIndex)', $quickSaleView);
        self::assertStringContainsString('selectedLaborGroups[groupIndex]', $quickSaleView);
        self::assertStringContainsString('يمكن اختيار أكثر من خيار رئيسي مع خياراته الإضافية', $quickSaleView);
        self::assertStringContainsString('طريقة اختيار تفاصيل العمل', $quickSaleView);
        self::assertStringNotContainsString('اختر التفاصيل؛ الضغط المتكرر على العداد يزيد العدد.', $quickSaleView);
        self::assertStringContainsString('@if($hasApprovedTaxNumber ?? false)', $quickSaleView);
        self::assertStringContainsString('الضريبة متاحة لهذا المتجر.', $quickSaleView);
        self::assertStringContainsString('rebuildLaborDescription()', $quickSaleInterface);
        self::assertStringContainsString('selectedLaborGroups: {}', $quickSaleInterface);
        self::assertStringContainsString("return [[group.label, ...selectedChildren].join(' ')]", $quickSaleInterface);
        self::assertStringContainsString("}).join('، ')", $quickSaleInterface);
        self::assertStringContainsString('laborSelectionsJson', $quickSaleInterface);
        self::assertStringNotContainsString('group.cost', $quickSaleView);
        self::assertStringNotContainsString('child.cost', $quickSaleView);
    }
}
