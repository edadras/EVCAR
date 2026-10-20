<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QrToken extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'station_id', 'charger_id', 'connector_id', 'token',
        'deep_link', 'status', 'offline_capable', 'expires_at', 'version', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'offline_capable' => 'boolean',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QrToken $qr) {
            if (empty($qr->token)) {
                $qr->token = Str::upper(Str::random(24));
            }

            if (! empty($qr->deep_link)) {
                return;
            }

            $charger = $qr->charger_id ? Charger::find($qr->charger_id) : null;
            $connector = $qr->connector_id ? Connector::find($qr->connector_id) : null;
            $station = $qr->station_id ? Station::find($qr->station_id) : null;

            if ($charger && $connector) {
                $qr->deep_link = 'charger://'.$charger->charge_point_id.'/connector/'.$connector->connector_number;
            } elseif ($station) {
                $qr->deep_link = 'https://charge.safircharge.ir/c/'.$station->code.'/'.($connector?->connector_number ?? '1');
            }
        });
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function charger(): BelongsTo
    {
        return $this->belongsTo(Charger::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function displayCode(): string
    {
        return 'QR-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function offlineTokenLabel(): string
    {
        $num = abs(crc32($this->token)) % 90000 + 10000;

        return 'TK-'.$num;
    }

    public function connectorLabel(): string
    {
        if (! $this->connector) {
            return '—';
        }

        $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
        $chargerIdx = 0;

        if ($this->charger && $this->station) {
            $idx = $this->station->chargers()->orderBy('id')->pluck('id')->search($this->charger_id);
            $chargerIdx = $idx === false ? 0 : (int) $idx;
        }

        return ($letters[$chargerIdx] ?? 'C').$this->connector->connector_number;
    }
}
