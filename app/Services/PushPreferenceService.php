<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationPreference;

class PushPreferenceService
{
    public function category(array $data): string
    {
        if (in_array($data['category'] ?? null, ['announcements', 'messages', 'operations'], true)) {
            return $data['category'];
        }
        $notification = isset($data['notification_id']) ? Notification::find($data['notification_id']) : null;
        if ($notification?->template_key) {
            return 'operations';
        }

        return $notification && $notification->sender_type !== 'admin' ? 'messages' : 'announcements';
    }

    public function filter(array $tokens, array $data): array
    {
        $category = $this->category($data);
        $devices = DeviceToken::whereIn('token', $tokens)->get(['token', 'user_id', 'accountant_id']);
        $preferences = NotificationPreference::where(function ($query) use ($devices) {
            $query->where(fn ($q) => $q->where('actor_type', 'user')->whereIn('actor_id', $devices->pluck('user_id')->filter()))
                ->orWhere(fn ($q) => $q->where('actor_type', 'accountant')->whereIn('actor_id', $devices->pluck('accountant_id')->filter()));
        })->get()->keyBy(fn ($row) => $row->actor_type.':'.$row->actor_id);

        return $devices->filter(function ($device) use ($preferences, $category) {
            // أي هوية مرتبطة بالاشتراك عطلت النوع تمنع وصوله إلى الجهاز المشترك.
            foreach (['user', 'accountant'] as $type) {
                $id = $device->{$type.'_id'};
                if ($id && ($preferences->get($type.':'.$id)?->{$category}) === false) {
                    return false;
                }
            }

            return true;
        })->pluck('token')->unique()->values()->all();
    }
}
