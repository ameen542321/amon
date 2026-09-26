<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CreditHealthCheckController;
use App\Http\Controllers\Admin\DebtHealthCheckController;
use App\Http\Controllers\Admin\PurchaseOrderHealthCheckController;
use App\Http\Controllers\Admin\PurchaseOrderManagementController;
use App\Http\Controllers\AdminOneSignalSettingsController;
use App\Http\Controllers\Notifications\AdminNotificationSendController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AdminPushNotificationController;
use App\Http\Controllers\Admin\SupportSessionController;
use App\Http\Controllers\Admin\SupportArchiveController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SupportActionController;
use App\Http\Controllers\Admin\SecurityCommandCenterController;
use App\Http\Controllers\Admin\NotificationOperationsController;



/*
|--------------------------------------------------------------------------
| مسارات المدير (Admin)
|--------------------------------------------------------------------------
| - جميع المسارات تبدأ بـ /admin
| - جميع المسارات تبدأ باسم admin.
| - جميع المسارات محمية بـ web + auth + is.admin
|--------------------------------------------------------------------------
*/
Route::get('/notifications/push', [AdminPushNotificationController::class, 'create'])
        ->middleware(['auth:web', 'is.admin'])
        ->name('admin.notifications.push');

    // تنفيذ الإرسال
    Route::post('/notifications/push', [AdminPushNotificationController::class, 'store'])
        ->middleware(['auth:web', 'is.admin', 'throttle:10,1'])
        ->name('admin.notifications.push.store');


