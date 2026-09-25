<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MobileContextController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

// توافق مؤقت مع فحص الصحة القديم؛ لا يحمل بيانات ولا مصادقة.
Route::get('/ping', fn (): string => 'pong');

Route::prefix('v1')->name('api.v1.')->middleware('api.contract')->group(function (): void {
    Route::post('/auth/login', [AuthTokenController::class, 'login'])
        ->middleware('throttle:mobile-login')->name('auth.login');
    Route::post('/auth/refresh', [AuthTokenController::class, 'refresh'])
        ->middleware('throttle:mobile-refresh')->name('auth.refresh');

    Route::middleware(['auth.api-token', 'throttle:mobile-api'])->group(function (): void {
        Route::post('/auth/logout', [AuthTokenController::class, 'logout'])->name('auth.logout');
        Route::post('/auth/logout-all', [AuthTokenController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('/me', [MobileContextController::class, 'me'])->middleware('ability:app:read')->name('me');
        Route::get('/app-config', [MobileContextController::class, 'appConfig'])->middleware('ability:app:read')->name('app-config');
        Route::get('/stores', [MobileContextController::class, 'stores'])->middleware('ability:app:read')->name('stores');
        Route::prefix('catalog')->name('catalog.')->middleware('ability:catalog:read')->group(function (): void {
            Route::get('/categories', [CatalogController::class, 'categories'])->name('categories');
            Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
            Route::get('/products/{product}', [CatalogController::class, 'show'])->whereNumber('product')->name('products.show');
            Route::get('/sync', [CatalogController::class, 'sync'])->name('sync');
        });
        Route::prefix('inventory')->name('inventory.')->middleware('ability:inventory:read')->group(function (): void {
            Route::get('/summary', [InventoryController::class, 'summary'])->name('summary');
            Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
            Route::get('/integrity', [InventoryController::class, 'integrity'])->name('integrity');
        });
        Route::put('/push-subscription', [PushSubscriptionController::class, 'store'])
            ->middleware(['ability:push:manage', 'idempotency'])->name('push.store');
        Route::delete('/push-subscription', [PushSubscriptionController::class, 'destroy'])
            ->middleware(['ability:push:manage', 'idempotency'])->name('push.destroy');
        Route::get('/devices', [MobileContextController::class, 'devices'])->middleware('ability:devices:manage')->name('devices.index');
        Route::delete('/devices/{token}', [MobileContextController::class, 'revokeDevice'])
            ->middleware(['ability:devices:manage', 'idempotency'])->name('devices.destroy');

        Route::prefix('notifications')->name('notifications.')->middleware('ability:notifications:read')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])
                ->middleware(['ability:notifications:write', 'idempotency'])->name('read');
            Route::delete('/{notification}', [NotificationController::class, 'hide'])
                ->middleware(['ability:notifications:write', 'idempotency'])->name('hide');
        });
    });
});
