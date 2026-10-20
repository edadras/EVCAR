<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'user_id', 'plate_two', 'plate_letter', 'plate_three', 'plate_province',
        'plate_normalized', 'brand', 'model', 'color', 'battery_kwh', 'is_default', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