Route::middleware(['web', 'auth', 'is.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | لوحة التحكم
        |--------------------------------------------------------------------------
        */
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard.index');

        Route::get('/security', [SecurityCommandCenterController::class, 'index'])->name('security.index');
        Route::get('/security/{securityEvent}', [SecurityCommandCenterController::class, 'show'])->name('security.show');
        Route::patch('/security/{securityEvent}/action', [SecurityCommandCenterController::class, 'action'])->name('security.action');
        Route::post('/security-maintenance/check', [SecurityCommandCenterController::class, 'runCheck'])->name('security.maintenance.check');
        Route::post('/security-maintenance/report', [SecurityCommandCenterController::class, 'runReport'])->name('security.maintenance.report');
        Route::post('/security-maintenance/cleanup-preview', [SecurityCommandCenterController::class, 'previewCleanup'])->name('security.maintenance.cleanup-preview');
        Route::delete('/security-maintenance/cleanup', [SecurityCommandCenterController::class, 'runCleanup'])->name('security.maintenance.cleanup');

        Route::get('/health/credit', [CreditHealthCheckController::class, 'index'])
            ->name('health.credit');

        Route::get('/health/debt', [DebtHealthCheckController::class, 'index'])
            ->name('health.debt');
        Route::get('/health/purchase-orders', [PurchaseOrderHealthCheckController::class, 'index'])
            ->name('health.purchase-orders');
        // متابعة الطلبيات وإعداد حدودها محصورة داخل مجموعة middleware الخاصة بالأدمن.
        Route::get('/purchase-orders', [PurchaseOrderManagementController::class, 'index'])->name('purchase-orders.index');
        Route::patch('/purchase-orders/limits/global', [PurchaseOrderManagementController::class, 'updateGlobalLimit'])->name('purchase-orders.limits.global');
        Route::patch('/purchase-orders/limits/stores', [PurchaseOrderManagementController::class, 'updateStoreLimit'])->name('purchase-orders.limits.store');

        /*
        |--------------------------------------------------------------------------
        | إدارة المستخدمين
        |--------------------------------------------------------------------------
        */
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('trash', [AdminUserController::class, 'trash'])->name('trash');
            Route::post('{id}/restore', [AdminUserController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [AdminUserController::class, 'forceDelete'])->name('force-delete');
            Route::patch('{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('toggleStatus');
        });

        Route::resource('users', AdminUserController::class)->except(['create']);

        Route::post('/support/owners/{user}', [SupportSessionController::class, 'owner'])
            ->name('support.owner.start');
        Route::post('/support/accountants/{accountant}', [SupportSessionController::class, 'accountant'])
            ->name('support.accountant.start');
        Route::get('/support-sessions', [SupportSessionController::class, 'index'])
            ->name('support.index');
        Route::get('/support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::get('/support-tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::post('/support-tickets/{ticket}/start', [SupportTicketController::class, 'start'])->name('support-tickets.start');
        Route::patch('/support-tickets/{ticket}/respond', [SupportTicketController::class, 'respond'])->name('support-tickets.respond');
        Route::patch('/support-tickets/{ticket}/close', [SupportTicketController::class, 'close'])->name('support-tickets.close');
        Route::patch('/support-tickets/{ticket}/reopen', [SupportTicketController::class, 'reopen'])->name('support-tickets.reopen');
        Route::delete('/support-tickets/{ticket}', [SupportTicketController::class, 'destroy'])->name('support-tickets.destroy');
        Route::get('/support-archive', [SupportArchiveController::class, 'index'])
            ->name('support.archive.index');
        Route::post('/support-archive/{archive}/review', [SupportArchiveController::class, 'review'])
            ->name('support.archive.review');
        Route::get('/support-actions', [SupportActionController::class, 'index'])->name('support-actions.index');
        Route::delete('/support-actions/tickets/{ticketId}/purge', [SupportActionController::class, 'purgeTicket'])
            ->name('support-actions.tickets.purge');
        Route::delete('/support-actions/sessions/{session}', [SupportActionController::class, 'destroySession'])
            ->name('support-actions.sessions.destroy');
        Route::delete('/support-actions/completed-items/{archive}', [SupportActionController::class, 'destroyCompletedItem'])
            ->name('support-actions.completed-items.destroy');

        /*
        |--------------------------------------------------------------------------
        | إدارة المتاجر (معلّقة حالياً)
        |--------------------------------------------------------------------------
        */
        // Route::resource('stores', AdminStoreController::class);
        // Route::post('stores/{store}/suspend', [AdminStoreController::class, 'suspend'])
        //     ->name('stores.suspend');
        // Route::post('stores/{store}/activate', [AdminStoreController::class, 'activate'])
        //     ->name('stores.activate');

        /*
        |--------------------------------------------------------------------------
        | الإشعارات (Notifications)
        |--------------------------------------------------------------------------
        */
        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');

        Route::get('/notifications/{id}', [NotificationController::class, 'show'])
            ->name('notifications.show');

        Route::post('/notifications/{id}/toggle', [NotificationController::class, 'toggle'])
            ->name('notifications.toggle');

        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
            ->name('notifications.read');

        Route::post('/notifications/read-all', [NotificationController::class, 'markAll'])
            ->name('notifications.markAll');

        Route::post('/notifications/mark-selected', [NotificationController::class, 'markSelected'])
            ->name('notifications.markSelected');

        Route::delete('/notifications/{id}', [NotificationController::class, 'delete'])
            ->name('notifications.delete');

        Route::get('/notification-operations', [NotificationOperationsController::class, 'index'])
            ->name('notification-operations.index');
        Route::delete('/notification-operations/expired', [NotificationOperationsController::class, 'cleanup'])
            ->middleware('throttle:2,1')
            ->name('notification-operations.cleanup');
        Route::delete('/notification-operations/{notification}', [NotificationOperationsController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('notification-operations.destroy');

        /*
        |--------------------------------------------------------------------------
        | إعدادات OneSignal
        |--------------------------------------------------------------------------
        */
        Route::get('/onesignal', [AdminOneSignalSettingsController::class, 'index'])
            ->name('onesignal.index');

        Route::post('/onesignal', [AdminOneSignalSettingsController::class, 'update'])
            ->name('onesignal.update');

        Route::post('/onesignal/test', [AdminOneSignalSettingsController::class, 'test'])
            ->name('onesignal.test');
    });

Route::post('/support-session/stop', [SupportSessionController::class, 'stop'])
    ->middleware(['auth:web', 'is.admin'])
    ->name('admin.support.stop');
Route::patch('/support-archive/{archive}/message', [SupportArchiveController::class, 'message'])
    ->middleware(['auth:web', 'is.admin'])
    ->name('admin.support.archive.message');

Route::get('/notifications/send', [AdminNotificationSendController::class, 'create'])
    ->middleware(['auth:web', 'is.admin'])
    ->name('notifications.internal.send');

Route::post('/notifications/send', [AdminNotificationSendController::class, 'store'])
    ->middleware(['auth:web', 'is.admin', 'throttle:10,1'])
    ->name('notifications.internal.send.store');
