<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class CustomerSession extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'customer_uuid', 'token_hash', 'platform',
        'ip_address', 'last_used_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
