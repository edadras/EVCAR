<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcppMessageLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'charge_point_id', 'direction', 'message_type', 'action', 'message_id', 'payload',
    ];
}
