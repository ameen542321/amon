<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;

class NotificationMaintenanceService
{
    public const RETENTION_DAYS = 15;

    public function expiredQuery(): Builder
    {
        return Notification::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    public function expiredCount(): int
    {
        return $this->expiredQuery()->count();
    }

    public function deleteExpired(int $chunkSize = 500): int
    {
        $chunkSize = min(2000, max(50, $chunkSize));
        $deleted = 0;

        $this->expiredQuery()->select('id')->chunkById(
            $chunkSize,
            function ($notifications) use (&$deleted): void {
                $deleted += Notification::query()
                    ->whereKey($notifications->pluck('id'))
                    ->delete();
            },
        );

        return $deleted;
    }
}
