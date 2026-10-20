<?php

namespace App\Services\App;

use App\Models\Campaign;
use App\Models\ChargingSession;
use App\Models\QrToken;
use App\Models\Reservation;
use App\Models\RfidCard;
use App\Models\Station;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Services\Audit\AuditService;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Support\Geo;

class MobileAppService
{
    public function __construct(
        private MobileAppPresenter $presenter,
        private ConnectorAvailabilityService $availability,
        private AuditService $audit,
        private CloudChargingSessionService $charging,
    ) {}

    /** @return array{lat:float,lng:float} */
    private function userRef(User $user): array
    {
        return [
            'lat' => (float) ($user->meta['lat'] ?? Geo::defaultRef()['lat']),
            'lng' => (float) ($user->meta['lng'] ?? Geo::defaultRef()['lng']),
        ];
    }

    public function home(User $user): array
    {
        $user->load('wallet');
        $ref = $this->userRef($user);
        $active = ChargingSession::with(['station', 'charger'])
            ->where('user_id', $user->id)->where('status', 'active')->latest('started_at')->first();
        $stations = Station::where('status', 'active')
            ->with(['chargers.connectors'])
            ->withCount('chargers')
            ->get()
            ->sortBy(fn (Station $s) => ($s->latitude && $s->longitude)
                ? Geo::haversineKm($ref['lat'], $ref['lng'], (float) $s->latitude, (float) $s->longitude)
                : 9999)
            ->take(8)
            ->values();
        $featured = Campaign::where('status', 'active')->first();

        return [
            'user' => $user,
            'activeSession' => $active,
            'gaugePct' => $this->presenter->activeSessionGauge($active),
            'campaigns' => Campaign::where('status', 'active')->limit(3)->get(),
            'featuredOffer' => $featured,
            'unreadNotifications' => $user->userNotifications()->whereNull('read_at')->count(),
            'headerDate' => $this->presenter->headerDate($user->meta['city'] ?? 'تهران'),
            'firstName' => $this->presenter->firstName($user),
            'nearbyStations' => $this->presenter->stationCards($stations, $ref['lat'], $ref['lng']),
        ];
    }

    public function map(User $user): array
    {
        $stations = Station::where('status', 'active')->with(['chargers.connectors'])->get();
        $ref = $this->userRef($user);

        return [
            'mapMarkers' => $this->presenter->mapMarkers($stations),
            'mapStations' => $this->presenter->mapStations($stations, $ref['lat'], $ref['lng']),
            'mapStats' => $this->presenter->mapStats($stations),
            'mapCenter' => $ref,
        ];
    }

    public function wallet(User $user): array
    {
        $wallet = $user->wallet;
        $transactions = $wallet
            ? WalletTransaction::where('wallet_id', $wallet->id)->latest('occurred_at')->limit(30)->get()
            : collect();
        $rfid = RfidCard::where('user_id', $user->id)->where('status', 'active')->first();

        return [
            'user' => $user,
            'wallet' => $wallet,
            'rfidCard' => $rfid,
            'transactions' => $transactions,
            'transactionsJs' => $this->presenter->walletTransactions($transactions),
            'rfidMasked' => $this->presenter->rfidMasked($rfid),
            'loyalty' => $this->presenter->loyaltyProgress($user),
        ];
    }

    public function account(User $user): array
    {
        $user->load(['wallet', 'vehicles']);
        $rfid = RfidCard::where('user_id', $user->id)->where('status', 'active')->first();

        return [
            'user' => $user,
            'initials' => $this->presenter->initials($user),
            'phoneFormatted' => $this->presenter->formatPhone($user->phone),
            'memberSince' => $this->presenter->memberSince($user),
            'vehicles' => $user->vehicles,
            'rfidCard' => $rfid,
            'rfidMasked' => $this->presenter->rfidMasked($rfid),
            'sessionCount' => $user->chargingSessions()->count(),
            'totalEnergy' => $user->chargingSessions()->sum('energy_kwh'),
        ];
    }

