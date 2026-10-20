<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'name', 'type', 'status', 'discount_percent', 'bonus_amount',
        'starts_at', 'ends_at', 'conditions',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'conditions' => 'array',
        ];
    }
}
