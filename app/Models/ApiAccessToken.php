<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiAccessToken extends Model
{
    protected $fillable = [
        'actor_type', 'actor_id', 'token_hash', 'device_uuid', 'device_name',
        'platform', 'app_version', 'abilities', 'last_ip', 'last_user_agent',
        'last_used_at', 'expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
