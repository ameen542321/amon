<?php

namespace Tests\Unit;

use App\Http\Controllers\Reports\SalesCostReportController;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\Stores\StoreAccessService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalesCostReportSavingTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        restore_error_handler();
        restore_exception_handler();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Schema::create('sales', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('store_id');
            $table->decimal('labor_total', 12, 2); $table->decimal('labor_cost', 12, 2)->nullable();
            $table->decimal('products_total', 12, 2)->default(100);
            $table->text('labor_cost_breakdown')->nullable(); $table->date('business_date'); $table->timestamps();
        });
        Schema::create('sales_cost_used_dates', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('store_id'); $table->date('business_date');
        });
        $this->store = new Store(['user_id' => 1, 'name' => 'اختبار']);
        $this->store->id = 1;
        Sale::create(['store_id' => 1, 'labor_total' => 20, 'labor_cost' => null, 'business_date' => '2026-10-01']);
        Sale::create(['store_id' => 1, 'labor_total' => 30, 'labor_cost' => 0, 'business_date' => '2026-10-02']);
    }

    private function request(array $data, int $userId = 1): Request
    {
        $request = Request::create('/test', 'PATCH', $data + ['from' => '2026-10-01', 'to' => '2026-10-31', 'q' => 'تظليل', 'exclude_used' => 1]);
        $user = new User; $user->id = $userId;
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function test_bulk_save_updates_only_selected_labor_costs_and_returns_to_last_selected_row(): void
    {
        $response = (new SalesCostReportController)->updateSelectedLaborCosts(
            $this->request(['costs' => [1 => 12.34, 2 => 56.78], 'return_sale' => 2]), $this->store, new StoreAccessService
        );
        self::assertSame(12.34, Sale::find(1)->labor_cost);
        self::assertSame(56.78, Sale::find(2)->labor_cost);
        self::assertSame(100.0, Sale::find(1)->products_total);
        self::assertSame(100.0, Sale::find(2)->products_total);
        self::assertStringEndsWith('#sale-2', $response->getTargetUrl());
        parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $filters);
        self::assertSame('تظليل', $filters['q']);
        self::assertSame('1', $filters['exclude_used']);
    }

    public function test_bulk_save_leaves_unselected_rows_unchanged_and_rejects_unselected_anchor(): void
    {
        $response = (new SalesCostReportController)->updateSelectedLaborCosts(
            $this->request(['costs' => [1 => 7], 'return_sale' => 2]), $this->store, new StoreAccessService
        );
        self::assertSame(7.0, Sale::find(1)->labor_cost);
        self::assertSame(0.0, Sale::find(2)->labor_cost);
        self::assertStringEndsWith('#sale-1', $response->getTargetUrl());
    }

    public function test_single_save_preserves_filters_and_returns_to_its_row(): void
    {
        $response = (new SalesCostReportController)->updateLaborCost(
            $this->request(['labor_cost' => 9.5]), $this->store, Sale::find(1), new StoreAccessService
        );
        self::assertSame(9.5, Sale::find(1)->labor_cost);
        self::assertSame(0.0, Sale::find(2)->labor_cost);
        self::assertStringEndsWith('#sale-1', $response->getTargetUrl());
        self::assertStringContainsString('from=2026-10-01', $response->getTargetUrl());
    }

    #[DataProvider('blockedStates')]
    public function test_bulk_save_rejects_ineligible_rows_without_partial_updates(string $state): void
    {
        if ($state === 'positive cost') Sale::find(2)->update(['labor_cost' => 4]);
        if ($state === 'products only') Sale::find(2)->update(['labor_total' => 0]);
        if ($state === 'used date') DB::table('sales_cost_used_dates')->insert(['store_id' => 1, 'business_date' => '2026-10-02']);
        try {
            (new SalesCostReportController)->updateSelectedLaborCosts($this->request(['costs' => [1 => 10, 2 => 20]]), $this->store, new StoreAccessService);
            self::fail('Invalid selection must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('costs.2', $exception->errors());
            self::assertNull(Sale::find(1)->labor_cost);
            self::assertSame($state === 'positive cost' ? 4.0 : 0.0, Sale::find(2)->labor_cost);
        }
    }

    public static function blockedStates(): array
    {
        return [['positive cost'], ['products only'], ['used date']];
    }

    public function test_bulk_save_cannot_access_another_store(): void
    {
        Sale::find(2)->update(['store_id' => 2]);
        try {
            (new SalesCostReportController)->updateSelectedLaborCosts($this->request(['costs' => [1 => 10, 2 => 20]]), $this->store, new StoreAccessService);
            self::fail('Another store must be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
            self::assertNull(Sale::find(1)->labor_cost);
        }
    }

    public function test_bulk_save_cannot_access_another_owner(): void
    {
        try {
            (new SalesCostReportController)->updateSelectedLaborCosts($this->request(['costs' => [1 => 10]], 2), $this->store, new StoreAccessService);
            self::fail('Another owner must be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertNull(Sale::find(1)->labor_cost);
        }
    }

    #[DataProvider('invalidCosts')]
    public function test_invalid_bulk_input_does_not_change_costs(array $costs): void
    {
        try {
            (new SalesCostReportController)->updateSelectedLaborCosts($this->request(['costs' => $costs]), $this->store, new StoreAccessService);
            self::fail('Invalid values must be rejected.');
        } catch (ValidationException $exception) {
            self::assertNotEmpty($exception->errors());
            self::assertNull(Sale::find(1)->labor_cost);
        }
    }

    public static function invalidCosts(): array
    {
        return [[[]], [[1 => -1]], [[1 => '']], [[1 => 'abc']], [[1 => 100000000]], [['bad-id' => 10]], [array_fill_keys(range(1, 101), 10)]];
    }

    private function pdfReport(): \App\Services\Reports\SalesCostReportService
    {
        $reports = $this->createMock(\App\Services\Reports\SalesCostReportService::class);
        $reports->method('build')->willReturn([
            'from' => '2026-10-01', 'to' => '2026-10-31',
            'rows' => collect([
                ['id' => 1, 'business_date' => '2026-10-01', 'description' => 'EXPORT_EXCLUDED', 'products' => 'Excluded material', 'accountant' => 'محاسب', 'sales_total' => 999.99, 'labor_total' => 10, 'total_cost' => 888.88],
                ['id' => 2, 'business_date' => '2026-10-02', 'description' => 'EXPORT_SELECTED', 'products' => 'Selected material', 'accountant' => 'محاسب', 'sales_total' => 250, 'labor_total' => 30, 'total_cost' => 124.56],
            ]),
        ]);
        return $reports;
    }

    public function test_pdf_contains_only_selected_rows_with_selected_totals(): void
    {
        Schema::create('onesignal_settings', fn (Blueprint $table) => $table->id());
        $captured = null;
        app('view')->composer('pdf.sales-cost', function ($view) use (&$captured) {
            $captured = $view->getData();
        });
        $response = (new SalesCostReportController)->exportPdf(
            $this->request(['sale_ids' => [2]]), $this->store, new StoreAccessService, $this->pdfReport()
        );
        self::assertSame([2], $captured['rows']->pluck('id')->all());
        self::assertSame(124.56, $captured['summary']['total_cost']);
        self::assertSame(250.0, $captured['summary']['sales_total']);
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertStringStartsWith('%PDF-', $response->getContent());
        if ($path = getenv('SALES_COST_PDF_SAMPLE')) file_put_contents($path, $response->getContent());
        self::assertSame(0.0, Sale::find(2)->labor_cost, 'Export must never save an entered cost.');
    }

    public function test_pdf_rejects_ids_outside_the_current_store_report(): void
    {
        try {
            (new SalesCostReportController)->exportPdf($this->request(['sale_ids' => [2, 999]]), $this->store, new StoreAccessService, $this->pdfReport());
            self::fail('Selection outside the report must be rejected.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_pdf_rejects_empty_selection(): void
    {
        $this->expectException(ValidationException::class);
        (new SalesCostReportController)->exportPdf($this->request(['sale_ids' => []]), $this->store, new StoreAccessService, $this->pdfReport());
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite'); Facade::clearResolvedInstances(); Facade::setFacadeApplication(null); Container::setInstance(null);
        parent::tearDown();
    }
}
