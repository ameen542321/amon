<?php

namespace App\Support\Notifications;

use App\Models\Notification;

final class NotificationDeepLink
{
    public const VERSION = 1;

    public static function path(Notification|int $notification): string
    {
        $id = $notification instanceof Notification ? $notification->getKey() : $notification;

        return route('notifications.open', ['notification' => (int) $id], false);
    }

    public static function payload(Notification|int $notification): array
    {
        return NotificationPayload::normalize([
            'deep_link_version' => self::VERSION,
            'notification_id' => (int) ($notification instanceof Notification ? $notification->getKey() : $notification),
            'url' => self::path($notification),
        ]);
    }
}
