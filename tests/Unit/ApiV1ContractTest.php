<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ApiV1ContractTest extends TestCase
{
    public function test_success_error_and_transport_contracts_are_versioned(): void
    {
        $root = dirname(__DIR__, 2);
        $response = file_get_contents($root.'/app/Support/Api/ApiResponse.php');
        $middleware = file_get_contents($root.'/app/Http/Middleware/ApplyApiContract.php');
        $bootstrap = file_get_contents($root.'/bootstrap/app.php');

        self::assertStringContainsString("'data' => \$data", $response);
        self::assertStringContainsString("'error' => [", $response);
        self::assertStringContainsString("'api_version' => 'v1'", $response);
        self::assertStringContainsString("'request_id' => request()->attributes->get('request_id')", $response);
        self::assertStringContainsString("'X-API-Version' => 'v1'", $response);
        self::assertStringContainsString("'X-Request-ID'", $middleware);
        self::assertStringContainsString('VALIDATION_FAILED', $bootstrap);
        self::assertStringContainsString('UNAUTHENTICATED', $bootstrap);
        self::assertStringContainsString('FORBIDDEN', $bootstrap);
        self::assertStringContainsString('NOT_FOUND', $bootstrap);
        self::assertStringContainsString('RATE_LIMITED', $bootstrap);
        self::assertStringContainsString('AuthenticationException', $bootstrap);
        self::assertStringContainsString('AuthorizationException', $bootstrap);
        self::assertStringContainsString('ModelNotFoundException', $bootstrap);
        self::assertStringContainsString('TokenMismatchException', $bootstrap);
        self::assertStringContainsString("is('api/v1/*', '*/api/v1/*')", $bootstrap);
    }

    public function test_browser_client_preserves_idempotency_and_never_claims_offline_success(): void
    {
        $root = dirname(__DIR__, 2);
        $client = file_get_contents($root.'/resources/js/features/pwa/api-client.js');

        self::assertStringContainsString("headers.set('Idempotency-Key', options.idempotencyKey)", $client);
        self::assertStringContainsString("credentials: 'same-origin'", $client);
        self::assertStringContainsString("code: 'REQUEST_TIMEOUT'", $client);
        self::assertStringContainsString("code: 'NETWORK_ERROR'", $client);
        self::assertStringContainsString("response.headers.get('X-Idempotent-Replayed') === 'true'", $client);
        self::assertStringNotContainsString('navigator.serviceWorker', $client);
        self::assertStringNotContainsString('caches.open', $client);
    }
}
