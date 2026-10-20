<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CachedUser extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'cloud_user_uuid', 'phone', 'name', 'phone_verified_at',
        'status', 'is_local_only', 'pending_sync', 'cloud_version', 'synced_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_local_only' => 'boolean',
            'pending_sync' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CachedWallet::class);
    }
}
