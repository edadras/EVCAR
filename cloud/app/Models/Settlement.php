<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'settlement_number', 'partner_id', 'period_start', 'period_end',
        'status', 'gross_revenue', 'platform_fee', 'partner_share',
        'energy_kwh', 'session_count', 'approved_at', 'paid_at',
        'payment_reference', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_revenue' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'partner_share' => 'decimal:2',
            'energy_kwh' => 'decimal:3',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }
}
