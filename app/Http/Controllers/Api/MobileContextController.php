<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\DeviceToken;
use App\Services\ApiAccountAccessService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileContextController extends Controller
{
    public function __construct(private readonly ApiAccountAccessService $access) {}

    public function me(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('api_actor');

        return ApiResponse::success([
            'type' => $this->access->actorType($actor),
            'id' => (int) $actor->getAuthIdentifier(),
            'name' => (string) $actor->name,
            'email' => (string) $actor->email,
        ]);
    }

    public function appConfig(): JsonResponse
    {
        return ApiResponse::success([
            'minimum_app_version' => config('mobile_api.minimum_app_version'),
            'features' => [
                'token_authentication' => true,
                'notifications' => true,
                'device_sessions' => true,
                'refresh_token_rotation' => true,
                'push_registration' => true,
                'catalog_read' => true,
                'catalog_delta_sync' => true,
                'inventory_read' => true,
                'inventory_integrity' => true,
                'shift_read' => true,
                'shift_gap_read' => true,
                'sales_read' => true,
                'sales_write' => false,
                'purchase_orders_read' => true,
                'purchase_orders_write' => false,
                'store_transfers_read' => true,
                'store_transfers_write' => false,
                'offline_sensitive_mutations' => false,
                'inventory_outbox' => false,
                'store_transfer_outbox' => false,
            ],
        ]);
    }

    public function stores(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('api_actor');
        $stores = $actor instanceof Accountant
            ? collect([$actor->store])->filter()
            : $actor->stores()->where('status', 'active')->orderBy('name')->get();

        return ApiResponse::success($stores->map(static fn ($store): array => [
            'id' => (int) $store->id,
            'name' => (string) $store->name,
            'status' => (string) $store->status,
        ])->values());
    }

    public function devices(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('api_actor');
        $current = $request->attributes->get('api_access_token');
        $tokens = \App\Models\ApiAccessToken::query()->where([
            'actor_type' => $this->access->actorType($actor),
            'actor_id' => (int) $actor->getAuthIdentifier(),
        ])->whereNull('revoked_at')->where('refresh_expires_at', '>', now())->orderByDesc('last_used_at')->get();

        return ApiResponse::success($tokens->map(static fn ($token): array => [
            'id' => (int) $token->id,
            'name' => $token->device_name,
            'platform' => $token->platform,
            'app_version' => $token->app_version,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at->toIso8601String(),
            'current' => $token->is($current),
        ])->values());
    }

    public function revokeDevice(Request $request, int $token): JsonResponse
    {
        $actor = $request->attributes->get('api_actor');
        $record = \App\Models\ApiAccessToken::query()->where([
            'actor_type' => $this->access->actorType($actor),
            'actor_id' => (int) $actor->getAuthIdentifier(),
        ])->whereKey($token)->whereNull('revoked_at')->where('expires_at', '>', now())->firstOrFail();
        if ($record->is($request->attributes->get('api_access_token'))) {
            return ApiResponse::error('CURRENT_DEVICE_REQUIRES_LOGOUT', 'استخدم مسار تسجيل الخروج لإلغاء الجهاز الحالي.', 422);
        }
        $record->forceFill(['revoked_at' => now(), 'refresh_token_hash' => null])->save();
        DeviceToken::query()->where('api_access_token_id', $record->id)->delete();

        return ApiResponse::success(['revoked' => true]);
    }
}
