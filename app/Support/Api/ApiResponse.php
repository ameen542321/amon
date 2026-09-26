<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponse
{
    public static function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => self::meta($meta),
        ], $status)->withHeaders(self::headers());
    }

    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
            'meta' => self::meta(),
        ], $status)->withHeaders(self::headers());
    }

    public static function noContent(): Response
    {
        return response('', 204, self::headers());
    }

    private static function meta(array $meta = []): array
    {
        return [
            ...$meta,
            'api_version' => 'v1',
            'request_id' => request()->attributes->get('request_id'),
        ];
    }

    private static function headers(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-API-Version' => 'v1',
        ];
    }
}
