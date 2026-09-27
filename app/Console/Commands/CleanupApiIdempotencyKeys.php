<?php

namespace App\Console\Commands;

use App\Models\ApiIdempotencyKey;
use Illuminate\Console\Command;

class CleanupApiIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:cleanup {--dry-run : Count expired keys without deleting them} {--chunk=500 : Maximum rows per delete query}';

    protected $description = 'Delete expired API idempotency records in bounded chunks';

    public function handle(): int
    {
        $chunk = max(1, min(5000, (int) $this->option('chunk')));
        $query = ApiIdempotencyKey::query()->where('expires_at', '<=', now());
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Expired idempotency records: {$count}");

            return self::SUCCESS;
        }

        $deleted = 0;
        do {
            $ids = (clone $query)->orderBy('id')->limit($chunk)->pluck('id');
            $batch = $ids->isEmpty() ? 0 : ApiIdempotencyKey::query()->whereKey($ids)->delete();
            $deleted += $batch;
        } while ($batch === $chunk);

        $this->info("Deleted idempotency records: {$deleted}");

        return self::SUCCESS;
    }
}
