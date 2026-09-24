<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AuthAccessSecurityContractTest extends TestCase
{
    public function test_sensitive_notification_and_support_routes_require_admin_access(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');

        self::assertMatchesRegularExpression("/Route::get\('\/notifications\/push'.*?->middleware\(\['auth:web', 'is.admin'\]\)/s", $routes);
        self::assertMatchesRegularExpression("/Route::post\('\/notifications\/push'.*?->middleware\(\['auth:web', 'is.admin', 'throttle:10,1'\]\)/s", $routes);
        self::assertMatchesRegularExpression("/Route::post\('\/support-session\/stop'.*?->middleware\(\['auth:web', 'is.admin'\]\)/s", $routes);
        self::assertMatchesRegularExpression("/Route::patch\('\/support-archive\/\{archive\}\/message'.*?->middleware\(\['auth:web', 'is.admin'\]\)/s", $routes);
        self::assertMatchesRegularExpression("/Route::get\('\/notifications\/send'.*?->middleware\(\['auth:web', 'is.admin'\]\)/s", $routes);
    }

    public function test_account_lookup_and_report_routes_are_not_public(): void
    {
        $ownerRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/user.php');
        $accountantRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/accountant.php');

        self::assertMatchesRegularExpression("/employees\/check-email'.*?->middleware\(\['owner.unified', 'throttle:20,1'\]\)/s", $ownerRoutes);
        self::assertMatchesRegularExpression("/view-report\/\{filename\}.*?->middleware\('accountant.unified'\)/s", $accountantRoutes);
    }

    public function test_password_reset_response_does_not_enumerate_users(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Auth/ForgotPasswordController.php');
        $webRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        self::assertStringNotContainsString('لا يوجد مستخدم مسجل بهذا البريد', $controller);
        self::assertStringContainsString('إذا كان البريد مسجلاً فسيصلك رابط إعادة تعيين كلمة المرور.', $controller);
        self::assertMatchesRegularExpression("/forgot-password'.*?->middleware\('throttle:5,1'\)/s", $webRoutes);
        self::assertMatchesRegularExpression("/reset-password'.*?->middleware\('throttle:5,1'\)/s", $webRoutes);
    }

    public function test_store_guard_uses_the_accountant_guard_user(): void
    {
        $middleware = file_get_contents(dirname(__DIR__, 2).'/app/Http/Middleware/UnifiedStoreGuard.php');

        self::assertStringContainsString("auth()->guard('accountant')->user()?->store_id", $middleware);
        self::assertStringNotContainsString("\$store->id === auth()->user()->store_id", $middleware);
    }

    public function test_accountant_guard_fails_closed_for_orphaned_accounts(): void
    {
        $middleware = file_get_contents(dirname(__DIR__, 2).'/app/Http/Middleware/UnifiedAccountantGuard.php');

        self::assertStringContainsString("loadMissing(['user', 'store'])", $middleware);
        self::assertStringContainsString('if (! $accountant->user || ! $accountant->store)', $middleware);
    }

    public function test_password_brokers_match_the_existing_reset_table(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/config/auth.php');

        self::assertSame(2, substr_count($config, "'table' => 'password_resets'"));
        self::assertStringNotContainsString("'table' => 'password_reset_tokens'", $config);
    }

    public function test_duplicate_commented_login_implementation_is_removed(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Auth/LoginController.php');

        self::assertSame(1, substr_count($controller, 'public function login(Request $request)'));
    }

    public function test_login_does_not_reveal_account_status_before_authentication(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Auth/LoginController.php');
        $attemptPosition = strpos($controller, "Auth::guard('accountant')->attempt");
        $statusPosition = strpos($controller, "\$user->status !== 'active'");

        self::assertNotFalse($attemptPosition);
        self::assertNotFalse($statusPosition);
        self::assertGreaterThan($attemptPosition, $statusPosition);
        self::assertStringNotContainsString("where('email', \$request->email)->first()", $controller);
    }

    public function test_authentication_views_use_shared_alerts_and_password_autocomplete(): void
    {
        $login = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/login.blade.php');
        $forgot = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/forgot.blade.php');
        $reset = file_get_contents(dirname(__DIR__, 2).'/resources/views/auth/reset.blade.php');
        $views = $login."\n".$forgot."\n".$reset;

        self::assertStringNotContainsString('ui-badge-danger', $views);
        self::assertStringNotContainsString('ui-badge-success', $views);
        self::assertStringContainsString('autocomplete="current-password"', $login);
        self::assertStringContainsString('autocomplete="new-password"', $reset);
        self::assertStringContainsString('auth-password-toggle', $reset);
        self::assertStringNotContainsString('<style', $views);
        self::assertStringNotContainsString('style="', $views);
    }

    public function test_legacy_auth_middleware_is_removed_after_guard_consolidation(): void
    {
        $bootstrap = file_get_contents(dirname(__DIR__, 2).'/bootstrap/app.php');
        $legacyClasses = [
            'CheckSubscriptionActive',
            'CheckStoreAccess',
            'CheckStoreStatus',
            'CheckUserSuspended',
            'SubscriptionWarning',
        ];

        foreach ($legacyClasses as $legacyClass) {
            self::assertFileDoesNotExist(dirname(__DIR__, 2)."/app/Http/Middleware/{$legacyClass}.php");
            self::assertStringNotContainsString($legacyClass.'::class', $bootstrap);
        }

        self::assertStringNotContainsString("'store.master'", $bootstrap);
        self::assertStringContainsString("'owner.unified'", $bootstrap);
        self::assertStringContainsString("'accountant.unified'", $bootstrap);
        self::assertStringContainsString("'store.check'", $bootstrap);
    }

    public function test_admin_routes_do_not_keep_duplicate_commented_route_definitions(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');

        self::assertStringNotContainsString("// Route::get('/notifications/send'", $routes);
        self::assertStringNotContainsString("// Route::get('/notifications/push'", $routes);
        self::assertStringNotContainsString("Route::prefix('admin')->middleware(['auth:web', 'is.admin'])->group(function () {\n\n});", $routes);
    }
}
