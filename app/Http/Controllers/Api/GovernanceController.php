<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\ApiAccessToken;
use App\Models\Log;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernanceController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $owner->loadMissing('plan:id,name,allowed_stores,allowed_accountants');
        $subscription = $this->activeSubscription($owner);
        $activeSessions = $this->sessionsQuery($owner)->whereNull('revoked_at')->where('expires_at', '>', now())->count();

        return ApiResponse::success([
            'account' => [
                'id' => (int) $owner->id,
                'name' => (string) $owner->name,
                'status' => (string) $owner->status,
                'subscription_active' => $owner->isSubscriptionActive(),
                'subscription_end_at' => $owner->subscription_end_at?->toDateString(),
            ],
            'plan' => [
                'id' => $owner->plan_id !== null ? (int) $owner->plan_id : null,
                'name' => $owner->plan?->name,
                'limits' => [
                    'stores' => (int) ($owner->allowed_stores ?? $owner->plan?->allowed_stores ?? 0),
                    'accountants' => (int) ($owner->allowed_accountants ?? $owner->plan?->allowed_accountants ?? 0),
                ],
                'usage' => [
                    'stores' => $owner->stores()->count(),
                    'active_stores' => $owner->stores()->where('status', 'active')->count(),
                    'accountants' => $owner->accountants()->count(),
                    'active_accountants' => $owner->accountants()->where('status', 'active')->count(),
                ],
            ],
            'current_subscription' => $subscription ? $this->subscription($subscription) : null,
            'security' => [
                'active_device_sessions' => $activeSessions,
                'revoked_device_sessions' => $this->sessionsQuery($owner)->whereNotNull('revoked_at')->count(),
                'last_login_at' => $owner->last_login_at?->toIso8601String(),
            ],
            'write_capabilities' => [
                'subscription' => false,
                'account_status' => false,
                'permissions' => false,
                'security_settings' => false,
            ],
        ]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $validated = $this->pageInput($request);
        $subscriptions = Subscription::query()->where('user_id', $owner->id)
            ->orderByDesc('id')->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($subscriptions->items())->map(fn (Subscription $subscription): array => $this->subscription($subscription))->values(),
            $this->pageMeta($subscriptions)
        );
    }

    public function sessions(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $validated = $this->pageInput($request);
        $sessions = $this->sessionsQuery($owner)->orderByDesc('id')->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($sessions->items())->map(fn (ApiAccessToken $token): array => [
                'id' => (int) $token->id,
                'device_uuid' => (string) $token->device_uuid,
                'device_name' => $token->device_name,
                'platform' => $token->platform,
                'app_version' => $token->app_version,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'revoked_at' => $token->revoked_at?->toIso8601String(),
                'current' => (int) $request->attributes->get('api_token')?->id === (int) $token->id,
            ])->values(),
            $this->pageMeta($sessions)
        );
    }

    public function audit(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $validated = $this->pageInput($request);
        $storeIds = $owner->stores()->pluck('id');
        $logs = Log::query()
            ->where(function ($query) use ($owner, $storeIds): void {
                $query->where('user_id', $owner->id)
                    ->orWhere(function ($actor) use ($owner): void {
                        $actor->where('actor_type', User::class)->where('actor_id', $owner->id);
                    })
                    ->orWhereIn('store_id', $storeIds);
            })
            ->orderByDesc('id')->cursorPaginate($validated['limit'] ?? 50);

        return ApiResponse::success(
            collect($logs->items())->map(fn (Log $log): array => [
                'id' => (int) $log->id,
                'store_id' => $log->store_id !== null ? (int) $log->store_id : null,
                'action' => (string) $log->action,
                'action_label' => $log->action_label,
                'subject_type' => $log->model_type ? class_basename($log->model_type) : null,
                'subject_id' => $log->model_id !== null ? (int) $log->model_id : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            $this->pageMeta($logs)
        );
    }

    private function owner(Request $request): User
    {
        $actor = $request->attributes->get('api_actor');
        if ($actor instanceof Accountant || ! ($actor instanceof User)) {
            abort(403, 'لوحة الحوكمة متاحة للمالك فقط.');
        }

        return $actor;
    }

    private function activeSubscription(User $owner): ?Subscription
    {
        return Subscription::query()->where('user_id', $owner->id)->where('status', 'active')
            ->whereDate('end_at', '>=', now()->toDateString())->latest('end_at')->first();
    }

    private function sessionsQuery(User $owner)
    {
        return ApiAccessToken::query()->where('actor_type', 'user')->where('actor_id', $owner->id);
    }

    private function subscription(Subscription $subscription): array
    {
        return [
            'id' => (int) $subscription->id,
            'type' => (string) $subscription->type,
            'price' => number_format((float) $subscription->price, 2, '.', ''),
            'status' => (string) $subscription->status,
            'start_at' => $subscription->start_at?->toDateString(),
            'end_at' => $subscription->end_at?->toDateString(),
            'created_at' => $subscription->created_at?->toIso8601String(),
        ];
    }

    private function pageInput(Request $request): array
    {
        return $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string'],
        ]);
    }

    private function pageMeta(mixed $paginator): array
    {
        return ['next_cursor' => $paginator->nextCursor()?->encode(), 'per_page' => $paginator->perPage()];
    }
}
