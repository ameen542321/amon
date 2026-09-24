<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class CleanupNotifications extends Command
{
    private const RETENTION_DAYS = 15;

    protected $signature = 'notifications:cleanup
        {--dry-run : عرض عدد الإشعارات المستحقة دون حذف}
        {--chunk=500 : عدد السجلات في دفعة الحذف الواحدة}';

    protected $description = 'حذف الإشعارات التي تجاوزت مدة الاحتفاظ المعتمدة على دفعات';

    public function handle(): int
    {
        $chunkSize = min(2000, max(50, (int) $this->option('chunk')));
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $expired = Notification::query()->where('created_at', '<', $cutoff);
        $count = (clone $expired)->count();

        if ($this->option('dry-run')) {
            $this->info("سيحذف {$count} إشعارًا أقدم من {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $deleted = 0;
        $expired->select('id')->chunkById($chunkSize, function ($notifications) use (&$deleted): void {
            $ids = $notifications->pluck('id');
            $deleted += Notification::query()->whereKey($ids)->delete();
        });

        $this->info("تم حذف {$deleted} إشعارًا وفق مدة الاحتفاظ البالغة 15 يومًا.");

        return self::SUCCESS;
    }
}
