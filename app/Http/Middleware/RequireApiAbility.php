<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $granted = $request->attributes->get('api_access_token')?->abilities ?? [];
        foreach ($abilities as $ability) {
            if (!in_array($ability, $granted, true)) {
                return ApiResponse::error('TOKEN_ABILITY_DENIED', 'رمز الجهاز لا يملك صلاحية هذا الطلب.', 403);
            }
        }

        return $next($request);
    }
}
