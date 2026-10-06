<?php

namespace Tests\Unit;

use App\Models\Store;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SalesCostReportEditingTest extends TestCase
{
    #[DataProvider('costStates')]
    public function test_cost_editor_is_available_for_missing_and_saved_costs(?float $cost, bool $used): void
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
        $date = '2026-10-01';
        $row = [
            'id' => 42, 'business_date' => $date, 'accountant' => 'محاسب',
            'description' => 'عملية اختبار', 'products' => 'منتج',
            'sales_total' => 100, 'products_cost' => 10, 'labor_total' => 20,
            'labor_cost' => $cost, 'has_labor_cost_snapshot' => $cost !== null,
            'labor_cost_breakdown' => $cost === null ? '' : 'تكلفة محفوظة',
            'total_cost' => 10 + ($cost ?? 0), 'cost_source' => 'محفوظة وقت البيع',
        ];
        $html = view('user.stores.reports.sales-cost', [
            'store' => $store, 'from' => $date, 'to' => $date, 'search' => '',
            'excludeUsed' => false, 'rows' => collect([$row]),
            'summary' => $row + ['operations_count' => 1, 'missing_labor_cost_count' => $cost === null ? 1 : 0],
            'usedDates' => collect($used ? [$date => true] : []),
            'matchedDates' => collect([$date]),
            'overlappingUsedDates' => collect($used ? [$date] : []),
        ])->render();

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $inputs = $xpath->query('//input[@name="labor_cost"]');
        self::assertCount($used ? 0 : 2, $inputs, 'Mobile and desktop editors must follow the same eligibility rule.');
        foreach ($inputs as $input) {
            self::assertSame($cost === null ? '' : (string) $cost, $input->getAttribute('value'));
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
