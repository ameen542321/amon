<?php

namespace App\Http\Middleware;

use App\Models\Accountant;
use App\Models\ApiIdempotencyKey;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureIdempotentRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) ($request->header('Idempotency-Key') ?: $request->input('_idempotency_key')));
        if (!preg_match('/\A[A-Za-z0-9._:-]{8,255}\z/', $key)) {
            return $this->error('يلزم إرسال Idempotency-Key صالح بطول 8 إلى 255 حرفًا.', 422);
        }

        $actor = $this->actor($request);
        if (!$actor) {
            return $this->error('انتهت جلسة الحساب أو أنها غير صالحة.', 401);
        }

        $identity = $this->identity($actor);
        $keyHash = hash('sha256', $key);
        $requestHash = $this->requestHash($request);
        $record = $this->claim($identity, $keyHash, $requestHash, $request);

        if (!$record->wasRecentlyCreated) {
            return $this->replayOrReject($record, $requestHash);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $record->delete();
            throw $exception;
        }

        if ($response->getStatusCode() >= 500 || strlen($response->getContent() ?: '') > config('idempotency.max_response_bytes')) {
            $record->delete();

            return $response;
        }

        $record->forceFill([
            'status' => 'completed',
            'response_status' => $response->getStatusCode(),
            'response_headers' => $this->replayableHeaders($response),
            'response_body' => $response->getContent() ?: '',
        ])->save();

        $response->headers->set('Idempotency-Key', $key);
        $response->headers->set('X-Idempotent-Replayed', 'false');

        return $response;
    }

    private function claim(array $identity, string $keyHash, string $requestHash, Request $request): ApiIdempotencyKey
    {
        try {
            return ApiIdempotencyKey::query()->create([
                ...$identity,
                'key_hash' => $keyHash,
                'request_hash' => $requestHash,
                'route_name' => $request->route()?->getName() ?? $request->path(),
                'status' => 'processing',
                'expires_at' => now()->addHours(max(1, config('idempotency.retention_hours'))),
            ]);
        } catch (QueryException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $record = ApiIdempotencyKey::query()
                ->where($identity)
                ->where('key_hash', $keyHash)
                ->firstOrFail();

            if ($record->expires_at->isPast()) {
                $record->delete();

                return $this->claim($identity, $keyHash, $requestHash, $request);
            }

            $staleBefore = now()->subMinutes(max(1, config('idempotency.processing_timeout_minutes')));
            if ($record->status === 'processing'
                && hash_equals($record->request_hash, $requestHash)
                && $record->updated_at->lessThanOrEqualTo($staleBefore)) {
                $claimed = ApiIdempotencyKey::query()
                    ->whereKey($record->id)
                    ->where('status', 'processing')
                    ->where('updated_at', $record->updated_at)
                    ->update([
                        'updated_at' => now(),
                        'expires_at' => now()->addHours(max(1, config('idempotency.retention_hours'))),
                    ]);

                if ($claimed === 1) {
                    $record->refresh();
                    $record->wasRecentlyCreated = true;
                }
            }

            return $record;
        }
    }

    private function replayOrReject(ApiIdempotencyKey $record, string $requestHash): Response
    {
        if (!hash_equals($record->request_hash, $requestHash)) {
            return $this->error('استُخدم Idempotency-Key نفسه مع طلب مختلف.', 422);
        }

        if ($record->status !== 'completed') {
            return $this->error('الطلب المطابق ما زال قيد التنفيذ. حاول قراءة النتيجة بعد لحظات.', 409)
                ->header('Retry-After', '2');
        }

        return response($record->response_body ?? '', $record->response_status ?? 200, $record->response_headers ?? [])
            ->header('X-Idempotent-Replayed', 'true');
    }

    private function requestHash(Request $request): string
    {
        $payload = $request->except(['_token', '_idempotency_key']);
        $this->sortRecursively($payload);

        return hash('sha256', json_encode([
            'method' => $request->getMethod(),
            'path' => '/'.$request->path(),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function sortRecursively(array &$payload): void
    {
        ksort($payload);
        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sortRecursively($value);
            }
        }
    }

    private function replayableHeaders(Response $response): array
    {
        return collect(['Content-Type', 'Cache-Control', 'X-Content-Type-Options'])
            ->mapWithKeys(fn (string $name): array => $response->headers->has($name)
                ? [$name => $response->headers->get($name)]
                : [])
            ->all();
    }

    private function actor(Request $request): ?Authenticatable
    {
        return $request->attributes->get('api_actor')
            ?? Auth::guard('accountant')->user()
            ?? Auth::guard('web')->user();
    }

    private function identity(Authenticatable $actor): array
    {
        return [
            'actor_type' => $actor instanceof Accountant ? 'accountant' : ($actor instanceof User ? 'user' : 'unknown'),
            'actor_id' => (int) $actor->getAuthIdentifier(),
        ];
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505', '19'], true);
    }

    private function error(string $message, int $status): Response
    {
        return ApiResponse::error('IDEMPOTENCY_ERROR', $message, $status);
    }
}
