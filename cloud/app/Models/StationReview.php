<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** امتیاز و نظر مشتری دربارهٔ ایستگاه (پس از جلسهٔ شارژ). */
class StationReview extends Model
{
    protected $fillable = ['user_id', 'station_id', 'charging_session_id', 'rating', 'comment', 'tags', 'is_hidden', 'reply', 'replied_at'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'is_hidden' => 'boolean', 'replied_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChargingSession::class, 'charging_session_id');
    }
}
