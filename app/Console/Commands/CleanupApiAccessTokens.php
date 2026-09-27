<?php

namespace App\Console\Commands;

use App\Models\ApiAccessToken;
use App\Models\DeviceToken;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CleanupApiAccessTokens extends Command
{
    protected $signature = 'api-tokens:cleanup {--dry-run} {--chunk=500}';

    protected $description = 'Delete expired tokens and old revoked device sessions';

    public function handle(): int
    {
        $chunk = max(1, min(5000, (int) $this->option('chunk')));
        $query = ApiAccessToken::query()->where(function (Builder $query): void {
            $query->where('refresh_expires_at', '<=', now())
                ->orWhere(function (Builder $legacy): void {
                    $legacy->whereNull('refresh_expires_at')->where('expires_at', '<=', now());
                })
                ->orWhere('revoked_at', '<=', now()->subDays(7));
        });
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Expired or old revoked API tokens: {$count}");
            return self::SUCCESS;
        }

        $deleted = 0;
        do {
            $ids = (clone $query)->orderBy('id')->limit($chunk)->pluck('id');
            if ($ids->isNotEmpty()) {
                DeviceToken::query()->whereIn('api_access_token_id', $ids)->delete();
            }
            $batch = $ids->isEmpty() ? 0 : ApiAccessToken::query()->whereKey($ids)->delete();
            $deleted += $batch;
        } while ($batch === $chunk);

        $this->info("Deleted API token records: {$deleted}");
        return self::SUCCESS;
    }
}
