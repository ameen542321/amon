<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\WorkspaceController;
use App\Models\Accountant;
use App\Models\User;
use App\Services\WorkspaceUiService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WorkspaceUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        restore_error_handler(); restore_exception_handler();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('stores', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('name'); $t->string('status'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('products', function (Blueprint $t) { $t->id(); $t->integer('store_id'); $t->string('name'); $t->string('barcode')->nullable(); $t->string('usage_type')->nullable(); $t->string('status')->default('active'); $t->softDeletes(); });
        Schema::create('store_purchase_orders', function (Blueprint $t) { $t->id(); $t->integer('store_id'); $t->integer('user_id'); $t->integer('accountant_id')->nullable(); $t->string('supplier_name'); $t->string('workflow_status'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('inventory_count_sessions', function (Blueprint $t) { $t->id(); $t->integer('store_id'); $t->integer('owner_id'); $t->integer('accountant_id'); $t->string('status'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('store_transfers', function (Blueprint $t) { $t->id(); $t->integer('receiver_store_id'); $t->string('status'); $t->timestamps(); });
        Schema::create('support_tickets', function (Blueprint $t) { $t->id(); $t->string('subject'); $t->string('reference'); $t->string('status'); $t->softDeletes(); $t->timestamps(); });
        DB::table('stores')->insert([
            ['id' => 1, 'user_id' => 10, 'name' => 'متجري', 'status' => 'active'],
            ['id' => 2, 'user_id' => 20, 'name' => 'متجر آخر', 'status' => 'active'],
            ['id' => 3, 'user_id' => 10, 'name' => 'متجر موقوف', 'status' => 'inactive'],
        ]);
        DB::table('products')->insert([
            ['id' => 1, 'store_id' => 1, 'name' => 'منتج متاح', 'barcode' => '123', 'usage_type' => 'sale'],
            ['id' => 2, 'store_id' => 2, 'name' => 'منتج سري', 'barcode' => '124', 'usage_type' => 'sale'],
            ['id' => 3, 'store_id' => 1, 'name' => 'منتج المالك', 'barcode' => '125', 'usage_type' => 'owner_purchase'],
            ['id' => 4, 'store_id' => 3, 'name' => 'منتج موقوف', 'barcode' => '126', 'usage_type' => 'sale'],
        ]);
        $stamp = '2026-10-14 10:00:00';
        DB::table('store_purchase_orders')->insert([
            ['id' => 1, 'store_id' => 1, 'user_id' => 10, 'accountant_id' => 100, 'supplier_name' => 'مورد متاح', 'workflow_status' => 'pending_owner_review', 'created_at' => $stamp, 'updated_at' => $stamp],
            ['id' => 2, 'store_id' => 2, 'user_id' => 20, 'accountant_id' => 100, 'supplier_name' => 'مورد سري', 'workflow_status' => 'returned_for_edit', 'created_at' => $stamp, 'updated_at' => $stamp],
            ['id' => 3, 'store_id' => 1, 'user_id' => 10, 'accountant_id' => 101, 'supplier_name' => 'مورد محاسب آخر', 'workflow_status' => 'returned_for_edit', 'created_at' => $stamp, 'updated_at' => $stamp],
            ['id' => 4, 'store_id' => 1, 'user_id' => 10, 'accountant_id' => null, 'supplier_name' => 'مورد غير معين', 'workflow_status' => 'pending_receipt_confirmation', 'created_at' => $stamp, 'updated_at' => $stamp],
        ]);
        DB::table('inventory_count_sessions')->insert(['id' => 1, 'store_id' => 1, 'owner_id' => 10, 'accountant_id' => 100, 'status' => 'counting', 'created_at' => $stamp, 'updated_at' => $stamp]);
        DB::table('store_transfers')->insert(['id' => 1, 'receiver_store_id' => 1, 'status' => 'pending', 'created_at' => $stamp, 'updated_at' => $stamp]);
        $owner = new User; $owner->id = 10; $owner->role = 'user'; Auth::guard('web')->setUser($owner);
        $accountant = new Accountant; $accountant->id = 100; $accountant->store_id = 1; Auth::guard('accountant')->setUser($accountant);
    }

    private function call(string $role, string $action, array $query = []): \Illuminate\Http\JsonResponse
    {
        $request = Request::create("/$role/api/v1/workspace/$action", 'GET', $query);
        $route = new Route('GET', $request->path(), []); $route->name("$role.api.workspace.$action"); $request->setRouteResolver(fn () => $route);
        return (new WorkspaceController)($request, new WorkspaceUiService);
    }

    public function test_search_is_scoped_to_route_guard_and_store_without_costs(): void
    {
        $owner = $this->call('user', 'search', ['q' => 'منتج']);
        self::assertCount(2, $owner->getData(true)['data']['items']);
        self::assertSame('user:10', $owner->getData(true)['meta']['account_scope']);
        self::assertStringContainsString('private', $owner->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', $owner->headers->get('Cache-Control'));
        $accountant = $this->call('accountant', 'search', ['q' => 'منتج'])->getData(true)['data']['items'];
        self::assertCount(1, $accountant);
        self::assertStringContainsString('workspace_search=123', $accountant[0]['url']);
        self::assertSame(['title', 'detail', 'url'], array_keys($accountant[0]));
        self::assertCount(2, $this->call('accountant', 'search', ['q' => 'مورد'])->getData(true)['data']['items']);
        self::assertCount(1, $this->call('user', 'search', ['q' => 'PO-1-2026-00001'])->getData(true)['data']['items']);
        self::assertSame([], $this->call('user', 'search', ['q' => 'PO-1-2025-00001'])->getData(true)['data']['items']);
        self::assertSame([], $this->call('user', 'search', ['q' => '%%'])->getData(true)['data']['items']);
    }

    public function test_a_foreign_or_suspended_store_is_rejected(): void
    {
        foreach (['user', 'accountant'] as $role) {
            foreach ([2, 3] as $id) {
                try { $this->call($role, 'search', ['q' => 'منتج', 'store_id' => $id]); self::fail('Foreign store accepted'); }
                catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
            }
        }
    }

    public function test_missing_route_guard_is_unauthorized_even_if_other_guard_is_present(): void
    {
        Auth::guard('accountant')->forgetUser();
        try { $this->call('accountant', 'search', ['q' => 'منتج']); self::fail('Other guard was used'); }
        catch (HttpException $e) { self::assertSame(401, $e->getStatusCode()); }
        self::assertCount(2, $this->call('user', 'search', ['q' => 'منتج'])->getData(true)['data']['items']);
    }

    public function test_task_counts_follow_the_actionable_role_and_never_mutate_records(): void
    {
        self::assertSame(2, $this->call('user', 'tasks')->getData(true)['data']['total']);
        $tasks = $this->call('accountant', 'tasks')->getData(true)['data'];
        self::assertSame(3, $tasks['total']);
        self::assertCount(3, $tasks['items']);
        self::assertSame('pending', DB::table('store_transfers')->value('status'));
        self::assertSame('counting', DB::table('inventory_count_sessions')->value('status'));
        self::assertSame(4, DB::table('store_purchase_orders')->count());
    }

    public function test_admin_workspace_only_reads_support_tickets(): void
    {
        $admin = Auth::guard('web')->user(); $admin->role = 'admin';
        DB::table('support_tickets')->insert(['subject' => 'تذكرة اختبار', 'reference' => 'SUP-1', 'status' => 'waiting_support']);
        self::assertSame(1, $this->call('admin', 'tasks')->getData(true)['data']['total']);
        self::assertCount(1, $this->call('admin', 'search', ['q' => 'اختبار'])->getData(true)['data']['items']);
        self::assertSame([], $this->call('admin', 'search', ['q' => 'منتج'])->getData(true)['data']['items']);
        $this->expectException(HttpException::class); $this->call('user', 'tasks');
    }

    public function test_workspace_endpoints_retain_role_guards_and_private_api_middleware(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['user' => 'owner.unified', 'accountant' => 'accountant.unified', 'admin' => 'is.admin'] as $role => $guard) {
            foreach (['search', 'tasks'] as $action) {
                $route = $routes->getByName("$role.api.workspace.$action");
                self::assertNotNull($route);
                self::assertContains($guard, $route->gatherMiddleware());
                self::assertContains('api.contract', $route->gatherMiddleware());
            }
        }
        self::assertSame(\App\Http\Controllers\NotificationController::class.'@open', $routes->getByName('notifications.open')->getActionName());
    }
}
