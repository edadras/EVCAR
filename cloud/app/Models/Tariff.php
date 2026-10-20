<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tariff extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'code', 'pricing_model', 'currency',
        'price_per_kwh', 'price_per_minute', 'session_fee',
        'member_discount_percent', 'is_default', 'status',
        'valid_from', 'valid_until', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kwh' => 'decimal:4',
            'price_per_minute' => 'decimal:4',
            'session_fee' => 'decimal:2',
            'member_discount_percent' => 'decimal:2',
            'is_default' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TariffRule::class);
    }

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }
}
