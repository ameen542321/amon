<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\ApiAccessToken;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\ApiAccountAccessService;
use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthTokenController extends Controller
{
    public function __construct(private readonly ApiAccountAccessService $access) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_type' => ['required', 'in:user,accountant'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_uuid' => ['required', 'string', 'min:8', 'max:128'],
            'device_name' => ['required', 'string', 'max:128'],
            'platform' => ['required', 'in:android,ios,web'],
            'app_version' => ['required', 'string', 'max:32'],
        ]);

        $actor = $this->findActor($validated['account_type'], mb_strtolower($validated['email']));
        if (!$actor || !Hash::check($validated['password'], $actor->getAuthPassword())) {
            return ApiResponse::error('INVALID_CREDENTIALS', 'بيانات الدخول غير صحيحة.', 422);
        }
        if ($denial = $this->access->denialCode($actor)) {
            return ApiResponse::error($denial, 'الحساب غير متاح حاليًا.', 403);
        }
        $minimumVersion = (string) config('mobile_api.minimum_app_version');
        if (version_compare($validated['app_version'], $minimumVersion, '<')) {
            return ApiResponse::error('UPDATE_REQUIRED', 'يلزم تحديث التطبيق قبل تسجيل الدخول.', 426, [
                'minimum_app_version' => $minimumVersion,
            ]);
        }

        [$plainToken, $plainRefreshToken, $token] = DB::transaction(function () use ($actor, $validated, $request): array {
            [$plainToken, $plainRefreshToken] = $this->newTokenPair();
            $identity = [
                'actor_type' => $this->access->actorType($actor),
                'actor_id' => (int) $actor->getAuthIdentifier(),
            ];

            $previousDeviceTokenIds = ApiAccessToken::query()->where($identity)
                ->where('device_uuid', $validated['device_uuid'])->whereNull('revoked_at')->pluck('id');
            ApiAccessToken::query()->whereKey($previousDeviceTokenIds)
                ->update(['revoked_at' => now(), 'refresh_token_hash' => null]);
            DeviceToken::query()->whereIn('api_access_token_id', $previousDeviceTokenIds)->delete();
            $token = ApiAccessToken::query()->create([
                ...$identity,
                'token_hash' => hash('sha256', $plainToken),
                'refresh_token_hash' => hash('sha256', $plainRefreshToken),
                'device_uuid' => $validated['device_uuid'],
                'device_name' => $validated['device_name'],
                'platform' => $validated['platform'],
                'app_version' => $validated['app_version'],
                'abilities' => ['app:read', 'devices:manage', 'notifications:read', 'notifications:write', 'push:manage'],
                'last_ip' => $request->ip(),
                'last_user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'last_used_at' => now(),
                'expires_at' => now()->addDays(max(1, config('mobile_api.token_lifetime_days'))),
                'refresh_expires_at' => now()->addDays(max(1, config('mobile_api.refresh_token_lifetime_days'))),
            ]);

            $excess = ApiAccessToken::query()->where($identity)->whereNull('revoked_at')
                ->where('refresh_expires_at', '>', now())
                ->orderByDesc('last_used_at')->pluck('id')
                ->slice(max(1, config('mobile_api.max_devices_per_account')));
            if ($excess->isNotEmpty()) {
                ApiAccessToken::query()->whereKey($excess)->update(['revoked_at' => now(), 'refresh_token_hash' => null]);
                DeviceToken::query()->whereIn('api_access_token_id', $excess)->delete();
            }

            return [$plainToken, $plainRefreshToken, $token];
        });

        return ApiResponse::success([
            'token' => $plainToken,
            'refresh_token' => $plainRefreshToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at->toIso8601String(),
            'refresh_expires_at' => $token->refresh_expires_at->toIso8601String(),
            'account' => $this->serializeActor($actor),
        ], status: 201);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string', 'starts_with:carled_refresh_', 'max:255'],
            'device_uuid' => ['required', 'string', 'min:8', 'max:128'],
            'app_version' => ['required', 'string', 'max:32'],
        ]);
        $minimumVersion = (string) config('mobile_api.minimum_app_version');
        if (version_compare($validated['app_version'], $minimumVersion, '<')) {
            return ApiResponse::error('UPDATE_REQUIRED', 'يلزم تحديث التطبيق قبل متابعة الاستخدام.', 426, [
                'minimum_app_version' => $minimumVersion,
            ]);
        }

        $result = DB::transaction(function () use ($validated, $request): ?array {
            $token = ApiAccessToken::query()
                ->where('refresh_token_hash', hash('sha256', $validated['refresh_token']))
                ->lockForUpdate()->first();
            if (!$token || !$token->canRefresh() || !hash_equals($token->device_uuid, $validated['device_uuid'])) {
                return null;
            }

            $actor = $token->actor_type === 'accountant'
                ? Accountant::query()->find($token->actor_id)
                : User::query()->find($token->actor_id);
            if (!$actor || $this->access->denialCode($actor)) {
                $token->forceFill(['revoked_at' => now()])->save();
                DeviceToken::query()->where('api_access_token_id', $token->id)->delete();
                return null;
            }

            [$plainToken, $plainRefreshToken] = $this->newTokenPair();
            $token->forceFill([
                'token_hash' => hash('sha256', $plainToken),
                'refresh_token_hash' => hash('sha256', $plainRefreshToken),
                'app_version' => $validated['app_version'],
                'last_ip' => $request->ip(),
                'last_user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'last_used_at' => now(),
                'last_refreshed_at' => now(),
                'expires_at' => now()->addDays(max(1, config('mobile_api.token_lifetime_days'))),
                'refresh_expires_at' => now()->addDays(max(1, config('mobile_api.refresh_token_lifetime_days'))),
            ])->save();

            return [$plainToken, $plainRefreshToken, $token];
        });

        if (!$result) {
            return ApiResponse::error('REFRESH_TOKEN_INVALID', 'رمز التجديد غير صالح أو منتهي.', 401);
        }
        [$plainToken, $plainRefreshToken, $token] = $result;

        return ApiResponse::success([
            'token' => $plainToken,
            'refresh_token' => $plainRefreshToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at->toIso8601String(),
            'refresh_expires_at' => $token->refresh_expires_at->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_access_token');
        DB::transaction(function () use ($token): void {
            $token->forceFill(['revoked_at' => now(), 'refresh_token_hash' => null])->save();
            DeviceToken::query()->where('api_access_token_id', $token->id)->delete();
        });

        return ApiResponse::success(['revoked' => true]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $actor = $request->attributes->get('api_actor');
        $tokens = ApiAccessToken::query()->where([
            'actor_type' => $this->access->actorType($actor),
            'actor_id' => (int) $actor->getAuthIdentifier(),
        ])->whereNull('revoked_at');
        $ids = (clone $tokens)->pluck('id');
        DB::transaction(function () use ($tokens, $ids): void {
            $tokens->update(['revoked_at' => now(), 'refresh_token_hash' => null]);
            DeviceToken::query()->whereIn('api_access_token_id', $ids)->delete();
        });

        return ApiResponse::success(['revoked_all' => true]);
    }

    private function newTokenPair(): array
    {
        return ['carled_'.Str::random(64), 'carled_refresh_'.Str::random(80)];
    }

    private function findActor(string $type, string $email): ?Authenticatable
    {
        return $type === 'accountant'
            ? Accountant::query()->whereRaw('LOWER(email) = ?', [$email])->first()
            : User::query()->whereRaw('LOWER(email) = ?', [$email])->where('role', User::ROLE_USER)->first();
    }

    private function serializeActor(Authenticatable $actor): array
    {
        return [
            'type' => $this->access->actorType($actor),
            'id' => (int) $actor->getAuthIdentifier(),
            'name' => (string) ($actor->name ?? ''),
        ];
    }
}
