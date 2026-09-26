<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Accountant;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AdminNotificationSendController extends Controller
{
    public function create()
    {
        $users = User::users()->where('status', User::STATUS_ACTIVE)->get();
        $accountants = Accountant::where('status', 'active')->get();

        return view('admin.notifications.send', compact('users', 'accountants'));
    }

    public function store(Request $request)
    {
        // فك JSON لو وصل كسلسلة
        if ($request->filled('target_ids') && is_string($request->target_ids)) {
            $decoded = json_decode($request->target_ids, true);
            $request->merge([
                'target_ids' => is_array($decoded) ? $decoded : null,
            ]);
        }

        $request->validate([
            'target_type' => 'required|in:all,users,accountants',
            'target_ids'  => 'nullable|array',
            'title'       => 'required|string|max:255',
            'message'     => 'required|string|max:2000',
        ]);

        $targetIds = collect($request->input('target_ids', []))
            ->map(static fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($request->target_type === 'users') {
            $targetIds = User::users()
                ->where('status', User::STATUS_ACTIVE)
                ->whereKey($targetIds)
                ->pluck('id');
        }

        if ($request->target_type === 'accountants') {
            $targetIds = Accountant::where('status', 'active')
                ->whereKey($targetIds)
                ->pluck('id');
        }

        if ($request->target_type !== 'all' && $targetIds->isEmpty()) {
            return back()->withErrors([
                'target_ids' => 'اختر مستلمًا فعالًا واحدًا على الأقل.',
            ])->withInput();
        }

        NotificationService::send([
            'sender_type' => 'admin',
            'target_type' => $request->target_type,
            'target_ids'  => $targetIds->all(),
            'title'       => $request->title,
            'message'     => $request->message,
        ]);

        return redirect()
            ->route('notifications.internal.send')
            ->with('success', 'تم إرسال الإشعار بنجاح');
    }
}
