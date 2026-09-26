<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RoadmapScopeContractTest extends TestCase
{
    public function test_current_scope_completes_pwa_without_starting_flutter(): void
    {
        $roadmap = file_get_contents(base_path('docs/خارطة-طريق-PWA-Flutter.md'));
        $scope = file_get_contents(base_path('docs/roadmap-current-scope.md'));

        self::assertStringContainsString('**المسار النشط:** إكمال PWA', $roadmap);
        self::assertStringContainsString('# المرحلة 8: Flutter MVP — مؤجلة بقرار النطاق', $roadmap);
        self::assertStringContainsString('لا تعد هذه المرحلة شرطًا لإطلاق PWA', $roadmap);
        self::assertStringContainsString('Outbox محدود لمسودة الجرد فقط', $roadmap);
        self::assertStringContainsString('لا ينشأ حاليًا `pubspec.yaml`', $scope);
        self::assertStringContainsString('## تعريف اكتمال PWA', $scope);
        self::assertStringContainsString('أي Offline لعملية مالية أو اعتماد مخزني', $scope);
    }
}
