<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\DeviceToken;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'provider' => ['sometimes', 'in:onesignal'],
        ]);
        $actor = $request->attributes->get('api_actor');
        $apiToken = $request->attributes->get('api_access_token');

        DB::transaction(function () use ($validated, $actor, $apiToken): void {
            // A provider token belongs to exactly one authenticated device at a time.
            DeviceToken::query()->where('token', $validated['token'])->delete();
            DeviceToken::query()->create([
                'user_id' => $actor instanceof Accountant ? null : $actor->getAuthIdentifier(),
                'accountant_id' => $actor instanceof Accountant ? $actor->getAuthIdentifier() : null,
                'api_access_token_id' => $apiToken->id,
                'token' => $validated['token'],
                'provider' => $validated['provider'] ?? 'onesignal',
                'last_seen_at' => now(),
            ]);
        });

        return ApiResponse::success(['registered' => true], status: 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $deleted = DeviceToken::query()
            ->where('api_access_token_id', $request->attributes->get('api_access_token')->id)
            ->delete();

        return ApiResponse::success(['unregistered' => $deleted > 0]);
    }
}
