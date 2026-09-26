<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PwaUpdateSafetyContractTest extends TestCase
{
    public function test_worker_reports_the_runtime_version_and_cache_identity(): void
    {
        $worker = file_get_contents(dirname(__DIR__, 2).'/public/sw.js');

        self::assertStringContainsString("event.data?.type === 'GET_VERSION'", $worker);
        self::assertStringContainsString('event.ports[0]?.postMessage', $worker);
        self::assertStringContainsString('version: WORKER_VERSION', $worker);
        self::assertStringContainsString('cache: CACHE_VERSION', $worker);
    }

    public function test_update_activation_is_bounded_and_blocked_when_preparation_fails(): void
    {
        $registration = file_get_contents(dirname(__DIR__, 2).'/resources/js/features/pwa/register-service-worker.js');

        self::assertStringContainsString('UPDATE_PREPARATION_TIMEOUT', $registration);
        self::assertStringContainsString('Promise.allSettled', $registration);
        self::assertStringContainsString("status === 'rejected'", $registration);
        self::assertStringContainsString("announceStatus('update-blocked'", $registration);
        self::assertStringContainsString("waitingWorker.postMessage({ type: 'SKIP_WAITING' })", $registration);
    }

    public function test_registration_detects_a_worker_build_mismatch(): void
    {
        $registration = file_get_contents(dirname(__DIR__, 2).'/resources/js/features/pwa/register-service-worker.js');

        self::assertStringContainsString("type: 'GET_VERSION'", $registration);
        self::assertStringContainsString('deployedVersion.version !== buildVersion', $registration);
        self::assertStringContainsString('await activeRegistration.update()', $registration);
    }
}
