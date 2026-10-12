<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['actor_type', 'actor_id', 'announcements', 'messages', 'operations'];

    protected function casts(): array
    {
        return ['announcements' => 'boolean', 'messages' => 'boolean', 'operations' => 'boolean'];
    }
}
