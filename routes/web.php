<?php

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;
use App\Http\Controllers\OneSignalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Cashier\InvoiceController;
use App\Http\Controllers\Cashier\QuickSaleController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;


Route::middleware('web')->group(function () {

    require base_path('routes/admin.php');
    require base_path('routes/user.php');
     require base_path('routes/accountant.php');

    Route::get('/notifications/open/{notification}', [NotificationController::class, 'open'])
        ->whereNumber('notification')
        ->middleware('throttle:120,1')
        ->name('notifications.open');


    /*
    |--------------------------------------------------------------------------
    | صفحة الترحيب المشتركة
    |--------------------------------------------------------------------------
    */
    Route::post('/welcome/continue', function () {
        $user = auth('web')->user(); // ← مهم جدًا

        $user->update(['welcome_shown' => true]);

        return match ($user->role) {
            'admin'      => redirect()->route('admin.dashboard.index'),
            'accountant' => redirect()->route('accountant.dashboard'),
            default      => redirect()->route('user.dashboard'),
        };
    })->middleware('auth:web')->name('welcome.continue');

    /*
    |--------------------------------------------------------------------------
    | سجل النشاطات
    |--------------------------------------------------------------------------
    */
    Route::get('/logs', [LogController::class, 'index'])
        ->name('user.logs.index')
        ->middleware('auth:web');

    /*
    |--------------------------------------------------------------------------
    | صفحات عامة
    |--------------------------------------------------------------------------
    */
    Route::view('/no-access', 'errors.no-access')->name('no.access');
    Route::view('/suspended', 'auth.suspended')->name('user.suspended');
    Route::view('/welcome-screen', 'auth.welcome-screen')->name('welcome.screen');
    /*
    |--------------------------------------------------------------------------
    | الصفحة الرئيسية
    |--------------------------------------------------------------------------
    */
    Route::get('/', fn() => view('welcome'));

    /*
    |--------------------------------------------------------------------------
    | مسارات الضيوف (web فقط)
    |--------------------------------------------------------------------------
    */
    Route::middleware('guest:web')->group(function () {

        Route::get('/login', [LoginController::class, 'showLoginForm'])
            ->name('login');

        Route::post('/login', [LoginController::class, 'login'])
            ->name('login.submit');

        Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])
            ->name('password.request');

        Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])
            ->middleware('throttle:5,1')
            ->name('password.email');

        Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])
            ->name('password.reset');

        Route::post('/reset-password', [ResetPasswordController::class, 'reset'])
            ->middleware('throttle:5,1')
            ->name('password.update');
    });

    /*
    |--------------------------------------------------------------------------
    | تسجيل الخروج (web فقط)
    |--------------------------------------------------------------------------
    */
    Route::post('/logout', [LoginController::class, 'logout'])
        ->middleware('auth:web')
        ->name('logout');

    /*
    |--------------------------------------------------------------------------
    | مسارات الاشتراك
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:web')->group(function () {

        Route::get('/subscription/expired', [SubscriptionController::class, 'expired'])
            ->name('subscription.expired');

        Route::get('/subscription/renew', [SubscriptionController::class, 'renew'])
            ->name('subscription.renew');

        Route::post('/subscription/renew', [SubscriptionController::class, 'processRenew'])
            ->name('subscription.processRenew');
    });

    /*
    |--------------------------------------------------------------------------
    | صفحات عامة إضافية
    |--------------------------------------------------------------------------
    */
    // Route::view('/products', 'products.index')->name('products.index');
    // Route::view('/categories', 'categories.index')->name('categories.index');
    // Route::view('/workers', 'workers.index')->name('workers.index');
    // Route::view('/salaries', 'salaries.index')->name('salaries.index');
    // Route::view('/subscriptions', 'subscriptions.index')->name('subscriptions.index');
});


Route::prefix('accountant')->group(function () {



    // صفحة الحساب الموقوف
    Route::get('/suspended', function () {
        return view('auth.suspended');
    })->name('accountant.suspended');

});

// مسار عام لمعاينة الفاتورة لا يتطلب guard معين
Route::get('/invoice/view/{id}', [InvoiceController::class, 'publicShow'])
     ->name('public.invoice.show');



Route::post('/device-token', [DeviceTokenController::class, 'store'])
    ->middleware(['web', 'throttle:20,1'])
    ->name('device.token.store');

// Route::get('/db-structure', function () {
//     $tables = DB::select('SHOW TABLES');
//     $structure = [];

//     foreach ($tables as $table) {
//         $tableName = current((array)$table);
//         $structure[$tableName] = DB::select("DESCRIBE $tableName");
//     }

//     return response()->json($structure);
// });



   Route::get('/reports/{filename}', function ($filename) {
    $path = public_path('reports/' . $filename);

    if (!file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $filename . '"'
    ]);
})->name('public.report.view');
