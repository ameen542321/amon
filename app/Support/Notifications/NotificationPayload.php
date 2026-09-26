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
            if (!is_string($key) || $key === 'version' || !self::isSafeValue($value)) {
                continue;
            }

            if ($key === 'url') {
                $value = self::safeWebUrl(['url' => $value]);
                if ($value === null) {
                    continue;
                }
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

        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\')) {
            return url($url);
        }

        $app = parse_url((string) config('app.url'));
        $candidate = parse_url($url);
        if (!is_array($app) || !is_array($candidate) || isset($candidate['user']) || isset($candidate['pass'])) {
            return null;
        }

        $appScheme = strtolower((string) ($app['scheme'] ?? ''));
        $urlScheme = strtolower((string) ($candidate['scheme'] ?? ''));
        $appHost = strtolower((string) ($app['host'] ?? ''));
        $urlHost = strtolower((string) ($candidate['host'] ?? ''));
        $defaultPort = static fn (string $scheme): ?int => match ($scheme) {
            'https' => 443,
            'http' => 80,
            default => null,
        };
        $appPort = $app['port'] ?? $defaultPort($appScheme);
        $urlPort = $candidate['port'] ?? $defaultPort($urlScheme);

        return $appScheme && $appHost
            && hash_equals($appScheme, $urlScheme)
            && hash_equals($appHost, $urlHost)
            && $appPort === $urlPort
                ? $url
                : null;
    }
}
