<?php

namespace Tests\Unit;

use App\Http\Controllers\Accountant\InventoryCountController;
use App\Http\Controllers\Api\AppContextController;
use App\Http\Controllers\Api\PwaPreferencesController;
use App\Models\Accountant;
use App\Models\InventoryCountSession;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\InventoryCountService;
use App\Services\OneSignalService;
use App\Services\PushPreferenceService;
use App\Services\ShiftLifecycleService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

class PwaRuntimeBackendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        restore_error_handler();
        restore_exception_handler();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
        DB::purge('sqlite');
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_12_000001_create_notification_preferences_table.php')->up();
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('accountant_id')->nullable();
            $table->string('token');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('sender_type');
            $table->string('template_key')->nullable();
        });
        $user = new User;
        $user->id = 1;
        Auth::guard('web')->setUser($user);
        $accountant = new Accountant;
        $accountant->id = 1;
        Auth::guard('accountant')->setUser($accountant);
    }

    public function test_preferences_follow_the_route_guard_even_when_both_sessions_exist(): void
    {
        $controller = new PwaPreferencesController;
        $request = Request::create('/user/api/v1/pwa/preferences', 'PUT', ['announcements' => false, 'messages' => true, 'operations' => true]);
        $response = $controller->update($request);
        self::assertFalse($response->getData(true)['data']['announcements']);
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        self::assertSame('user', NotificationPreference::first()->actor_type);
        self::assertTrue($controller->show(Request::create('/accountant/api/v1/pwa/preferences'))->getData(true)['data']['announcements']);
        self::assertSame(1, NotificationPreference::count());
    }

    public function test_push_filter_respects_categories_and_identical_ids_in_different_roles(): void
    {
        DB::table('device_tokens')->insert([
            ['token' => 'owner', 'user_id' => 1, 'accountant_id' => null],
            ['token' => 'accountant', 'user_id' => null, 'accountant_id' => 1],
            ['token' => 'shared', 'user_id' => 1, 'accountant_id' => 1],
        ]);
        NotificationPreference::create(['actor_type' => 'user', 'actor_id' => 1, 'announcements' => false, 'messages' => true, 'operations' => true]);
        $service = new PushPreferenceService;
        self::assertSame(['accountant'], $service->filter(['owner', 'accountant', 'shared'], ['category' => 'announcements']));
        self::assertSame(['owner', 'accountant', 'shared'], $service->filter(['owner', 'accountant', 'shared'], ['category' => 'messages']));
        DB::table('notifications')->insert(['id' => 7, 'sender_type' => 'user', 'template_key' => 'inventory.changed']);
        self::assertSame('operations', $service->category(['notification_id' => 7]));
        self::assertSame(1, DB::table('notifications')->count(), 'Push preferences must not erase the inbox.');
    }

    public function test_inventory_json_save_returns_the_exact_saved_session_confirmation(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_type');
            $table->boolean('is_splittable')->default(false);
            $table->softDeletes();
        });
        Schema::create('inventory_count_session_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_count_session_id');
            $table->unsignedBigInteger('product_id');
            $table->string('decision');
        });
        DB::table('products')->insert(['id' => 5, 'product_type' => 'regular']);
        DB::table('inventory_count_session_items')->insert(['id' => 10, 'inventory_count_session_id' => 3, 'product_id' => 5, 'decision' => 'pending']);
        $session = new InventoryCountSession(['store_id' => 9, 'accountant_id' => 1, 'status' => 'counting']);
        $session->id = 3;
        $session->updated_at = '2026-10-12 10:05:00';
        $shift = $this->createMock(ShiftLifecycleService::class);
        $shift->method('currentShiftContext')->willReturn(['business_date' => '2026-10-12']);
        app()->instance(ShiftLifecycleService::class, $shift);
        $service = $this->createMock(InventoryCountService::class);
        $service->expects(self::once())->method('saveAccountantCounts')->with($session, [10 => ['accountant_quantity' => 5, 'unit_type' => 'piece']], '2026-10-12', '2026-10-12 10:00:00', 1)->willReturn($session);
        $request = Request::create('/accountant/inventory-counts/3/items', 'PUT', ['session_version' => '2026-10-12 10:00:00', 'items' => [10 => ['accountant_quantity' => 5, 'unit_type' => 'piece']]], server: ['HTTP_ACCEPT' => 'application/json']);
        $response = (new InventoryCountController)->bulkUpdate($request, $session, $service);
        self::assertSame(['saved' => true, 'session_id' => 3, 'session_version' => '2026-10-12T07:05:00.000000Z'], $response->getData(true)['data']);
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_app_context_uses_the_authenticated_role_of_the_route(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->softDeletes();
        });
        DB::table('stores')->insert(['id' => 9, 'user_id' => 1, 'name' => 'Test']);
        Auth::guard('accountant')->user()->store_id = 9;
        foreach (['user', 'accountant'] as $type) {
            app()->instance('request', Request::create('/'.$type.'/api/v1/app-context'));
            $response = (new AppContextController)();
            self::assertSame($type, $response->getData(true)['data']['account']['type']);
        }
    }

    public function test_muted_push_is_not_sent_or_retried(): void
    {
        DB::table('device_tokens')->insert(['token' => 'muted-device', 'user_id' => 1]);
        NotificationPreference::create(['actor_type' => 'user', 'actor_id' => 1, 'announcements' => false]);
        Http::fake();
        self::assertTrue((new OneSignalService)->sendToDevices(['muted-device'], 'Title', 'Body', ['category' => 'announcements']));
        Http::assertNothingSent();
    }

    public function test_push_to_all_registered_devices_is_batched_within_provider_limits(): void
    {
        Schema::create('onesignal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_id');
            $table->string('api_key');
        });
        DB::table('onesignal_settings')->insert(['app_id' => 'fake-app', 'api_key' => 'fake-key']);
        $devices = array_map(fn ($id) => ['token' => 'test-device-'.$id, 'user_id' => 1], range(1, 1001));
        DB::table('device_tokens')->insert($devices);
        Http::fake(['https://onesignal.com/api/v1/notifications' => Http::response(['id' => 'fake-delivery'], 200)]);
        self::assertTrue((new OneSignalService)->sendToAll('Title', 'Body'));
        Http::assertSentCount(2);
        self::assertSame([1000, 1], Http::recorded()->map(fn ($record) => count($record[0]['include_player_ids']))->all());
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
