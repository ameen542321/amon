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
    }

    public function test_store_guard_uses_the_accountant_guard_user(): void
    {
        $middleware = file_get_contents(dirname(__DIR__, 2).'/app/Http/Middleware/UnifiedStoreGuard.php');

        self::assertStringContainsString("auth()->guard('accountant')->user()?->store_id", $middleware);
        self::assertStringNotContainsString("\$store->id === auth()->user()->store_id", $middleware);
    }
}
