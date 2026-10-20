<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GsmModemConfig extends Model
{
    protected $table = 'gsm_modem_config';

    protected $fillable = [
        'port', 'baud_rate', 'pin_code', 'sms_center', 'sender_id',
        'is_enabled', 'status', 'last_check_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_check_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'port' => '/dev/ttyUSB0',
            'baud_rate' => 115200,
            'is_enabled' => true,
            'status' => 'unknown',
        ]);
    }
}
