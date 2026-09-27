<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PwaOperationalControlsContractTest extends TestCase
{
    public function test_pwa_features_have_server_controlled_kill_switches(): void
    {
        $config = file_get_contents(base_path('config/pwa.php'));
        $registration = file_get_contents(base_path('resources/js/features/pwa/register-service-worker.js'));
        $sync = file_get_contents(base_path('resources/js/features/pwa/outbox-sync.js'));

        self::assertStringContainsString("'service_worker_enabled'", $config);
        self::assertStringContainsString("'updates_enabled'", $config);
        self::assertStringContainsString("'outbox_enabled'", $config);
        self::assertStringContainsString('registration.unregister()', $registration);
        self::assertStringContainsString('!pwaRuntimeConfig.updatesEnabled', $registration);
        self::assertStringContainsString('!pwaRuntimeConfig.outboxEnabled', $sync);
    }
}
