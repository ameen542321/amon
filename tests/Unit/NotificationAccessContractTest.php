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
}
