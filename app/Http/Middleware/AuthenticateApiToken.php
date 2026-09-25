<?php

namespace App\Http\Middleware;

use App\Models\Accountant;
use App\Models\ApiAccessToken;
use App\Models\User;
use App\Services\ApiAccountAccessService;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(private readonly ApiAccountAccessService $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        if (!$plainToken || !str_starts_with($plainToken, 'carled_')) {
            return ApiResponse::error('TOKEN_MISSING', 'يلزم رمز دخول صالح.', 401);
        }

        $token = ApiAccessToken::query()->where('token_hash', hash('sha256', $plainToken))->first();
        if (!$token || !$token->isUsable()) {
            return ApiResponse::error('TOKEN_INVALID', 'رمز الدخول غير صالح أو منتهي.', 401);
        }

        $minimumVersion = (string) config('mobile_api.minimum_app_version');
        if (!$request->routeIs('api.v1.app-config')
            && (!$token->app_version || version_compare($token->app_version, $minimumVersion, '<'))) {
            return ApiResponse::error('UPDATE_REQUIRED', 'يلزم تحديث التطبيق قبل متابعة الاستخدام.', 426, [
                'minimum_app_version' => $minimumVersion,
            ]);
        }

        $actor = $token->actor_type === 'accountant'
            ? Accountant::query()->find($token->actor_id)
            : User::query()->find($token->actor_id);
        if (!$actor || ($denial = $this->access->denialCode($actor))) {
            $token->forceFill(['revoked_at' => now()])->save();

            return ApiResponse::error($denial ?? 'ACCOUNT_INVALID', 'الحساب غير متاح حاليًا.', 403);
        }

        if ($token->actor_type === 'accountant') Auth::guard('accountant')->setUser($actor);
        else Auth::guard('web')->setUser($actor);

        $request->attributes->set('api_access_token', $token);
        $request->attributes->set('api_actor', $actor);
        if (!$token->last_used_at || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill([
                'last_used_at' => now(),
                'last_ip' => $request->ip(),
                'last_user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ])->save();
        }

        return $next($request);
    }
}
