<?php

namespace App\Http\Controllers;

use App\Jobs\SendOneSignalNotification;
use App\Models\Accountant;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Notifications\NotificationDeepLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPushNotificationController extends Controller
{
    public function create()
    {
        $users = User::users()->where('status', User::STATUS_ACTIVE)->get();
        $accountants = Accountant::where('status', 'active')->get();

        return view('admin.notifications.push', compact('users', 'accountants'));
    }

    public function store(Request $request)
    {
        // فك JSON لو وصل كسلسلة
        if (is_string($request->target_ids)) {
            $decoded = json_decode($request->target_ids, true);
            $request->merge([
                'target_ids' => is_array($decoded) ? $decoded : [],
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
            $targetIds = User::users()->where('status', User::STATUS_ACTIVE)
                ->whereKey($targetIds)->pluck('id');
        }

        if ($request->target_type === 'accountants') {
            $targetIds = Accountant::where('status', 'active')
                ->whereKey($targetIds)->pluck('id');
        }

        if ($request->target_type !== 'all' && $targetIds->isEmpty()) {
            return back()->withErrors(['target_ids' => 'اختر مستلمًا فعالًا واحدًا على الأقل.'])->withInput();
        }

        // لا تشمل قائمة all المدير أو الحسابات الموقوفة وفق المتطلبات المعتمدة.
        $deviceTokens = collect();

        if ($request->target_type === 'all') {
            $deviceTokens = DB::table('device_tokens')
                ->leftJoin('users', 'users.id', '=', 'device_tokens.user_id')
                ->leftJoin('accountants', 'accountants.id', '=', 'device_tokens.accountant_id')
                ->where(function ($query): void {
                    $query->where(function ($users): void {
                        $users->where('users.role', User::ROLE_USER)
                            ->where('users.status', User::STATUS_ACTIVE)
                            ->whereNull('users.deleted_at');
                    })->orWhere(function ($accountants): void {
                        $accountants->where('accountants.status', 'active');
                    });
                })
                ->pluck('device_tokens.token');
        }

        if ($request->target_type === 'users') {
            $deviceTokens = DB::table('device_tokens')
                ->whereIn('user_id', $targetIds)
                ->pluck('token');
        }

        if ($request->target_type === 'accountants') {
            $deviceTokens = DB::table('device_tokens')
                ->whereIn('accountant_id', $targetIds)
                ->pluck('token');
        }

        // ينشأ Inbox أولًا ليكون هو المصدر، ثم يحمل Push رابطًا إلى السجل نفسه.
        $notification = NotificationService::send([
            'sender_type' => 'admin',
            'target_type' => $request->target_type,
            'target_ids'  => $targetIds->all(),
            'title'       => $request->title,
            'message'     => $request->message,
        ]);
        $deepLink = NotificationDeepLink::payload($notification);
        $notification->forceFill(['data' => $deepLink])->save();

        // كل دفعة تصبح Job مستقلة حتى لا يطول طلب الويب ولا يكبر payload المزود.
        $deviceTokens->unique()->values()->chunk(1000)->each(function ($tokens) use ($request, $deepLink): void {
            SendOneSignalNotification::dispatch(
                $tokens->all(),
                $request->title,
                $request->message,
                $deepLink,
            );
        });

        return back()->with('success', 'تم حفظ الإشعار الداخلي وإضافة Push إلى قائمة الإرسال.');
    }
}
