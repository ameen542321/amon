<?php

namespace App\Support\Notifications;

/**
 * عقد بيانات صغير ومستقر يمكن أن تشترك فيه واجهات الويب وPWA وFlutter لاحقًا.
 */
final class NotificationPayload
{
    public const VERSION = 1;

    public static function normalize(array $data): array
    {
        $normalized = ['version' => self::VERSION];

        foreach ($data as $key => $value) {
            if (!is_string($key) || !self::isSafeValue($value)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private static function isSafeValue(mixed $value): bool
    {
        if (is_null($value) || is_scalar($value)) {
            return true;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_null($item) && !is_scalar($item)) {
                return false;
            }
        }

        return true;
    }

    public static function safeWebUrl(array $data): ?string
    {
        $url = $data['url'] ?? null;
        if (!is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return url($url);
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        return $appHost && $urlHost && hash_equals(strtolower($appHost), strtolower($urlHost))
            ? $url
            : null;
    }
}
