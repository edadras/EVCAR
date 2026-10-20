<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CachedWallet extends Model
{
    protected $fillable = [
        'uuid', 'cloud_wallet_uuid', 'cached_user_id', 'balance',
        'credit_limit', 'bonus_balance', 'currency', 'cloud_version', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'bonus_balance' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(CachedUser::class, 'cached_user_id');
    }
}
