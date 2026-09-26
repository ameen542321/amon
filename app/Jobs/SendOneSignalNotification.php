<?php

namespace App\Jobs;

use App\Services\OneSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendOneSignalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly array $deviceTokens,
        public readonly string $title,
        public readonly string $message,
        public readonly array $data = [],
    ) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(OneSignalService $oneSignal): void
    {
        if (!$oneSignal->sendToDevices($this->deviceTokens, $this->title, $this->message, $this->data)) {
            throw new RuntimeException('تعذر تسليم إشعار OneSignal.');
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('فشل إرسال OneSignal بعد استنفاد المحاولات.', [
            'device_count' => count($this->deviceTokens),
            'error' => $exception->getMessage(),
        ]);
    }
}
