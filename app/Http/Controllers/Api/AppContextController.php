<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AppContextController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $account = $this->currentAccount();
        abort_unless($account, 401);

        $stores = $account instanceof User
            ? $account->stores()->select(['id', 'name'])->orderBy('name')->get()
            : collect([$account instanceof Accountant ? $account->store : null])->filter();

        return ApiResponse::success([
            'account' => [
                'type' => $account instanceof Accountant ? 'accountant' : 'user',
                'id' => (int) $account->getAuthIdentifier(),
                'name' => (string) ($account->name ?? ''),
            ],
            'stores' => $stores->map(static fn ($store): array => [
                'id' => (int) $store->id,
                'name' => (string) $store->name,
            ])->values(),
            'features' => [
                'notifications_api' => true,
                'idempotency_keys' => true,
                'offline_sensitive_mutations' => false,
            ],
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }

    private function currentAccount(): ?Authenticatable
    {
        return Auth::guard('accountant')->user() ?? Auth::guard('web')->user();
    }
}
