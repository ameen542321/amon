<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\Notifications\NotificationPayload;
use App\Support\Notifications\NotificationRecipient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string'],
        ]);
        $recipient = $this->recipient();
        $notifications = Notification::query()
            ->visibleTo($recipient)
            ->latest('id')
            ->cursorPaginate($validated['limit'] ?? 20);

        return $this->json([
            'data' => collect($notifications->items())
                ->map(fn (Notification $notification): array => $this->serialize($notification, $recipient))
                ->values(),
            'meta' => [
                'next_cursor' => $notifications->nextCursor()?->encode(),
                'per_page' => $notifications->perPage(),
            ],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $recipient = $this->recipient();
        $count = Notification::query()
            ->visibleTo($recipient)
            ->unreadByRecipient($recipient)
            ->count();

        return $this->json(['data' => ['count' => $count]]);
    }

    public function markRead(int $notification): JsonResponse
    {
        $recipient = $this->recipient();
        $this->notificationFor($recipient, $notification)->markAsReadByRecipient($recipient);

        return $this->json(['data' => ['read' => true]]);
    }

    public function hide(int $notification): JsonResponse
    {
        $recipient = $this->recipient();
        $this->notificationFor($recipient, $notification)->hideFromRecipient($recipient);

        return $this->json(null, 204);
    }

    private function serialize(Notification $notification, NotificationRecipient $recipient): array
    {
        return [
            'id' => (int) $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'sender_type' => $notification->sender_type,
            'channel' => $notification->channel,
            'read' => $notification->isReadByRecipient($recipient),
            'data' => NotificationPayload::normalize($notification->data ?? []),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function notificationFor(NotificationRecipient $recipient, int $notification): Notification
    {
        return Notification::query()->visibleTo($recipient)->findOrFail($notification);
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

    private function json(?array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->withHeaders([
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
