<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PwaFoundationContractTest extends TestCase
{
    public function test_service_worker_only_runtime_caches_public_static_assets(): void
    {
        $worker = file_get_contents(dirname(__DIR__, 2).'/public/sw.js');

        foreach (['/admin', '/user', '/accountant', '/api', '/device-token'] as $prefix) {
            self::assertStringContainsString("'{$prefix}'", $worker);
        }

        self::assertStringContainsString('isPrivateApplicationRequest(url)', $worker);
        self::assertStringContainsString('isStaticAsset(url)', $worker);
        self::assertStringContainsString("caches.match('/offline.html')", $worker);
        self::assertStringContainsString("request.method !== 'GET'", $worker);
        self::assertStringContainsString("event.data?.type === 'SKIP_WAITING'", $worker);
    }

    public function test_manifest_uses_only_the_text_based_svg_icon(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode(file_get_contents($root.'/public/manifest.webmanifest'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('standalone', $manifest['display']);
        self::assertSame('rtl', $manifest['dir']);
        self::assertSame('ar', $manifest['lang']);
        self::assertCount(1, $manifest['icons']);
        self::assertSame('/carled.svg', $manifest['icons'][0]['src']);
        self::assertSame('image/svg+xml', $manifest['icons'][0]['type']);
        self::assertFileExists($root.'/public/carled.svg');
        self::assertFileDoesNotExist($root.'/public/icons/carled-192.png');
        self::assertFileDoesNotExist($root.'/public/icons/carled-512.png');
    }

    public function test_main_layouts_expose_installable_metadata_without_inline_css(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'resources/views/welcome.blade.php',
            'resources/views/dashboard/app.blade.php',
            'resources/views/layouts/auth.blade.php',
        ] as $path) {
            $layout = file_get_contents($root.'/'.$path);
            self::assertStringContainsString('manifest.webmanifest', $layout);
            self::assertStringContainsString('carled.svg', $layout);
            self::assertStringNotContainsString('<style', $layout);
            self::assertStringNotContainsString('style="', $layout);
        }
    }

    public function test_app_context_is_session_scoped_and_requests_receive_trace_ids(): void
    {
        $root = dirname(__DIR__, 2);
        $context = file_get_contents($root.'/app/Http/Controllers/Api/AppContextController.php');
        $middleware = file_get_contents($root.'/app/Http/Middleware/AttachRequestId.php');
        $bootstrap = file_get_contents($root.'/bootstrap/app.php');
        $ownerRoutes = file_get_contents($root.'/routes/user.php');
        $accountantRoutes = file_get_contents($root.'/routes/accountant.php');

        self::assertStringContainsString("Auth::guard('accountant')->user()", $context);
        self::assertStringContainsString("Auth::guard('web')->user()", $context);
        self::assertStringContainsString("'offline_sensitive_mutations' => false", $context);
        self::assertStringContainsString("headers->set('X-Request-ID'", $middleware);
        self::assertStringContainsString('AttachRequestId::class', $bootstrap);
        self::assertStringContainsString("'/api/v1/app-context'", $ownerRoutes);
        self::assertStringContainsString("'/api/v1/app-context'", $accountantRoutes);
    }

    public function test_install_update_and_connection_experience_requires_user_actions(): void
    {
        $root = dirname(__DIR__, 2);
        $panel = file_get_contents($root.'/resources/views/components/pwa-install-panel.blade.php');
        $registration = file_get_contents($root.'/resources/js/features/pwa/register-service-worker.js');
        $layout = file_get_contents($root.'/resources/views/dashboard/app.blade.php');
        $welcome = file_get_contents($root.'/resources/views/welcome.blade.php');

        self::assertStringContainsString('<x-pwa-install-panel />', $layout);
        self::assertStringContainsString('<x-pwa-install-panel />', $welcome);
        self::assertStringContainsString('data-pwa-install', $panel);
        self::assertStringContainsString('data-pwa-update', $panel);
        self::assertStringNotContainsString('<style', $panel);
        self::assertStringNotContainsString('style="', $panel);
        self::assertStringContainsString("'beforeinstallprompt'", $registration);
        self::assertStringContainsString("'offline'", $registration);
        self::assertStringContainsString("'online'", $registration);
        self::assertStringContainsString("postMessage({ type: 'SKIP_WAITING' })", $registration);
    }

    public function test_https_deployment_check_validates_public_pwa_contract(): void
    {
        $root = dirname(__DIR__, 2);
        $package = json_decode(file_get_contents($root.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
        $checker = file_get_contents($root.'/scripts/check-pwa-deployment.mjs');
        $apache = file_get_contents($root.'/public/.htaccess');

        self::assertSame('node scripts/check-pwa-deployment.mjs', $package['scripts']['test:pwa:deployment']);
        self::assertStringContainsString("candidate.protocol !== 'https:'", $checker);
        self::assertStringContainsString("fetchPath('/manifest.webmanifest'", $checker);
        self::assertStringContainsString("fetchPath('/sw.js'", $checker);
        self::assertStringContainsString("fetchPath('/offline.html'", $checker);
        self::assertStringContainsString('AddType application/manifest+json .webmanifest', $apache);
        self::assertStringContainsString('no-cache, no-store, must-revalidate', $apache);
    }
}
