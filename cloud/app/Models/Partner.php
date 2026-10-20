<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'code', 'name', 'legal_name', 'national_id', 'economic_code',
        'contact_phone', 'contact_email', 'address', 'city', 'province',
        'revenue_share_percent', 'platform_fee_percent', 'settlement_cycle',
        'bank_iban', 'status', 'manager_user_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'revenue_share_percent' => 'decimal:2',
            'platform_fee_percent' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }
}
