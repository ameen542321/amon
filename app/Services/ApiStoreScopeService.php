<?php

namespace App\Services;

use App\Models\Accountant;
use App\Models\Store;
use Illuminate\Contracts\Auth\Authenticatable;

class ApiStoreScopeService
{
    public function resolve(Authenticatable $actor, int $storeId): Store
    {
        $query = Store::query()->whereKey($storeId)->where('status', 'active');

        if ($actor instanceof Accountant) {
            $query->whereKey((int) $actor->store_id)
                ->where('user_id', (int) $actor->user_id);
        } else {
            $query->where('user_id', (int) $actor->getAuthIdentifier());
        }

        return $query->firstOrFail();
    }
}
