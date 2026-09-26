<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryCountController;
use App\Http\Controllers\Api\MobileContextController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PeopleController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StoreTransferController;
use App\Http\Controllers\Api\SupportingOperationController;
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
        Route::prefix('inventory-counts')->name('inventory-counts.')->middleware('ability:inventory-counts:read')->group(function (): void {
            Route::get('/', [InventoryCountController::class, 'index'])->name('index');
            Route::get('/{session}', [InventoryCountController::class, 'show'])->whereNumber('session')->name('show');
            Route::patch('/{session}/draft', [InventoryCountController::class, 'saveDraft'])
                ->whereNumber('session')
                ->middleware(['ability:inventory-counts:write', 'idempotency'])
                ->name('draft.save');
        });
        Route::prefix('people')->name('people.')->middleware('ability:people:read')->group(function (): void {
            Route::get('/summary', [PeopleController::class, 'summary'])->name('summary');
            Route::get('/employees', [PeopleController::class, 'employees'])->name('employees.index');
            Route::get('/employees/{employee}', [PeopleController::class, 'employee'])->whereNumber('employee')->name('employees.show');
            Route::get('/accountants', [PeopleController::class, 'accountants'])->name('accountants.index');
            Route::get('/accountants/{accountant}', [PeopleController::class, 'accountant'])->whereNumber('accountant')->name('accountants.show');
        });
        Route::prefix('operations')->name('operations.')->middleware('ability:operations:read')->group(function (): void {
            Route::get('/summary', [SupportingOperationController::class, 'summary'])->name('summary');
            Route::get('/expenses', [SupportingOperationController::class, 'expenses'])->name('expenses.index');
            Route::get('/expenses/{expense}', [SupportingOperationController::class, 'expense'])->whereNumber('expense')->name('expenses.show');
            Route::get('/internal-use', [SupportingOperationController::class, 'internalUses'])->name('internal-use.index');
            Route::get('/internal-use/{sale}', [SupportingOperationController::class, 'internalUse'])->whereNumber('sale')->name('internal-use.show');
            Route::get('/owner-purchases', [SupportingOperationController::class, 'ownerPurchases'])->name('owner-purchases.index');
        });
        Route::prefix('shifts')->name('shifts.')->middleware('ability:shifts:read')->group(function (): void {
            Route::get('/current', [ShiftController::class, 'current'])->name('current');
            Route::get('/history', [ShiftController::class, 'history'])->name('history');
            Route::get('/gaps', [ShiftController::class, 'gaps'])->name('gaps');
        });
        Route::prefix('sales')->name('sales.')->middleware('ability:sales:read')->group(function (): void {
            Route::get('/', [SaleController::class, 'index'])->name('index');
            Route::get('/summary', [SaleController::class, 'summary'])->name('summary');
            Route::get('/{sale}', [SaleController::class, 'show'])->whereNumber('sale')->name('show');
        });
        Route::prefix('purchase-orders')->name('purchase-orders.')->middleware('ability:purchase-orders:read')->group(function (): void {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
            Route::get('/summary', [PurchaseOrderController::class, 'summary'])->name('summary');
            Route::get('/{order}', [PurchaseOrderController::class, 'show'])->whereNumber('order')->name('show');
        });
        Route::prefix('store-transfers')->name('store-transfers.')->middleware('ability:store-transfers:read')->group(function (): void {
            Route::get('/', [StoreTransferController::class, 'index'])->name('index');
            Route::get('/summary', [StoreTransferController::class, 'summary'])->name('summary');
            Route::get('/{transfer}', [StoreTransferController::class, 'show'])->whereNumber('transfer')->name('show');
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
