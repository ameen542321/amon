<?php

namespace Tests\Unit;

use App\Models\Store;
use App\Models\Sale;
use App\Models\User;
use App\Http\Controllers\Reports\SalesCostReportController;
use App\Services\Stores\StoreAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SalesCostReportEditingTest extends TestCase
{
    #[DataProvider('costStates')]
    public function test_cost_editor_is_available_only_for_zero_or_missing_labor_costs(?float $cost, bool $used, float $laborTotal = 20): void
    {
        $root = dirname(__DIR__, 2);
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        restore_error_handler();
        restore_exception_handler();
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::unprepared(file_get_contents($root.'/database/testing/sqlite-schema.sql'));

        $store = new Store(['name' => 'متجر الاختبار']);
        $store->id = 1;
        $store->user_id = 1;
        $date = '2026-10-01';
        $sale = new Sale(['store_id' => 1, 'labor_cost' => $cost, 'labor_total' => $laborTotal]);
        $order = new \App\Modules\PurchaseOrders\Models\StorePurchaseOrder(['supplier_name' => 'مورد الاختبار']);
        $order->id = 77;
        $order->created_at = $date;
        $row = [
            'id' => 42, 'business_date' => $date, 'accountant' => 'محاسب',
            'description' => 'عملية اختبار', 'products' => 'منتج',
            'sales_total' => 100, 'products_cost' => 10, 'labor_total' => $laborTotal,
            'can_edit_labor_cost' => $sale->canEditLaborCost(),
            'labor_cost' => $cost, 'has_labor_cost_snapshot' => $cost !== null,
            'labor_cost_breakdown' => $cost === null ? '' : 'تكلفة محفوظة',
            'total_cost' => 10 + ($cost ?? 0), 'cost_source' => 'محفوظة وقت البيع',
        ];
        $html = view('user.stores.reports.sales-cost', [
            'errors' => new \Illuminate\Support\ViewErrorBag,
            'store' => $store, 'from' => $date, 'to' => $date, 'search' => '',
            'excludeUsed' => false, 'rows' => collect([$row]),
            'purchaseOrders' => collect([$order]),
            'summary' => $row + ['operations_count' => 1, 'missing_labor_cost_count' => $cost === null ? 1 : 0],
            'usedDates' => collect($used ? [$date => true] : []),
            'matchedDates' => collect([$date]),
            'overlappingUsedDates' => collect($used ? [$date] : []),
        ])->render();

        self::assertStringNotContainsString('محفوظة وقت البيع', $html);
        self::assertStringNotContainsString('تكلفة محفوظة', $html);
        self::assertStringContainsString('id="sale-42"', $html);
        self::assertStringContainsString('مورد الاختبار', $html);
        self::assertStringContainsString('طلبية #77', $html);
        self::assertStringContainsString(route('user.stores.purchase-orders.show', [$store, $order]), $html);
        self::assertStringContainsString('aria-controls="cost-purchase-orders"', $html);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $inputs = $xpath->query('//input[@name="labor_cost"]');
        self::assertCount(!$used && $laborTotal > 0 && ($cost === null || $cost === 0.0) ? 1 : 0, $inputs, 'The shared mobile and desktop editor must follow eligibility rules.');
        if (!$sale->canEditLaborCost()) {
            $request = Request::create('/reports/sales-cost/42/labor-cost', 'PATCH', ['labor_cost' => 15]);
            $user = new User;
            $user->id = 1;
            $request->setUserResolver(fn () => $user);
            try {
                (new SalesCostReportController)->updateLaborCost($request, $store, $sale, new StoreAccessService);
                self::fail('A direct request must not modify a saved cost or a products-only sale.');
            } catch (HttpException $exception) {
                self::assertSame(422, $exception->getStatusCode());
                self::assertSame($cost, $sale->labor_cost);
            }
        }
        foreach ($inputs as $input) {
            self::assertSame('', $input->getAttribute('value'), 'A new labor cost must start empty, including when the stored cost is zero.');
            $form = $xpath->query('ancestor::form', $input)->item(0);
            self::assertStringContainsString('/42/labor-cost', $form->getAttribute('action'));
            self::assertSame('PATCH', $xpath->query('.//input[@name="_method"]', $form)->item(0)->getAttribute('value'));
        }
    }

    public static function costStates(): array
    {
        return [
            'missing' => [null, false], 'zero snapshot' => [0.0, false],
            'saved snapshot' => [12.5, false], 'used missing' => [null, true],
            'used zero snapshot' => [0.0, true], 'used saved snapshot' => [12.5, true],
            'products only missing' => [null, false, 0],
            'products only zero' => [0.0, false, 0],
            'products only saved' => [12.5, false, 0],
        ];
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }
}
