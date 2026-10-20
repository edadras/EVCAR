<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnprDetection extends Model
{
    protected $fillable = [
        'uuid', 'plate_normalized', 'plate_two', 'plate_letter', 'plate_three', 'plate_province',
        'confidence', 'cached_user_uuid', 'cached_vehicle_uuid', 'charging_session_uuid',
        'camera_source', 'connector_id', 'auto_start_status', 'auto_start_message', 'auto_start_at',
        'image_path', 'detected_at', 'pending_sync', 'synced_at', 'raw_result',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:4',
            'detected_at' => 'datetime',
            'auto_start_at' => 'datetime',
            'pending_sync' => 'boolean',
            'synced_at' => 'datetime',
            'raw_result' => 'array',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(EdgeConnector::class, 'connector_id');
    }
}
