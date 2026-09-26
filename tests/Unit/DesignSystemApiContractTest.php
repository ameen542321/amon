<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DesignSystemApiContractTest extends TestCase
{
    public function test_contract_matches_central_visual_identity_assets(): void
    {
        $config = file_get_contents(base_path('config/design_system.php'));
        $css = file_get_contents(base_path('resources/css/app.css'));
        $manifest = file_get_contents(base_path('public/manifest.webmanifest'));

        self::assertStringContainsString("'default_theme' => 'dark'", $config);
        self::assertStringContainsString("'supported_themes' => ['dark', 'light']", $config);
        self::assertStringContainsString("'family' => 'Cairo'", $config);
        self::assertStringContainsString("'brand' => '#00C4B4'", $config);
        self::assertStringContainsString('--ui-brand: #00C4B4;', $css);
        self::assertStringContainsString('"theme_color": "#00C4B4"', $manifest);
    }

    public function test_api_contract_is_versioned_verifiable_and_read_only(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/DesignSystemController.php'));

        self::assertStringContainsString("Route::get('/design-system'", $routes);
        self::assertStringContainsString('ability:design-system:read', $routes);
        self::assertStringNotContainsString("Route::post('/design-system'", $routes);
        self::assertStringNotContainsString("Route::patch('/design-system'", $routes);
        self::assertStringContainsString("hash('sha256'", $controller);
        self::assertStringContainsString("->header('ETag'", $controller);
        self::assertStringContainsString("'publish' => false", $controller);
        self::assertStringContainsString("'rollback' => false", $controller);
    }
}
