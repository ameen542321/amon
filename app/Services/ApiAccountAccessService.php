<?php

namespace App\Services;

use App\Models\Accountant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class ApiAccountAccessService
{
    public function denialCode(Authenticatable $actor): ?string
    {
        if ($actor instanceof User) {
            if ($actor->role !== User::ROLE_USER || $actor->status !== User::STATUS_ACTIVE) {
                return 'ACCOUNT_INACTIVE';
            }

            if ($actor->subscription_end_at && now()->greaterThan($actor->subscription_end_at->endOfDay())) {
                return 'SUBSCRIPTION_EXPIRED';
            }

            return null;
        }

        if (!$actor instanceof Accountant) {
            return 'ACCOUNT_INVALID';
        }

        $actor->loadMissing(['user', 'store']);
        if (!$actor->user || !$actor->store) return 'ACCOUNT_ORPHANED';
        if ($actor->status !== 'active' || $actor->user->status !== User::STATUS_ACTIVE) return 'ACCOUNT_INACTIVE';
        if ($actor->user->subscription_end_at && now()->greaterThan($actor->user->subscription_end_at->endOfDay())) {
            return 'SUBSCRIPTION_EXPIRED';
        }
        if ($actor->store->status !== 'active') return 'STORE_INACTIVE';

        return null;
    }

    public function actorType(Authenticatable $actor): string
    {
        return $actor instanceof Accountant ? 'accountant' : 'user';
    }
}