    public function history(User $user): array
    {
        $sessions = ChargingSession::with(['station', 'charger'])
            ->where('user_id', $user->id)->latest('started_at')->limit(50)->get();

        return [
            'sessions' => $sessions,
            'sessionsJs' => $this->presenter->historySessions($sessions),
            'monthStats' => $this->presenter->monthStats($user),
        ];
    }

    public function charging(User $user): array
    {
        $session = ChargingSession::with(['station', 'charger', 'connector'])
            ->where('user_id', $user->id)->where('status', 'active')->latest('started_at')->first();

        return [
            'user' => $user,
            'session' => $session,
            'wallet' => $user->wallet,
            'gaugePct' => $this->presenter->activeSessionGauge($session),
        ];
    }

    public function notifications(User $user): array
    {
        $all = $user->userNotifications()->latest('sent_at')->limit(40)->get();

        return [
            'notifications' => $all,
            'grouped' => $this->presenter->notificationsGrouped($all),
        ];
    }

    public function vehicles(User $user): array
    {
        $logs = $this->audit->forUserAssets($user, 30)
            ->filter(fn ($log) => $log->subject_type === Vehicle::class);

        return [
            'vehicles' => Vehicle::where('user_id', $user->id)->orderByDesc('is_default')->get(),
            'auditRows' => $this->presenter->auditRows($logs),
        ];
    }

    public function rfid(User $user): array
    {
        $logs = $this->audit->forUserAssets($user, 30)
            ->filter(fn ($log) => $log->subject_type === RfidCard::class);

        return [
            'cards' => RfidCard::where('user_id', $user->id)->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'blocked' THEN 1 ELSE 2 END")->orderByDesc('updated_at')->get(),
            'user' => $user,
            'auditRows' => $this->presenter->auditRows($logs),
        ];
    }

    public function reservations(User $user, ?string $stationCode = null): array
    {
        $stations = Station::where('status', 'active')->with(['chargers.connectors'])->orderBy('name')->get();
        $connectorOptions = $stations->mapWithKeys(function (Station $st) {
            $options = $st->chargers->flatMap(function ($ch) {
                return $ch->connectors->map(function ($cn) use ($ch) {
                    $snap = $this->availability->snapshot($cn);

                    return [
                        'id' => $cn->id,
                        'label' => $ch->charge_point_id.' · کانکتور '.$cn->connector_number,
                        'reservable' => $snap['reservable'],
                        'reason' => $snap['reason'],
                    ];
                });
            })->values();

            return [$st->id => $options];
        });

        // ایستگاه انتخاب‌شده از صفحهٔ شارژر، وگرنه نزدیک‌ترین ایستگاه به موقعیت مرجع کاربر.
        $ref = Geo::defaultRef();
        $defaultStation = ($stationCode ? $stations->firstWhere('code', $stationCode) : null)
            ?? $stations->filter(fn ($s) => $s->latitude && $s->longitude)
                ->sortBy(fn ($s) => Geo::haversineKm($ref['lat'], $ref['lng'], (float) $s->latitude, (float) $s->longitude))
                ->first()
            ?? $stations->first();
        $stationsPayload = $stations->map(fn (Station $st) => [
            'id' => $st->id,
            'name' => $st->name,
            'city' => $st->city,
            'stats' => $this->reserveStationStats($st),
            'connectors' => $this->reserveConnectorsLive($st),
        ])->values();

        return [
            'reservations' => Reservation::with('station')->where('user_id', $user->id)->orderByDesc('reserved_from')->get(),
            'stations' => $stations,
            'stationsPayload' => $stationsPayload,
            'connectorOptions' => $connectorOptions,
            'defaultStation' => $defaultStation,
            'stationStats' => $defaultStation ? $this->reserveStationStats($defaultStation) : ['free' => 0, 'busy' => 0, 'soon' => 0],
            'connectorsLive' => $defaultStation ? $this->reserveConnectorsLive($defaultStation) : [],
        ];
    }

    public function support(User $user): array
    {
        return [
            'tickets' => SupportTicket::where('user_id', $user->id)->latest()->get(),
            'user' => $user,
            'contact' => $this->presenter->supportContact(),
        ];
    }

