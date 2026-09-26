<?php

namespace App\Console\Commands;

use App\Services\NotificationMaintenanceService;
use Illuminate\Console\Command;

class CleanupNotifications extends Command
{
    protected $signature = 'notifications:cleanup
        {--dry-run : عرض عدد الإشعارات المستحقة دون حذف}
        {--chunk=500 : عدد السجلات في دفعة الحذف الواحدة}';

    protected $description = 'حذف الإشعارات التي تجاوزت مدة الاحتفاظ المعتمدة على دفعات';

    public function handle(NotificationMaintenanceService $maintenance): int
    {
        $chunkSize = min(2000, max(50, (int) $this->option('chunk')));
        $cutoff = now()->subDays(NotificationMaintenanceService::RETENTION_DAYS);
        $count = $maintenance->expiredCount();

        if ($this->option('dry-run')) {
            $this->info("سيحذف {$count} إشعارًا أقدم من {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $deleted = $maintenance->deleteExpired($chunkSize);

        $this->info("تم حذف {$deleted} إشعارًا وفق مدة الاحتفاظ البالغة 15 يومًا.");

        return self::SUCCESS;
    }
}
