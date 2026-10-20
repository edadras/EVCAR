<?php

namespace App\Services;

use App\Models\Connector;
use App\Models\Reservation;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Services\Charging\ReservationDispatcher;
use Carbon\Carbon;

class ReservationService
{
    public function __construct(
        private ConnectorAvailabilityService $availability,
    ) {}

    public function validateNewReservation(
        int $userId,
        int $stationId,
        Carbon $from,
        Carbon $until,
        ?int $connectorId = null,
    ): ?string {
        if (! $connectorId) {
            return 'انتخاب کانکتور الزامی است.';
        }

        $connector = Connector::with('charger.station')->find($connectorId);
        if (! $connector || (int) $connector->charger?->station_id !== $stationId) {
            return 'کانکتور انتخاب‌شده متعلق به این ایستگاه نیست.';
        }

        if ($this->userHasUnfulfilledReservation($userId, $stationId)) {
            return 'این کاربر هنوز رزرو قبلی خود را در این ایستگاه استفاده نکرده است. ابتدا رزرو قبلی را لغو کنید یا عملیات شارژ را انجام دهید.';
        }

        if ($error = $this->availability->reservationBlockReason($connector, $from, $until)) {
            return $error;
        }

        return null;
    }

    /** رزرو لغو نشده که هنوز شارژ/ورود ثبت نشده */
    public function userHasUnfulfilledReservation(int $userId, int $stationId): bool
    {
        return Reservation::query()
            ->where('user_id', $userId)
            ->where('station_id', $stationId)
            ->whereNot('status', 'cancelled')
            ->whereNull('checked_in_at')
            ->exists();
    }

    public function connectorOverlaps(int $connectorId, Carbon $from, Carbon $until, ?int $exceptId = null): bool
    {
        return $this->availability->reservationOverlaps($connectorId, $from, $until, $exceptId);
    }

    public function cancel(Reservation $reservation, ?int $cancelledByAdminId = null): void
    {
        $reservation->refresh();
        $meta = $reservation->meta ?? [];
        if ($cancelledByAdminId) {
            $meta['cancelled_by_admin'] = $cancelledByAdminId;
        }

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'meta' => $meta,
        ]);

        app(ReservationDispatcher::class)->cancelOnCharger($reservation->fresh());
    }
}
