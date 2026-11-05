<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tariff extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'code', 'pricing_model', 'currency',
        'price_per_kwh', 'price_per_minute', 'session_fee',
        'member_discount_percent', 'is_default', 'status',
        'valid_from', 'valid_until', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'price_per_kwh' => 'decimal:4',
            'price_per_minute' => 'decimal:4',
            'session_fee' => 'decimal:2',
            'member_discount_percent' => 'decimal:2',
            'is_default' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class, 'station_tariffs')->withPivot(['effective_from', 'effective_until'])->withTimestamps();
    }

    /**
     * تعرفهٔ مؤثر: اول تعرفهٔ فعالی که به همین ایستگاه اختصاص داده شده (و با نوع کانکتور جور است)،
     * بعد تعرفهٔ پیش‌فرض شبکه، بعد هر تعرفهٔ فعال.
     */
    public static function effectiveFor(?int $stationId, ?string $connectorType = null): ?self
    {
        if ($stationId) {
            $group = $connectorType === null ? null
                : (in_array($connectorType, ['Type2', 'Type1', 'AC', 'GBT-AC'], true) ? 'ac' : 'dc');
            $assigned = static::with('rules')->where('status', 'active')
                ->whereHas('stations', fn ($q) => $q->where('stations.id', $stationId)
                    ->where(fn ($w) => $w->whereNull('station_tariffs.effective_from')->orWhere('station_tariffs.effective_from', '<=', now()))
                    ->where(fn ($w) => $w->whereNull('station_tariffs.effective_until')->orWhere('station_tariffs.effective_until', '>', now())))
                ->orderByDesc('updated_at')->get()
                ->first(fn (self $t) => $group === null || in_array($t->meta['connector_scope'] ?? 'all', ['all', $group], true));
            if ($assigned) {
                return $assigned;
            }
        }

        return static::with('rules')->where('is_default', true)->where('status', 'active')->first()
            ?? static::with('rules')->where('status', 'active')->first();
    }

    public function rules(): HasMany
    {
        return $this->hasMany(TariffRule::class);
    }

    public function chargingSessions(): HasMany
    {
        return $this->hasMany(ChargingSession::class);
    }
}
