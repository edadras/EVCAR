<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcppCall extends Model
{
    protected $fillable = [
        'uuid', 'charge_point_id', 'action', 'payload', 'status', 'message_id',
        'response', 'error', 'sent_at', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'sent_at' => 'datetime',
            'answered_at' => 'datetime',
        ];
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['answered', 'error', 'timeout'], true);
    }
}
