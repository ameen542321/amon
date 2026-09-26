<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyKey extends Model
{
    protected $fillable = [
        'actor_type',
        'actor_id',
        'key_hash',
        'request_hash',
        'route_name',
        'status',
        'response_status',
        'response_headers',
        'response_body',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_headers' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
