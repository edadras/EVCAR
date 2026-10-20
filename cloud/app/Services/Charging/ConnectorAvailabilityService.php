<?php

namespace App\Services\Charging;

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Reservation;
use App\Models\Station;
use Carbon\Carbon;

class ConnectorAvailabilityService
{
    /** @var list<string> */
    private const RESERVABLE_OCPP = ['Available', 'Preparing', 'Finishing'];

    /** @var list<string> */
    private const CHARGER_READY = ['active', 'deployed'];

    public function snapshot(Connector $connector): array
    {
        $connector->loadMissing('charger.station');
        $charger = $connector->charger;
        $station = $charger?->station;

        $state = 'free';
        $reservable = true;
        $reason = null;

        if (! $station || $station->status !== 'active') {
            $state = 'inactive';
            $reservable = false;
            $reason = $this->stationBlockReason($station);
        } elseif (! $charger || $charger->trashed()) {
            $state = 'offline';
            $reservable = false;
            $reason = 'شارژر در دسترس نیست.';
        } elseif (! in_array($charger->status, self::CHARGER_READY, true)) {
            $state = 'offline';
            $reservable = false;
            $reason = 'شارژر غیرفعال است.';
        } elseif ($connector->operational_status !== 'active') {
            $state = 'inactive';
            $reservable = false;
            $reason = 'کانکتور غیرفعال است.';
        } elseif (in_array($connector->ocpp_status, ['Faulted', 'Unavailable'], true)) {
            $state = 'fault';
            $reservable = false;
            $reason = $this->ocppBlockReason($connector->ocpp_status);
        } elseif ($connector->ocpp_status === 'Charging') {
            $state = 'charging';
            $reservable = false;
            $reason = 'این کانکتور در حال شارژ است.';
        } elseif ($connector->ocpp_status === 'Reserved') {
            $state = 'reserved';
            $reservable = false;
            $reason = 'این کانکتور رزرو شده است.';
        } elseif (! $charger->is_online) {
            $state = 'offline';
            $reservable = false;
            $reason = 'شارژر آفلاین است.';
        }

        return [
            'state' => $state,
            'reservable' => $reservable,
            'reason' => $reason,
            'ocpp_status' => $connector->ocpp_status,
            'operational_status' => $connector->operational_status,
            'charger_online' => (bool) $charger?->is_online,
            'station_status' => $station?->status,
        ];
    }

    public function reservationBlockReason(
        Connector $connector,
        Carbon $from,
        Carbon $until,
    ): ?string {
        $connector->loadMissing('charger.station');
        $charger = $connector->charger;
        $station = $charger?->station;

        if ($reason = $this->stationBlockReason($station)) {
            return $reason;
        }

        if (! $charger || $charger->trashed()) {
            return 'شارژر در دسترس نیست.';
        }

        if (! in_array($charger->status, self::CHARGER_READY, true)) {
            return 'شارژر غیرفعال است.';
        }

        if ($connector->operational_status !== 'active') {
            return 'کانکتور غیرفعال است.';
        }

        if (! in_array($connector->ocpp_status, self::RESERVABLE_OCPP, true)) {
            return $this->ocppBlockReason($connector->ocpp_status)
                ?? 'وضعیت فعلی کانکتور اجازهٔ رزرو نمی‌دهد.';
        }

        if ($from->lte(now()->addMinutes(30)) && ! $charger->is_online) {
            return 'شارژر آفلاین است و برای این بازهٔ زمانی قابل رزرو نیست.';
        }

        if ($this->sessionOverlaps($connector->id, $from, $until)) {
            return 'در این بازهٔ زمانی جلسهٔ شارژ فعال روی کانکتور وجود دارد.';
        }

        if ($this->reservationOverlaps($connector->id, $from, $until)) {
            return 'این کانکتور در تاریخ و ساعت انتخاب‌شده قبلاً رزرو شده است.';
        }

        return null;
    }

    public function qrIssueBlockReason(Connector $connector): ?string
    {
        $connector->loadMissing('charger.station');
        $station = $connector->charger?->station;

        if ($reason = $this->stationBlockReason($station)) {
            return $reason;
        }

        $charger = $connector->charger;
        if (! $charger || $charger->trashed()) {
            return 'شارژر در دسترس نیست.';
        }

        if (! in_array($charger->status, self::CHARGER_READY, true)) {
            return 'شارژر هنوز فعال/مستقر نشده است.';
        }

        if ($connector->operational_status !== 'active') {
            return 'کانکتور غیرفعال است.';
        }

        if (in_array($connector->ocpp_status, ['Faulted', 'Unavailable'], true)) {
            return $this->ocppBlockReason($connector->ocpp_status);
        }

        return null;
    }

    public function isReservableNow(Connector $connector): bool
    {
        return $this->reservationBlockReason($connector, now(), now()->addMinutes(30)) === null;
    }

    public function sessionOverlaps(int $connectorId, Carbon $from, Carbon $until): bool
    {
        return ChargingSession::query()
            ->where('connector_id', $connectorId)
            ->where(function ($q) use ($from, $until) {
                $q->where('status', 'active')
                    ->orWhere(function ($q2) use ($from, $until) {
                        $q2->where('started_at', '<', $until)
                            ->where(function ($q3) use ($from) {
                                $q3->whereNull('stopped_at')->orWhere('stopped_at', '>', $from);
                            });
                    });
            })
            ->exists();
    }

    public function reservationOverlaps(int $connectorId, Carbon $from, Carbon $until, ?int $exceptId = null): bool
    {
        return Reservation::query()
            ->where('connector_id', $connectorId)
            ->whereNot('status', 'cancelled')
            ->where('reserved_from', '<', $until)
            ->where('reserved_until', '>', $from)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    private function stationBlockReason(?Station $station): ?string
    {
        if (! $station) {
            return 'ایستگاه یافت نشد.';
        }

        return match ($station->status) {
            'inactive' => 'ایستگاه غیرفعال است و رزرو/شارژ مجاز نیست.',
            'maintenance' => 'ایستگاه در حالت تعمیر است.',
            'active' => null,
            default => 'وضعیت ایستگاه اجازهٔ عملیات نمی‌دهد.',
        };
    }

    private function ocppBlockReason(string $ocppStatus): ?string
    {
        return match ($ocppStatus) {
            'Faulted' => 'این کانکتور در وضعیت خطا است.',
            'Unavailable' => 'این کانکتور در دسترس نیست.',
            'Charging' => 'این کانکتور در حال شارژ است.',
            'Reserved' => 'این کانکتور رزرو شده است.',
            default => null,
        };
    }
}
