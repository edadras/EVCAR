<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class OtpRequest extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'phone', 'purpose', 'code_hash', 'attempts', 'max_attempts',
        'status', 'sent_at', 'expires_at', 'verified_at', 'ip_address',
        'user_agent', 'customer_uuid', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
