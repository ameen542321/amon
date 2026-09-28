<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PwaPerformanceBaselineContractTest extends TestCase
{
    public function test_staging_baseline_measures_without_claiming_an_slo(): void
    {
        $script = file_get_contents(base_path('scripts/measure-pwa-staging-performance.mjs'));
        $acceptance = json_decode(file_get_contents(base_path('config/pwa_acceptance.json')), true, 512, JSON_THROW_ON_ERROR);

        self::assertContains('performance_baseline', $acceptance['required_automated_gates']);
        self::assertStringContainsString("cache: 'no-store'", $script);
        self::assertStringContainsString('p50_ms', $script);
        self::assertStringContainsString('p95_ms', $script);
        self::assertStringContainsString('p99_ms', $script);
        self::assertStringContainsString('error_rate', $script);
        self::assertStringContainsString('javascript_bytes', $script);
        self::assertStringContainsString('css_bytes', $script);
        self::assertStringContainsString('not an approved production SLO', $script);
    }
}
