<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class GsmMessageLog extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'otp_request_id', 'phone', 'message_body', 'direction',
        'status', 'modem_reference', 'error_message', 'queued_at', 'sent_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
