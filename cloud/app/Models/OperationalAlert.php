<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalAlert extends Model
{
    protected $fillable = [
        'type', 'subject_key', 'severity', 'message', 'station_id', 'status',
        'occurrences', 'opened_at', 'last_seen_at', 'resolved_at', 'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
