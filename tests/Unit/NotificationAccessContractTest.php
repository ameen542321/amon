<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotificationAccessContractTest extends TestCase
{
    public function test_notification_mutations_use_the_same_recipient_scoped_query(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php');

        self::assertStringContainsString('visibleTo($recipient)->findOrFail($id)', $controller);
        self::assertStringContainsString('->visibleTo($recipient)', $controller);
        self::assertStringNotContainsString('redirect_to', $controller);
        self::assertStringNotContainsString('Notification::findOrFail', $controller);
    }

    public function test_read_and_hidden_markers_include_the_account_type(): void
    {
        $recipient = file_get_contents(dirname(__DIR__, 2).'/app/Support/Notifications/NotificationRecipient.php');
        $notification = file_get_contents(dirname(__DIR__, 2).'/app/Models/Notification.php');

        self::assertStringContainsString('return "{$this->type}:{$this->id}";', $recipient);
        self::assertStringContainsString('return "hidden_by_{$this->readMarker()}";', $recipient);
        self::assertStringContainsString('isReadByRecipient', $notification);
        self::assertStringContainsString('hideFromRecipient', $notification);
    }

    public function test_device_token_registration_supports_both_guards_with_one_route(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/DeviceTokenController.php');
        $webRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
        $adminRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');

        self::assertStringContainsString("Auth::guard('accountant')->user()", $controller);
        self::assertStringContainsString("Auth::guard('web')->user()", $controller);
        self::assertStringContainsString("'Cache-Control' => 'private, no-store'", $controller);
        self::assertSame(1, substr_count($webRoutes.$adminRoutes, "Route::post('/device-token'"));
    }

    public function test_admin_notification_routes_do_not_reference_missing_controller_actions(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');

        self::assertStringNotContainsString('deleteindex', $routes);
        self::assertStringNotContainsString('deleteSelected', $routes);
        self::assertStringContainsString("->name('notifications.markSelected')", $routes);
    }

    public function test_cleanup_is_scheduled_and_never_runs_during_model_retrieval(): void
    {
        $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/Notification.php');
        $command = file_get_contents(dirname(__DIR__, 2).'/app/Console/Commands/CleanupNotifications.php');
        $maintenance = file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMaintenanceService.php');
        $schedule = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

        self::assertStringNotContainsString('static::retrieved', $model);
        self::assertStringNotContainsString('rand(', $model);
        self::assertStringContainsString("notifications:cleanup", $command);
        self::assertStringContainsString('public const RETENTION_DAYS = 15;', $maintenance);
        self::assertStringContainsString('$maintenance->deleteExpired($chunkSize)', $command);
        self::assertStringContainsString("dailyAt('02:30')", $schedule);
        self::assertStringContainsString('withoutOverlapping()', $schedule);
    }

    public function test_push_delivery_is_queued_with_operational_limits(): void
    {
        $job = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendOneSignalNotification.php');
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/OneSignalService.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/AdminPushNotificationController.php');

        self::assertStringContainsString('implements ShouldQueue', $job);
        self::assertStringContainsString('public int $tries = 3;', $job);
        self::assertStringContainsString('public int $timeout = 30;', $job);
        self::assertStringContainsString('connectTimeout(5)', $service);
        self::assertStringContainsString('->timeout(10)', $service);
        self::assertStringContainsString('SendOneSignalNotification::dispatch(', $controller);
        self::assertStringContainsString('->chunk(1000)', $controller);
    }

    public function test_admin_has_a_notification_operations_center_with_guarded_deletion(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Admin/NotificationOperationsController.php');
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMaintenanceService.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/notifications/operations.blade.php');

        self::assertStringContainsString("name('notification-operations.index')", $routes);
        self::assertStringContainsString("middleware('throttle:2,1')", $routes);
        self::assertStringContainsString('public const RETENTION_DAYS = 15;', $service);
        self::assertStringContainsString('$maintenance->deleteExpired()', $controller);
        self::assertStringContainsString('لا يوجد زر لحذف كل السجلات', $view);
        self::assertStringContainsString('data-ui-confirm=', $view);
        self::assertStringNotContainsString('<style', $view);
        self::assertStringNotContainsString('style="', $view);
    }

    public function test_notification_payload_is_versioned_persisted_and_blocks_external_links(): void
    {
        $payload = file_get_contents(dirname(__DIR__, 2).'/app/Support/Notifications/NotificationPayload.php');
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationService.php');
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/notifications/show.blade.php');

        self::assertStringContainsString('public const VERSION = 1;', $payload);
        self::assertStringContainsString('NotificationPayload::normalize', $service);
        self::assertStringContainsString("'data'         =>", $service);
        self::assertStringContainsString('hash_equals', $payload);
        self::assertStringContainsString('safeWebUrl', $view);
        self::assertStringNotContainsString("href=\"{{ \$notification->data['url'] }}\"", $view);
    }

    public function test_pwa_notification_api_uses_existing_session_guards_and_scoped_queries(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/NotificationController.php');
        $ownerRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/user.php');
        $accountantRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/accountant.php');
        $publicApiRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/api.php');

        self::assertStringContainsString('->visibleTo($recipient)', $controller);
        self::assertStringContainsString('->cursorPaginate(', $controller);
        self::assertStringContainsString("Auth::guard('accountant')->user()", $controller);
        self::assertStringContainsString("Auth::guard('web')->user()", $controller);
        self::assertStringContainsString("prefix('api/v1/notifications')", $ownerRoutes);
        self::assertStringContainsString("middleware('throttle:120,1')", $ownerRoutes);
        self::assertStringContainsString("prefix('api/v1/notifications')", $accountantRoutes);
        self::assertStringContainsString("middleware('throttle:120,1')", $accountantRoutes);
        self::assertStringNotContainsString('notifications', $publicApiRoutes);
    }

    public function test_notification_staging_gate_is_repeatable_and_protects_real_data(): void
    {
        $package = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/package.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $checker = file_get_contents(dirname(__DIR__, 2).'/scripts/check-notification-readiness.mjs');
        $guide = file_get_contents(dirname(__DIR__, 2).'/docs/بوابة-Staging-لنظام-الإشعارات.md');

        self::assertSame(
            'node scripts/check-notification-readiness.mjs',
            $package['scripts']['test:notifications'],
        );
        self::assertStringContainsString("process.argv.includes('--staging-env')", $checker);
        self::assertStringContainsString("!['sync', 'null'].includes(queue)", $checker);
        self::assertStringContainsString('DB_DATABASE" value=":memory:" force="true"', $checker);
        self::assertStringContainsString('php artisan notifications:cleanup --dry-run', $guide);
        self::assertStringContainsString('لا تستخدم الإرسال العام `All`', $guide);
    }
}
