<?php

namespace App\Services\Charging;

use App\Models\Reservation;
use App\Services\Edge\EdgeCommandService;
use App\Services\Ocpp\CloudOcppCommander;
use App\Services\Ocpp\OcppCommandMap;
use Illuminate\Support\Facades\Log;

/**
 * رزرو واقعی روی شارژر: کمی پیش از شروع بازهٔ رزرو، ReserveNow (OCPP) با idTag کاربر ارسال می‌شود
 * تا کانکتور برای دیگران قفل شود؛ لغو رزرو هم CancelReservation می‌فرستد.
 */
class ReservationDispatcher
{
    public const LEAD_MINUTES = 2;

    public function __construct(
        private CloudOcppCommander $direct,
        private EdgeCommandService $edgeCommands,
        private DirectChargingService $directCharging,
    ) {}

    public function dispatchDue(): int
    {
        $sent = 0;
        Reservation::with(['connector.charger.station.edgeStation', 'user'])
            ->whereNotIn('status', ['cancelled', 'completed', 'expired'])
            ->whereNull('checked_in_at')
            ->whereNotNull('connector_id')
            ->where('reserved_from', '<=', now()->addMinutes(self::LEAD_MINUTES))
            ->where('reserved_until', '>', now())
            ->get()
            ->filter(fn (Reservation $r) => empty($r->meta['reserve_sent_at']))
            ->each(function (Reservation $r) use (&$sent) {
                try {
                    $this->send($r, 'reserve_now', [
                        'idTag' => $this->directCharging->idTagFor($r->user),
                        'expiryDate' => $r->reserved_until->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                        'reservationId' => $r->id,
                    ]);
                    $r->update(['meta' => array_merge($r->meta ?? [], ['reserve_sent_at' => now()->toIso8601String()])]);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('reservation.reserve_now_failed', ['reservation' => $r->id, 'error' => $e->getMessage()]);
                }
            });

        // رزروهای منقضی (کاربر نیامد)
        Reservation::whereNotIn('status', ['cancelled', 'completed', 'expired'])
            ->whereNull('checked_in_at')
            ->where('reserved_until', '<', now())
            ->update(['status' => 'expired']);

        return $sent;
    }

    public function cancelOnCharger(Reservation $reservation): void
    {
        if (empty($reservation->meta['reserve_sent_at']) || ! $reservation->connector) {
            return;
        }

        try {
            $this->send($reservation, 'cancel_reservation', ['reservationId' => $reservation->id]);
        } catch (\Throwable $e) {
            Log::warning('reservation.cancel_failed', ['reservation' => $reservation->id, 'error' => $e->getMessage()]);
        }
    }

    /** جلسهٔ شارژ کاربر در ایستگاه، رزرو فعالش را «استفاده‌شده» می‌کند. */
    public static function checkIn(int $userId, int $stationId): void
    {
        Reservation::where('user_id', $userId)
            ->where('station_id', $stationId)
            ->whereNotIn('status', ['cancelled', 'expired', 'completed'])
            ->whereNull('checked_in_at')
            ->where('reserved_from', '<=', now()->addMinutes(30))
            ->where('reserved_until', '>=', now()->subMinutes(30))
            ->update(['checked_in_at' => now(), 'status' => 'completed']);
    }

    /** @param array<string, mixed> $payload */
    private function send(Reservation $reservation, string $type, array $payload): void
    {
        $connector = $reservation->connector->loadMissing('charger.station.edgeStation');
        $charger = $connector->charger;

        if ($charger->connectsDirectly()) {
            [$action, $body] = OcppCommandMap::build($type, (int) $connector->connector_number, $payload);
            $this->direct->dispatch($charger->charge_point_id, $action, $body);

            return;
        }

        $this->edgeCommands->queue($charger->station, $type, $connector, $payload, null);
    }
}
