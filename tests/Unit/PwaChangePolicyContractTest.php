<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PwaChangePolicyContractTest extends TestCase
{
    public function test_every_change_must_review_and_record_its_pwa_impact(): void
    {
        $agents = file_get_contents(base_path('AGENTS.md'));
        $policy = file_get_contents(base_path('docs/قاعدة-PWA-لكل-تغيير.md'));
        $package = file_get_contents(base_path('package.json'));

        self::assertStringContainsString('قاعدة PWA الإلزامية لكل تغيير', $agents);
        self::assertStringContainsString('ينطبق', $agents);
        self::assertStringContainsString('لا ينطبق', $agents);
        self::assertStringContainsString('كل إضافة أو تعديل أو إصلاح', $policy);
        self::assertStringContainsString('Online-only', $policy);
        self::assertStringContainsString('تبديل الحساب', $policy);
        self::assertStringContainsString('test:pwa:change-policy', $package);
    }
}
