<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobileReportApiContractTest extends TestCase
{
    public function test_reports_reuse_canonical_services_and_signed_downloads(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/ReportController.php'));

        self::assertStringContainsString("prefix('reports')", $routes);
        self::assertStringContainsString('ability:reports:read', $routes);
        self::assertStringContainsString("middleware(['api.contract', 'signed', 'throttle:30,1'])", $routes);
        self::assertStringContainsString("middleware('idempotency')", $routes);
        self::assertStringContainsString('MonthlyStoreReportService', $controller);
        self::assertStringContainsString('StoreTransferReportService', $controller);
        self::assertStringContainsString('temporarySignedRoute', $controller);
        self::assertStringContainsString('report_download_ttl_minutes', $controller);
    }

    public function test_report_access_is_owner_scoped_online_only_and_bounded(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/ReportController.php'));
        $context = file_get_contents(base_path('app/Http/Controllers/Api/MobileContextController.php'));

        self::assertStringContainsString('OWNER_REQUIRED', $controller);
        self::assertStringContainsString("where('user_id', \$validated['owner_id'])", $controller);
        self::assertStringContainsString("where('status', 'active')", $controller);
        self::assertStringContainsString('diffInDays($to) > 366', $controller);
        self::assertStringContainsString("'report_signed_downloads' => true", $context);
        self::assertStringContainsString("'report_offline_cache' => false", $context);
    }
}
