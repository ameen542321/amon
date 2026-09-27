<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class MobileShiftApiContractTest extends TestCase
{
    public function test_shift_api_is_read_only_and_business_date_aware(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/api.php');
        $controller = file_get_contents($root.'/app/Http/Controllers/Api/ShiftController.php');
        $lifecycle = file_get_contents($root.'/app/Services/ShiftLifecycleService.php');
        self::assertStringContainsString("prefix('shifts')", $routes);
        self::assertStringContainsString('ability:shifts:read', $routes);
        self::assertStringNotContainsString("Route::post('/shifts", $routes);
        self::assertStringContainsString('currentShiftContext', $controller);
        self::assertStringContainsString('missingBusinessDates', $controller);
        self::assertStringContainsString("whereDate('business_date'", $controller);
        self::assertStringContainsString("request()->hasSession()", $lifecycle);
    }
}
