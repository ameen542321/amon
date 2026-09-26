<?php

namespace App\Support\Notifications;

use App\Models\Accountant;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * هوية مستلم مستقلة عن رقم السجل لمنع تصادم User وAccountant المتساويين رقمياً.
 */
final readonly class NotificationRecipient
{
    private function __construct(
        public string $type,
        public int $id,
        public array $storeIds = [],
        public ?string $role = null,
    ) {}

    public static function fromAccount(Authenticatable $account): self
    {
        if ($account instanceof Accountant) {
            return new self('accountant', (int) $account->getAuthIdentifier());
        }

        if ($account instanceof User) {
            $storeIds = $account->isUser()
                ? $account->stores()->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                : [];

            return new self('user', (int) $account->getAuthIdentifier(), $storeIds, $account->role);
        }

        throw new InvalidArgumentException('نوع حساب مستلم الإشعار غير مدعوم.');
    }

    public function readMarker(): string
    {
        return "{$this->type}:{$this->id}";
    }

    public function hiddenMarker(): string
    {
        return "hidden_by_{$this->readMarker()}";
    }

    public function receivesBroadcastNotifications(): bool
    {
        return $this->type === 'accountant' || $this->role === User::ROLE_USER;
    }
}
