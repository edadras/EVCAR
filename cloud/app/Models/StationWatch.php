<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** درخواست مشتری برای اعلان آزاد شدن کانکتور در ایستگاه. */
class StationWatch extends Model
{
    protected $fillable = ['user_id', 'station_id', 'expires_at', 'notified_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
