<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'phone', 'purpose', 'code_hash', 'attempts', 'channel',
        'edge_station_uuid', 'sent_at', 'expires_at', 'verified_at', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
