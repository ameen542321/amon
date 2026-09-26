<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Support\Notifications\NotificationRecipient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $recipient = $this->recipient();
        $notifications = Notification::query()
            ->visibleTo($recipient)
            ->latest()
            ->paginate(10);

        return view('notifications.index', compact('notifications', 'recipient'));
    }

    public function show(int $id): View
    {
        $recipient = $this->recipient();
        $notification = $this->notificationFor($recipient, $id);

        return view('notifications.show', compact('notification', 'recipient'));
    }

    public function open(int $notification): RedirectResponse
    {
        if (!$this->currentAccount()) {
            return redirect()->guest(route('login'));
        }

        $recipient = $this->recipient();
        $record = $this->notificationFor($recipient, $notification);
        $record->markAsReadByRecipient($recipient);

        return redirect()->route($this->showRouteName(), ['id' => $record->id]);
    }

    public function toggle(int $id): RedirectResponse
    {
        $recipient = $this->recipient();
        $notification = $this->notificationFor($recipient, $id);

        $notification->isReadByRecipient($recipient)
            ? $notification->markAsUnreadByRecipient($recipient)
            : $notification->markAsReadByRecipient($recipient);

        return back();
    }

    public function markAsRead(int $id): RedirectResponse
    {
        $recipient = $this->recipient();
        $this->notificationFor($recipient, $id)->markAsReadByRecipient($recipient);

        return back();
    }

    public function markAll(): RedirectResponse
    {
        $recipient = $this->recipient();

        Notification::query()->visibleTo($recipient)->eachById(
            static fn (Notification $notification) => $notification->markAsReadByRecipient($recipient),
        );

        return back();
    }

    public function markSelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer', 'distinct'],
        ]);
        $recipient = $this->recipient();

        Notification::query()
            ->visibleTo($recipient)
            ->whereKey($validated['selected'])
            ->eachById(static fn (Notification $notification) => $notification->markAsReadByRecipient($recipient));

        return back()->with('success', 'تم تعليم الإشعارات المحددة كمقروءة');
    }

    public function delete(int $id): RedirectResponse
    {
        $recipient = $this->recipient();
        $this->notificationFor($recipient, $id)->hideFromRecipient($recipient);

        return redirect()->route($this->indexRouteName());
    }

    private function notificationFor(NotificationRecipient $recipient, int $id): Notification
    {
        // استخدام الاستعلام نفسه في كل العمليات يمنع تعديل إشعار لا يخص الحساب.
        return Notification::query()->visibleTo($recipient)->findOrFail($id);
    }

    private function recipient(): NotificationRecipient
    {
        $account = $this->currentAccount();
        abort_unless($account, 401);

        return NotificationRecipient::fromAccount($account);
    }

    private function currentAccount(): ?Authenticatable
    {
        return Auth::guard('accountant')->user() ?? Auth::guard('web')->user();
    }

    private function showRouteName(): string
    {
        if (Auth::guard('accountant')->check()) {
            return 'accountant.notifications.show';
        }

        return Auth::guard('web')->user()?->isAdmin()
            ? 'admin.notifications.show'
            : 'user.notifications.show';
    }

    private function indexRouteName(): string
    {
        if (Auth::guard('accountant')->check()) {
            return 'accountant.notifications.index';
        }

        return Auth::guard('web')->user()?->isAdmin()
            ? 'admin.notifications.index'
            : 'user.notifications.index';
    }
}
