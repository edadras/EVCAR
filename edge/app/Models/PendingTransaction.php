<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PendingTransaction extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'charging_session_uuid', 'cached_user_uuid', 'type',
        'amount', 'status', 'idempotency_key', 'payload', 'pending_sync', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
            'pending_sync' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
