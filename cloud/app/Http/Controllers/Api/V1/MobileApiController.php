<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ChargingSession;
use App\Models\EvCatalogVehicle;
use App\Models\Payment;
use App\Models\QrToken;
use App\Models\Reservation;
use App\Models\RfidCard;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Services\App\ChargingAuthorizationService;
use App\Services\App\ConnectivityService;
use App\Services\App\MobileAppPresenter;
use App\Services\App\MobileAppService;
use App\Services\Auth\CustomerAuthService;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Finance\PaymentGatewayService;
use App\Services\Navigator\EvNavigatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * API اپ موبایل (Flutter). داده‌ها از همان سرویس‌های وب‌اپ مشتری خوانده می‌شوند و
 * اکشن‌ها (شروع شارژ، شارژ کیف پول، خودرو، RFID و …) مستقیماً به ActionController وب‌اپ
 * می‌روند (MobileJsonResponses) تا رفتار اپ و وب‌اپ دقیقاً یکسان باشد.
 * مبالغ همه به تومان هستند.
 */
class MobileApiController extends Controller
{
    public function __construct(
        private MobileAppService $app,
        private MobileAppPresenter $presenter,
    ) {}

    // ───────────────────────── احراز هویت ─────────────────────────

    public function login(Request $request, CustomerAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:128'],
        ]);

        $user = $auth->findByPhone($data['phone']);
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'شماره موبایل یا رمز عبور اشتباه است.'], 422);
        }
        if ($user->status !== 'active') {
            return response()->json(['message' => 'حساب کاربری غیرفعال است.'], 422);
        }

        $user->update(['last_login_at' => now()]);

        return $this->tokenResponse($user, $data['device_name'] ?? null, 'ورود موفق');
    }

    public function register(Request $request, CustomerAuthService $auth): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'registration_token' => ['required', 'string', 'max:64'],
            'registration_proof' => ['nullable', 'string', 'max:128'],
            'name' => ['nullable', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            $result = $auth->register(
                $data['phone'],
                $data['password'],
                $data['registration_token'],
                $data['name'] ?? null,
                $data['registration_proof'] ?? null,
                startWebSession: false,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->tokenResponse(User::findOrFail($result['user_id']), $data['device_name'] ?? null, $result['message']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true, 'message' => 'خروج انجام شد.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    // ───────────────────────── صفحات ─────────────────────────

    public function home(Request $request): JsonResponse
    {
        $user = $request->user();
        $d = $this->app->home($user);

        return response()->json([
            'user' => $this->userPayload($user),
            'header_date' => $d['headerDate'],
            'first_name' => $d['firstName'],
            'unread_notifications' => $d['unreadNotifications'],
            'active_session' => $d['activeSession'] ? $this->sessionPayload($d['activeSession']) : null,
            'featured_offer' => $d['featuredOffer'] ? $this->campaignPayload($d['featuredOffer']) : null,
            'campaigns_count' => $d['campaigns']->count(),
            'nearby_stations' => $d['nearbyStations'],
        ]);
    }

    public function map(Request $request): JsonResponse
    {
        $d = $this->app->map($request->user());

        return response()->json([
            'markers' => $d['mapMarkers'],
            'stations' => $d['mapStations'],
            'stats' => $d['mapStats'],
            'center' => $d['mapCenter'],
        ]);
    }

    public function charger(Request $request, ChargingAuthorizationService $auth): JsonResponse
    {
        $qr = $request->query('qr');
        $stationCode = $request->query('station');
        $connectorId = $request->query('connector') ? (int) $request->query('connector') : null;
        // QR فیزیکی ممکن است فقط توکن را داشته باشد؛ ایستگاه و کانکتور از خود توکن خوانده می‌شود.
        if ($qr && ! $stationCode && ($token = QrToken::with('station')->where('token', $qr)->first())) {
            $stationCode = $token->station?->code;
            $connectorId ??= $token->connector_id;
        }
        $grant = $auth->tryGrantFromQrToken($qr);
        $d = $this->app->charger($stationCode, $connectorId);
        $station = $d['station'];
        if (! $station) {
            return response()->json(['message' => 'ایستگاه یافت نشد.'], 404);
        }

        $user = $request->user();
        $connectors = array_map(function (array $c) use ($auth) {
            $c['authorized'] = $auth->activeGrant((int) $c['db_id']) !== null;

            return $c;
        }, $d['connectorsJs']);
        $defaultVehicle = Vehicle::where('user_id', $user->id)->orderByDesc('is_default')->first();

        return response()->json([
            'station' => [
                'id' => $station->id,
                'code' => $station->code,
                'name' => $station->name,
                'address' => $station->address,
                'city' => $station->city,
                'lat' => $station->latitude ? (float) $station->latitude : null,
                'lng' => $station->longitude ? (float) $station->longitude : null,
                'max_power_kw' => (int) $station->chargers->max('max_power_kw'),
                'chargers_count' => $station->chargers->count(),
                'rating' => $station->meta['rating'] ?? null,
                'reviews' => $station->meta['reviews'] ?? null,
                'amenities' => array_values((array) ($station->meta['amenities'] ?? [])),
                'hours' => is_array($station->operating_hours) && ! empty($station->operating_hours['label'])
                    ? $station->operating_hours['label'] : '۲۴ ساعته',
            ],
            'connectors' => $connectors,
            'free_count' => $d['freeCount'],
            'highlight_connector_id' => $d['highlightConnectorId'],
            'qr_grant' => $grant,
            'tariff' => [
                'energy' => $d['tariffEnergy'],
                'idle' => $d['tariffIdle'],
                'idle_grace' => $d['tariffIdleGrace'],
                'offpeak' => $d['tariffOffpeak'],
                'offpeak_hours' => $d['tariffOffpeakHours'],
            ],
            'wallet_balance' => $this->toman($user->wallet?->balance),
            'charge_target_soc' => (int) ($user->meta['app_prefs']['charge_target_soc'] ?? 80),
            'vehicle' => $defaultVehicle ? $this->vehiclePayload($defaultVehicle) : null,
            'has_active_session' => ChargingSession::where('user_id', $user->id)->where('status', 'active')->exists(),
        ]);
    }

    public function charging(Request $request, CloudChargingSessionService $charging): JsonResponse
    {
        $user = $request->user();
        $d = $this->app->charging($user);
        $session = $d['session'];

        return response()->json([
            'session' => $session ? $this->sessionPayload($session) : null,
            'live' => $session ? $charging->liveMetrics($session) : null,
            'wallet_balance' => $this->toman($d['wallet']?->balance),
            'charge_target_soc' => (int) ($user->meta['app_prefs']['charge_target_soc'] ?? 80),
        ]);
    }

    public function wallet(Request $request): JsonResponse
    {
        $d = $this->app->wallet($request->user());

        return response()->json([
            'balance' => $this->toman($d['wallet']?->balance),
            'bonus_balance' => $this->toman($d['wallet']?->bonus_balance),
            'rfid_masked' => $d['rfidMasked'],
            'loyalty' => $d['loyalty'],
            'transactions' => $d['transactionsJs'],
            'gateways' => app(PaymentGatewayService::class)->gateways(),
        ]);
    }

    /** وضعیت یک پرداخت (اپ پس از بازگشت از درگاه بانک آن را می‌خواند). */
    public function payment(Request $request, string $uuid): JsonResponse
    {
        $payment = Payment::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json([
            'uuid' => $payment->uuid,
            'status' => $payment->status,
            'amount' => $this->toman($payment->amount),
            'gateway' => $payment->gateway,
            'reference' => $payment->gateway_reference,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'balance' => $this->toman($request->user()->wallet()->first()?->balance),
        ]);
    }

    public function account(Request $request): JsonResponse
    {
        $user = $request->user();
        $d = $this->app->account($user);

        return response()->json([
            'user' => $this->userPayload($user),
            'initials' => $d['initials'],
            'phone_formatted' => $d['phoneFormatted'],
            'member_since' => $d['memberSince'],
            'vehicles' => $d['vehicles']->map(fn (Vehicle $v) => $this->vehiclePayload($v))->values(),
            'rfid_masked' => $d['rfidMasked'],
            'session_count' => $d['sessionCount'],
            'total_energy' => round((float) $d['totalEnergy'], 1),
            'balance' => $this->toman($user->wallet?->balance),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $d = $this->app->history($request->user());

        return response()->json([
            'sessions' => $d['sessionsJs'],
            'month_stats' => $d['monthStats'],
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $d = $this->app->notifications($request->user());
        $map = fn ($items) => collect($items)->map(fn (UserNotification $n) => [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'read' => $n->read_at !== null,
            'time' => $n->sent_at?->locale('fa')->diffForHumans(),
            'sent_at' => $n->sent_at?->toIso8601String(),
        ])->values();

        return response()->json([
            'today' => $map($d['grouped']['today']),
            'earlier' => $map($d['grouped']['earlier']),
        ]);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $d = $this->app->vehicles($request->user());
        $models = EvCatalogVehicle::query()->where('is_active', true)->orderBy('name')->pluck('name')
            ->merge($d['vehicles']->map(fn (Vehicle $v) => trim("{$v->brand} {$v->model}")))
            ->unique()->filter()->values();

        return response()->json([
            'vehicles' => $d['vehicles']->map(fn (Vehicle $v) => $this->vehiclePayload($v))->values(),
            'model_options' => $models,
            'plate_letters' => ['الف', 'ب', 'ج', 'د', 'س', 'ص', 'ط', 'ق', 'ل', 'م', 'ن', 'و', 'ه', 'ی', 'معلولین'],
            'audit' => $d['auditRows'],
        ]);
    }

    public function rfid(Request $request): JsonResponse
    {
        $d = $this->app->rfid($request->user());

        return response()->json([
            'cards' => $d['cards']->map(fn (RfidCard $c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'id_tag' => $c->id_tag,
                'id_tag_formatted' => $this->presenter->formatRfidTag($c->id_tag),
                'label' => $c->card_label,
                'status' => $c->status,
                'status_label' => $this->presenter->rfidStatusLabel($c->status),
                'issued_at' => $c->issued_at?->toIso8601String(),
            ])->values(),
            'audit' => $d['auditRows'],
        ]);
    }

    public function reservations(Request $request): JsonResponse
    {
        $d = $this->app->reservations($request->user(), $request->query('station'));

        return response()->json([
            'reservations' => $d['reservations']->map(fn (Reservation $r) => [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'station' => $r->station?->name,
                'station_id' => $r->station_id,
                'status' => $r->status,
                'from' => $r->reserved_from?->toIso8601String(),
                'until' => $r->reserved_until?->toIso8601String(),
            ])->values(),
            'stations' => $d['stationsPayload'],
            'default_station_id' => $d['defaultStation']?->id,
        ]);
    }

    public function scan(): JsonResponse
    {
        $targets = array_map(function (array $t) {
            parse_str((string) parse_url($t['url'], PHP_URL_QUERY), $q);

            return ['label' => $t['label'], 'station' => $q['station'] ?? null, 'connector' => isset($q['connector']) ? (int) $q['connector'] : null, 'qr' => $q['qr'] ?? null];
        }, $this->app->scan()['scanTargets']);

        return response()->json(['targets' => $targets]);
    }

    public function support(Request $request): JsonResponse
    {
        $d = $this->app->support($request->user());

        return response()->json([
            'tickets' => $d['tickets']->map(fn (SupportTicket $t) => $this->ticketPayload($t))->values(),
            'contact' => $d['contact'],
        ]);
    }

    public function ticketForm(Request $request): JsonResponse
    {
        $d = $this->app->ticketForm($request->user());

        return response()->json([
            'sessions' => $d['sessions']->map(fn (ChargingSession $s) => [
                'id' => $s->id,
                'label' => ($s->station?->name ?? '—').' · '.($s->started_at?->format('Y/m/d H:i') ?? ''),
            ])->values(),
        ]);
    }

    public function ticket(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $ticket = SupportTicket::where('user_id', $user->id)->findOrFail($id);
        $d = $this->app->ticketDetail($user, $ticket);

        return response()->json([
            'ticket' => $this->ticketPayload($d['ticket']),
            'messages' => $d['messages']->map(fn (TicketMessage $m) => [
                'id' => $m->id,
                'message' => $m->message,
                'staff' => (bool) $m->is_staff_reply,
                'author' => $m->user?->name,
                'time' => $m->created_at?->format('Y/m/d H:i'),
            ])->values(),
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json(['prefs' => (object) ($request->user()->meta['app_prefs'] ?? [])]);
    }

    public function offers(): JsonResponse
    {
        return response()->json([
            'campaigns' => Campaign::where('status', 'active')->get()->map(fn (Campaign $c) => $this->campaignPayload($c))->values(),
        ]);
    }

    public function legal(string $key): JsonResponse
    {
        abort_unless(in_array($key, ['privacy', 'terms'], true), 404);

        return response()->json(['content' => $this->app->legal($key)]);
    }

    public function navigator(Request $request, EvNavigatorService $navigator): JsonResponse
    {
        $d = $navigator->pageData($request->user());
        $vehicles = collect($d['userVehicles'] ?? [])->map(fn ($v) => $v instanceof Vehicle ? $this->vehiclePayload($v) : $v)->values();

        return response()->json([
            'presets' => $d['vehiclePresets'] ?? [],
            'vehicles' => $vehicles,
            'default_vehicle' => ($d['defaultVehicle'] ?? null) instanceof Vehicle ? $this->vehiclePayload($d['defaultVehicle']) : ($d['defaultVehicle'] ?? null),
            'center' => $d['mapCenter'] ?? null,
            'api_configured' => (bool) ($d['apiConfigured'] ?? false),
            'popular_routes' => $d['popularRoutes'] ?? [],
        ]);
    }

    public function context(Request $request, ConnectivityService $connectivity): JsonResponse
    {
        return response()->json(['connectivity' => $connectivity->build($request->user()), 'backend' => 'cloud']);
    }

    // ───────────────────────── کمکی‌ها ─────────────────────────

    private function tokenResponse(User $user, ?string $device, string $message): JsonResponse
    {
        if ($user->role !== UserRole::Customer) {
            return response()->json(['message' => 'این اپ مخصوص مشتریان است.'], 403);
        }

        $token = $user->createToken($device ?: 'mobile-app', ['mobile'], now()->addDays(90));

        return response()->json([
            'message' => $message,
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    private function toman(mixed $rial): int
    {
        return (int) round((float) ($rial ?? 0) / 10);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $meta = $user->meta ?? [];
        $parts = preg_split('/\s+/u', trim((string) $user->name), 2) ?: [];

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? '',
            'email' => str_ends_with((string) $user->email, '@safircharge.local') ? null : $user->email,
            'phone' => $user->phone,
            'national_id' => $meta['national_id'] ?? $user->national_code,
            'city' => $meta['city'] ?? null,
            'balance' => $this->toman($user->wallet?->balance),
            'prefs' => (object) ($meta['app_prefs'] ?? []),
        ];
    }

    /** @return array<string, mixed> */
    private function sessionPayload(ChargingSession $s): array
    {
        $live = app(CloudChargingSessionService::class)->liveMetrics($s);

        return [
            'id' => $s->id,
            'uuid' => $s->uuid,
            'status' => $s->status,
            'station' => $s->station?->name,
            'charge_point' => $s->charger?->charge_point_id,
            'connector_number' => $s->connector?->connector_number,
            'auth_method' => $this->presenter->authMethodLabel($s->auth_method),
            'started_at' => $s->started_at?->toIso8601String(),
            'energy_kwh' => round((float) $s->energy_kwh, 2),
            'cost' => $this->toman($s->cost_amount),
            'pct' => $live['pct'],
            'power' => $live['power'],
            'eta' => $live['eta'],
        ];
    }

    /** @return array<string, mixed> */
    private function vehiclePayload(Vehicle $v): array
    {
        return [
            'id' => $v->id,
            'uuid' => $v->uuid,
            'brand' => $v->brand,
            'model' => $v->model,
            'brand_model' => trim($v->brand.' '.$v->model),
            'plate_two' => $v->plate_two,
            'plate_letter' => $v->plate_letter,
            'plate_three' => $v->plate_three,
            'plate_province' => $v->plate_province,
            'battery_kwh' => (int) $v->battery_kwh,
            'is_default' => (bool) $v->is_default,
            'line' => $this->presenter->vehicleLine($v),
        ];
    }

    /** @return array<string, mixed> */
    private function ticketPayload(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'number' => $t->ticket_number,
            'subject' => $t->subject,
            'body' => $t->body,
            'category' => $t->category,
            'status' => $t->status,
            'status_label' => $this->presenter->ticketStatusLabel($t->status),
            'created' => $t->created_at?->format('Y/m/d H:i'),
        ];
    }

    /** @return array<string, mixed> */
    private function campaignPayload(Campaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'type' => $c->type,
            'discount_percent' => $c->discount_percent ? (int) $c->discount_percent : null,
            'bonus_amount' => $c->bonus_amount ? $this->toman($c->bonus_amount) : null,
            'ends_at' => $c->ends_at?->format('Y/m/d'),
            'summary' => $c->conditions['summary'] ?? null,
        ];
    }
}
