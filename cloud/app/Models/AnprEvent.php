<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnprEvent extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'station_id', 'edge_detection_uuid', 'plate_normalized',
        'plate_two', 'plate_letter', 'plate_three', 'plate_province',
        'confidence', 'vehicle_id', 'user_id', 'charging_session_id',
        'image_path', 'detected_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:4',
            'detected_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
