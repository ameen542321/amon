<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationOperationsController extends Controller
{
    public function index(NotificationMaintenanceService $maintenance): View
    {
        $summary = [
            'total' => Notification::query()->count(),
            'today' => Notification::query()->whereDate('created_at', today())->count(),
            'expired' => $maintenance->expiredCount(),
            'site' => Notification::query()->where('channel', 'site')->count(),
            'push' => Notification::query()->whereIn('channel', ['push', 'both'])->count(),
        ];

        $targetCounts = Notification::query()
            ->selectRaw('target_type, COUNT(*) as aggregate')
            ->groupBy('target_type')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'target_type');

        $notifications = Notification::query()->latest()->paginate(20);

        return view('admin.notifications.operations', compact('summary', 'targetCounts', 'notifications'));
    }

    public function cleanup(NotificationMaintenanceService $maintenance): RedirectResponse
    {
        $deleted = $maintenance->deleteExpired();

        return back()->with('success', "تم حذف {$deleted} إشعارًا منتهيًا فقط.");
    }

    public function destroy(Notification $notification): RedirectResponse
    {
        $notification->delete();

        return back()->with('success', 'تم حذف الإشعار المحدد نهائيًا لجميع المستلمين.');
    }
}
