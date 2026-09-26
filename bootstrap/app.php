<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\AttachRequestId::class);


        // Web middleware group
        $middleware->group('web', [

            // الكوكيز
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,

            // احتواء المصادر المقيدة أمنيًا قبل تنفيذ طلبات التطبيق.
            \App\Http\Middleware\BlockSecurityThreats::class,

            // تفعيل الجلسة
            \Illuminate\Session\Middleware\StartSession::class,

            // منع تكرار نفس الطلب التعديلي خلال فترة قصيرة
            \App\Http\Middleware\PreventDuplicateRequest::class,

            // مشاركة الأخطاء
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,

            // حماية CSRF
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,

            // ربط الروتات (يجب أن يكون دائماً قبل حراس المتاجر)
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        // Aliases
        $middleware->alias([
            'is.admin'   => \App\Http\Middleware\IsAdmin::class,

            /* |--- 🛡️ الحراس الجدد (مدمجة ومنظمة) --- */


            'redirect.dashboard' => \App\Http\Middleware\RedirectIfAuthenticatedToDashboard::class,
            'no.access'          => \App\Http\Middleware\NoAccess::class,
            'plan.limit'         => \App\Http\Middleware\CheckPlanLimit::class,
            // الحراس النهائيين
            // حارس المالك
            'owner.unified' => \App\Http\Middleware\UnifiedOwnerGuard::class,
            // حارس المحاسب
            'accountant.unified' => \App\Http\Middleware\UnifiedAccountantGuard::class,

            // إعادة الطلبات API الحساسة بأمان عند ضعف الشبكة دون تكرار الأثر.
            'idempotency' => \App\Http\Middleware\EnsureIdempotentRequest::class,
            'api.contract' => \App\Http\Middleware\ApplyApiContract::class,
            'auth.api-token' => \App\Http\Middleware\AuthenticateApiToken::class,
            'ability' => \App\Http\Middleware\RequireApiAbility::class,

               'store.check' => \App\Http\Middleware\UnifiedStoreGuard::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $exception, \Illuminate\Http\Request $request) {
            if (!$request->is('api/v1/*', '*/api/v1/*')) {
                return null;
            }

            return \App\Support\Api\ApiResponse::error(
                'VALIDATION_FAILED',
                'تعذر قبول بيانات الطلب.',
                422,
                ['fields' => $exception->errors()],
            );
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $exception, \Illuminate\Http\Request $request) {
            return $request->is('api/v1/*', '*/api/v1/*')
                ? \App\Support\Api\ApiResponse::error('UNAUTHENTICATED', 'انتهت جلسة الحساب أو أنها غير صالحة.', 401)
                : null;
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $exception, \Illuminate\Http\Request $request) {
            return $request->is('api/v1/*', '*/api/v1/*')
                ? \App\Support\Api\ApiResponse::error('FORBIDDEN', 'لا يملك الحساب صلاحية تنفيذ هذا الطلب.', 403)
                : null;
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $exception, \Illuminate\Http\Request $request) {
            return $request->is('api/v1/*', '*/api/v1/*')
                ? \App\Support\Api\ApiResponse::error('NOT_FOUND', 'السجل المطلوب غير موجود أو غير متاح لهذا الحساب.', 404)
                : null;
        });

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $exception, \Illuminate\Http\Request $request) {
            return $request->is('api/v1/*', '*/api/v1/*')
                ? \App\Support\Api\ApiResponse::error('SESSION_EXPIRED', 'انتهت جلسة الحماية. حدّث الصفحة ثم أعد المحاولة.', 419)
                : null;
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception, \Illuminate\Http\Request $request) {
            if (!$request->is('api/v1/*', '*/api/v1/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $messages = [
                401 => ['UNAUTHENTICATED', 'انتهت جلسة الحساب أو أنها غير صالحة.'],
                403 => ['FORBIDDEN', 'لا يملك الحساب صلاحية تنفيذ هذا الطلب.'],
                404 => ['NOT_FOUND', 'السجل المطلوب غير موجود أو غير متاح لهذا الحساب.'],
                405 => ['METHOD_NOT_ALLOWED', 'طريقة الطلب غير مدعومة لهذا المسار.'],
                419 => ['SESSION_EXPIRED', 'انتهت جلسة الحماية. حدّث الصفحة ثم أعد المحاولة.'],
                429 => ['RATE_LIMITED', 'تم تجاوز الحد المؤقت للطلبات. حاول لاحقًا.'],
            ];

            if (!isset($messages[$status])) {
                return null;
            }

            return \App\Support\Api\ApiResponse::error($messages[$status][0], $messages[$status][1], $status);
        });

        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            if (!$request->is('api/v1/*', '*/api/v1/*')) {
                return null;
            }

            return \App\Support\Api\ApiResponse::error(
                'INTERNAL_ERROR',
                'تعذر إكمال الطلب بسبب خطأ داخلي. استخدم رقم التتبع عند التواصل مع الدعم.',
                500,
            );
        });

        $exceptions->report(function (\Throwable $exception) {
            if (app()->runningInConsole()) {
                return;
            }

            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('security_events')) {
                    app(\App\Services\SecurityEventService::class)->record(
                        'OPS.UNHANDLED_EXCEPTION',
                        'operations',
                        'high',
                        'سيدي، رصدنا خطأً غير معالج في التطبيق.',
                        [
                            'confidence' => 100,
                            'subject' => get_class($exception),
                            'evidence' => [
                                'exception' => get_class($exception),
                                'file' => basename($exception->getFile()),
                                'line' => $exception->getLine(),
                            ],
                        ]
                    );
                }
            } catch (\Throwable) {
                // لا يسمح لفشل الرصد بإخفاء الاستثناء الأصلي أو إنشاء حلقة تقارير.
            }
        });
    })

    ->create();
