<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotificationDeepLinkContractTest extends TestCase
{
    public function test_push_opens_only_a_visible_internal_notification(): void
    {
        $link = file_get_contents(base_path('app/Support/Notifications/NotificationDeepLink.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/NotificationController.php'));
        $push = file_get_contents(base_path('app/Http/Controllers/AdminPushNotificationController.php'));
        $oneSignal = file_get_contents(base_path('app/Services/OneSignalService.php'));
        $routes = file_get_contents(base_path('routes/web.php'));
        $payload = file_get_contents(base_path('app/Support/Notifications/NotificationPayload.php'));

        self::assertStringContainsString("route('notifications.open'", $link);
        self::assertStringContainsString("'deep_link_version'", $link);
        self::assertStringContainsString('notificationFor($recipient, $notification)', $controller);
        self::assertStringContainsString('markAsReadByRecipient($recipient)', $controller);
        self::assertStringContainsString("name('notifications.open')", $routes);
        self::assertStringContainsString('NotificationDeepLink::payload($notification)', $push);
        self::assertStringContainsString("...($webUrl ? ['url' => $webUrl] : [])", $oneSignal);
        self::assertStringContainsString('NotificationPayload::safeWebUrl($data)', $oneSignal);
        self::assertStringContainsString("hash_equals($appScheme, $urlScheme)", $payload);
        self::assertStringContainsString("$appPort === $urlPort", $payload);
    }
}
