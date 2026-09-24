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
        $schedule = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

        self::assertStringNotContainsString('static::retrieved', $model);
        self::assertStringNotContainsString('rand(', $model);
        self::assertStringContainsString("notifications:cleanup", $command);
        self::assertStringContainsString('private const RETENTION_DAYS = 15;', $command);
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
}
