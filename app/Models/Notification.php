<?php

namespace App\Models;

use App\Support\Notifications\NotificationRecipient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    protected $fillable = [
        'sender_id',
        'sender_type',
        'target_type',
        'target_ids',
        'title',
        'message',
        'data',
        'template_key',
        'channel',
        'read_by',
    ];

    protected $casts = [
        'target_ids' => 'array',
        'read_by'    => 'array',
        'data' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | دوال الفلترة (Scopes) - لضمان دقة البيانات
    |--------------------------------------------------------------------------
    */
// داخل ملف Notification.php

    /**
     * حصر الاستعلام في الإشعارات التي تخص هوية المستلم ونوع حسابه.
     */
    public function scopeVisibleTo(Builder $query, NotificationRecipient $recipient): Builder
    {
        return $query
            ->where(function (Builder $targetQuery) use ($recipient): void {
                // يبدأ بشرط مستحيل حتى تبقى فروع OR واضحة مهما كان نوع الحساب.
                $targetQuery->whereRaw('1 = 0');

                if ($recipient->receivesBroadcastNotifications()) {
                    $targetQuery->orWhere('target_type', 'all');
                }

                if ($recipient->type === 'accountant') {
                    $targetQuery->orWhere('target_type', 'all_accountants')
                        ->orWhere(function (Builder $accountantQuery) use ($recipient): void {
                            $accountantQuery->whereIn('target_type', ['accountant', 'accountants'])
                                ->where(fn (Builder $ids) => $this->whereJsonId($ids, $recipient->id));
                        });

                    return;
                }

                $targetQuery->orWhere(function (Builder $userQuery) use ($recipient): void {
                    $userQuery->whereIn('target_type', ['user', 'users'])
                        ->where(fn (Builder $ids) => $this->whereJsonId($ids, $recipient->id));
                });

                if ($recipient->storeIds !== []) {
                    $targetQuery->orWhere(function (Builder $storeQuery) use ($recipient): void {
                        $storeQuery->whereIn('target_type', ['store', 'stores'])
                            ->where(function (Builder $ids) use ($recipient): void {
                                foreach ($recipient->storeIds as $index => $storeId) {
                                    $method = $index === 0 ? 'whereJsonContains' : 'orWhereJsonContains';
                                    $ids->{$method}('target_ids', $storeId);
                                    $ids->orWhereJsonContains('target_ids', (string) $storeId);
                                }
                            });
                    });
                }
            })
            ->where(function (Builder $hiddenQuery) use ($recipient): void {
                $hiddenQuery->whereNull('read_by')
                    ->orWhereJsonDoesntContain('read_by', $recipient->hiddenMarker());
            });
    }

    private function whereJsonId(Builder $query, int $id): void
    {
        $query->whereJsonContains('target_ids', $id)
            ->orWhereJsonContains('target_ids', (string) $id);
    }

    public function isReadByRecipient(NotificationRecipient $recipient): bool
    {
        return in_array($recipient->readMarker(), $this->read_by ?? [], true);
    }

    public function markAsReadByRecipient(NotificationRecipient $recipient): self
    {
        $readBy = $this->read_by ?? [];

        if (!in_array($recipient->readMarker(), $readBy, true)) {
            $readBy[] = $recipient->readMarker();
            $this->update(['read_by' => array_values(array_unique($readBy))]);
        }

        return $this;
    }

    public function markAsUnreadByRecipient(NotificationRecipient $recipient): self
    {
        $readBy = array_values(array_filter(
            $this->read_by ?? [],
            static fn ($marker): bool => $marker !== $recipient->readMarker(),
        ));

        $this->update(['read_by' => $readBy]);

        return $this;
    }

    public function hideFromRecipient(NotificationRecipient $recipient): self
    {
        $readBy = $this->read_by ?? [];
        $readBy[] = $recipient->hiddenMarker();
        $this->update(['read_by' => array_values(array_unique($readBy))]);

        return $this;
    }


public static function cleanupOldNotifications($userId = null)
{
    // إذا لم يتم تمرير المعرف، نحاول جلب معرف المستخدم الحالي من الجلسة
    $userId = $userId ?? auth()->id();

    // إذا لم يكن هناك مستخدم مسجل دخول أصلاً، نخرج من الدالة لتجنب الأخطاء
    if (!$userId) {
        return false;
    }

    // جلب إعدادات المستخدم أو استخدام القيم الافتراضية
    $settings = \App\Models\UserSetting::where('user_id', $userId)->first();

    // عدد أيام الصلاحية (من الإعدادات أو 15 يوم افتراضياً)
    $days = $settings ? $settings->notifications_expiry : 15;

    return static::whereJsonContains('target_ids', (string)$userId)
                 ->where('created_at', '<', now()->subDays($days))
                 ->delete();
}

protected static function booted()
{
    static::retrieved(function ($notification) {
        // رفعنا الاحتمالية قليلاً (إلى 10%) لأن المدة قصيرة جداً
        // لضمان استجابة أسرع للحذف
        if (rand(1, 100) <= 10) {
            static::cleanupOldNotifications();
        }
    });
}
}
