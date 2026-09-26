<?php

namespace App\Services;

use App\Models\OneSignalSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Support\Notifications\NotificationPayload;

class OneSignalService
{
    /**
     * جلب إعدادات OneSignal من قاعدة البيانات
     */
    private static function getConfig()
    {
        return OneSignalSetting::first();
    }

    /**
     * إرسال إشعار إلى أجهزة محددة
     */
    public function sendToDevices(array $deviceTokens, string $title, string $message, array $data = []): bool
    {
        if (empty($deviceTokens)) {
            return false;
        }

        $config = self::getConfig();
        if (!$config || !$config->app_id || !$config->api_key) {
            return false;
        }

        $data = NotificationPayload::normalize($data);
        $webUrl = NotificationPayload::safeWebUrl($data);
        $payload = [
            'app_id' => $config->app_id,
            'include_player_ids' => $deviceTokens,
            'headings' => [
                'en' => $title,
                'ar' => $title,
            ],
            'contents' => [
                'en' => $message,
                'ar' => $message,
            ],
            'data' => $data,
            ...($webUrl ? ['url' => $webUrl] : []),
        ];

        $response = Http::connectTimeout(5)
            ->timeout(10)
            ->retry(2, 250, throw: false)
            ->withHeaders([
                'Authorization' => "Basic {$config->api_key}",
                'Content-Type' => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', $payload);

        if (!$response->successful()) {
            Log::warning('رفض OneSignal طلب إرسال الإشعار.', [
                'status' => $response->status(),
                'device_count' => count($deviceTokens),
            ]);
        }

        return $response->successful();
    }

    /**
     * إرسال إشعار إلى جميع الأجهزة
     */
    public function sendToAll(string $title, string $message, array $data = []): bool
    {
        $config = self::getConfig();
        if (!$config || !$config->app_id || !$config->api_key) {
            return false;
        }

        $data = NotificationPayload::normalize($data);
        $webUrl = NotificationPayload::safeWebUrl($data);
        $payload = [
            'app_id' => $config->app_id,
            'included_segments' => ['All'],
            'headings' => [
                'en' => $title,
                'ar' => $title,
            ],
            'contents' => [
                'en' => $message,
                'ar' => $message,
            ],
            'data' => $data,
            ...($webUrl ? ['url' => $webUrl] : []),
        ];

        $response = Http::connectTimeout(5)
            ->timeout(10)
            ->retry(2, 250, throw: false)
            ->withHeaders([
                'Authorization' => "Basic {$config->api_key}",
                'Content-Type' => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', $payload);

        if (!$response->successful()) {
            Log::warning('فشل اختبار الإرسال العام عبر OneSignal.', [
                'status' => $response->status(),
            ]);
        }

        return $response->successful();
    }
}