    public function scan(): array
    {
        $tokens = QrToken::with(['station', 'charger', 'connector'])
            ->where('status', 'active')
            ->whereHas('station', fn ($q) => $q->where('status', 'active'))
            ->latest()->get()
            ->filter(fn (QrToken $t) => ! $t->connector || $this->availability->qrIssueBlockReason($t->connector) === null);

        return [
            'scanTargets' => $this->presenter->scanTargets($tokens),
        ];
    }

    public function charger(?string $stationCode = null, ?int $connectorId = null): array
    {
        $station = $stationCode
            ? Station::where('code', $stationCode)->where('status', 'active')->with(['chargers.connectors'])->first()
            : null;
        $tariff = $this->presenter->tariffBreakdown();

        return [
            'station' => $station,
            'highlightConnectorId' => $connectorId,
            'connectorsJs' => $station ? $this->presenter->chargerConnectors($station) : [],
            'freeCount' => $station ? $this->presenter->stationFreeCount($station) : 0,
            'tariffEnergy' => $tariff['energy'],
            'tariffIdle' => $tariff['idle'],
            'tariffOffpeak' => $tariff['offpeak'],
            'tariffOffpeakHours' => $tariff['offpeak_hours'],
        ];
    }

    public function ticketForm(User $user): array
    {
        return [
            'sessions' => ChargingSession::with('station')
                ->where('user_id', $user->id)->latest('started_at')->limit(10)->get(),
        ];
    }

    public function ticketDetail(User $user, SupportTicket $ticket): array
    {
        return [
            'ticket' => $ticket->load(['messages.user']),
            'messages' => $ticket->messages,
        ];
    }

    public function legal(string $key): string
    {
        return SystemSetting::text('app', $key, match ($key) {
            'privacy' => 'SafirCharge اطلاعات حساب، جلسات شارژ و موقعیت ایستگاه را مطابق قوانین ذخیره و پردازش می‌کند.',
            'terms' => 'استفاده از سامانه شارژ به معنای پذیرش تعرفه‌ها، قوانین پارکینگ و مسئولیت نگهداری از کابل و کانکتور است.',
            default => '',
        });
    }

    /** @return array{free:int,busy:int,soon:int} */
    public function reserveStationStats(Station $station): array
    {
        $connectors = $station->chargers->flatMap(fn ($ch) => $ch->connectors);
        $free = 0;
        $busy = 0;
        $soon = 0;
        foreach ($connectors as $cn) {
            $snap = $this->availability->snapshot($cn);
            if ($snap['reservable']) {
                $free++;
            } elseif ($cn->ocpp_status === 'Charging') {
                $busy++;
            } else {
                $soon++;
            }
        }

        return compact('free', 'busy', 'soon');
    }

    /** @return array<int, array<string, mixed>> */
    public function reserveConnectorsLive(Station $station): array
    {
        $letters = ['A', 'B', 'C'];
        $rows = [];
        $activeSessions = ChargingSession::where('status', 'active')
            ->whereIn('connector_id', $station->chargers->flatMap->connectors->pluck('id'))
            ->get()
            ->keyBy('connector_id');

        foreach ($station->chargers as $ci => $charger) {
            foreach ($charger->connectors as $cn) {
                $snap = $this->availability->snapshot($cn);
                $label = ($letters[$ci] ?? 'C').$cn->connector_number;
                $session = $activeSessions->get($cn->id);
                $charging = $cn->ocpp_status === 'Charging' || $session !== null;
                $live = $session ? $this->charging->liveMetrics($session) : null;
                $rows[] = [
                    'id' => $label,
                    'db_id' => $cn->id,
                    'free' => $snap['reservable'],
                    'charging' => $charging,
                    'state' => $snap['state'],
                    'reason' => $snap['reason'],
                    'soc' => $live['pct'] ?? null,
                    'eta' => $live['eta'] ?? null,
                ];
            }
        }

        return $rows;
    }
}
