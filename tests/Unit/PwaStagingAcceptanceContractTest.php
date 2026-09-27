<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PwaStagingAcceptanceContractTest extends TestCase
{
    public function test_staging_gate_never_treats_missing_evidence_as_success(): void
    {
        $contract = json_decode(file_get_contents(base_path('config/pwa_acceptance.json')), true, 512, JSON_THROW_ON_ERROR);
        $runner = file_get_contents(base_path('scripts/run-pwa-staging-acceptance.mjs'));

        self::assertSame(1, $contract['schema_version']);
        self::assertContains('deployment', $contract['required_automated_gates']);
        self::assertContains('browser', $contract['required_automated_gates']);
        self::assertArrayHasKey('push_delivery_and_click', $contract['required_manual_gates']);
        self::assertArrayHasKey('account_switch_isolation', $contract['required_manual_gates']);
        self::assertContains('p50_p95_p99', $contract['provisional_slo_evidence']);
        self::assertStringContainsString("status: process.env[environment] === 'pass' ? 'PASS' : 'PENDING'", $runner);
        self::assertStringContainsString("Decision: **\${go ? 'GO' : 'NO-GO'}**", $runner);
        self::assertStringContainsString("process.exit(go ? 0 : 1)", $runner);
    }
}
