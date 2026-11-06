<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Station extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'code', 'name', 'partner_id', 'type', 'status',
        'address', 'city', 'province', 'latitude', 'longitude',
        'timezone', 'offline_mode_enabled', 'local_hostname', 'wifi_ssid',
        'operating_hours', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'offline_mode_enabled' => 'boolean',
            'operating_hours' => 'array',
            'meta' => 'array',
        ];
    }

    public const IMAGE_DIR = 'uploads/stations';

    /** @var array<string, array{label:string, open:?string, close:?string}> */
    public const HOURS_PRESETS = [
        '24h' => ['label' => '۲۴ ساعته', 'open' => null, 'close' => null],
        'day' => ['label' => '۶ صبح تا ۱۲ شب', 'open' => '06:00', 'close' => '24:00'],
        'office' => ['label' => 'اداری (۸ تا ۱۷)', 'open' => '08:00', 'close' => '17:00'],
    ];

    /** @var array<string, string> label => icon */
    public const AMENITIES = [
        'پارکینگ' => 'parking',
        'کافه' => 'cup',
        'رستوران' => 'cup',
        'سرویس بهداشتی' => 'user',
        'WiFi رایگان' => 'wifi',
        'نگهبانی' => 'shield',
        'سرپوشیده' => 'roof',
        'فروشگاه' => 'bag',
        'نمازخانه' => 'star',
    ];

    public function hoursPreset(): string
    {
        $h = $this->operating_hours;
        if (! is_array($h) || empty($h['preset'])) {
            return '24h';
        }

        return $h['preset'];
    }

    public function hoursLabel(): string
    {
        $h = $this->operating_hours;

        return is_array($h) && ! empty($h['label']) ? $h['label'] : self::HOURS_PRESETS['24h']['label'];
    }

    /** @return list<string> */
    public function amenities(): array
    {
        $a = $this->meta['amenities'] ?? [];

        return is_array($a) ? array_values(array_filter($a, 'is_string')) : [];
    }

    public function tariffs(): BelongsToMany
    {
        return $this->belongsToMany(Tariff::class, 'station_tariffs')->withPivot(['effective_from', 'effective_until'])->withTimestamps();
    }

    public function imageUrl(): ?string
    {
        $path = $this->meta['image'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function chargers(): HasMany
    {
        return $this->hasMany(Charger::class);
    }

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function edgeStation(): HasOne
    {
        return $this->hasOne(EdgeStation::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(QrToken::class);
    }
}
