<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Station;
use App\Models\Tariff;
use App\Models\Vehicle;
use App\Services\Audit\AuditService;
use App\Services\Auth\CustomerAuthService;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Services\Edge\EdgeCommandService;
use App\Support\Persian;
use App\Services\Ocpp\CloudCommandExecutor;
use App\Services\Stations\RemoteStationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteCommandController extends Controller
{
    public function __construct(
        private EdgeCommandService $commands,
        private RemoteStationService $remote,
    ) {}

    public function store(Request $request, Station $station): JsonResponse
    {
        $data = $request->validate([
            'command_type' => ['required', 'string', 'max:40'],
            'connector_id' => ['nullable', 'integer'],
            'payload' => ['nullable', 'array'],
        ]);

        $connector = null;
        if (! empty($data['connector_id'])) {
            $connector = Connector::query()
                ->with('charger')
                ->where('id', $data['connector_id'])
                ->whereHas('charger', fn ($q) => $q->where('station_id', $station->id))
                ->firstOrFail();
        }

        // ایستگاه بدون Edge: دستور مستقیم روی سرور OCPP ابری اجرا می‌شود.
        $directCharger = $connector?->charger ?? $station->chargers()->first();
        if (! $station->edgeStation && $directCharger?->connectsDirectly()) {
            $cmd = app(CloudCommandExecutor::class)->run(
                $station,
                $data['command_type'],
                $connector,
                ($data['payload'] ?? []) + ['charge_point_id' => $directCharger->charge_point_id],
                $request->user(),
            );

            return response()->json([
                'ok' => $cmd->status === 'completed',
                'uuid' => $cmd->uuid,
                'status' => $cmd->status,
                'error' => $cmd->error_message,
                'message' => $cmd->status === 'completed' ? 'دستور مستقیم روی شارژر اجرا شد.' : ($cmd->error_message ?? 'اجرای دستور ناموفق بود.'),
            ], $cmd->status === 'completed' ? 200 : 422);
        }

        try {
            $cmd = $this->commands->queue(
                $station,
                $data['command_type'],
                $connector,
                $data['payload'] ?? [],
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'uuid' => $cmd->uuid,
            'status' => $cmd->status,
            'message' => 'دستور در صف ایستگاه قرار گرفت؛ Edge آن را محلی روی OCPP اجرا می‌کند.',
        ]);
    }

    /** شروع شارژ برای مشتری از پنل؛ جلسه به نام مشتری ثبت و از کیف پول او کسر می‌شود. */
    public function startForCustomer(
        Request $request,
        Station $station,
        CustomerAuthService $customers,
        ConnectorAvailabilityService $availability,
        CloudChargingSessionService $charging,
        AuditService $audit,
    ): JsonResponse {
        $data = $request->validate([
            'connector_id' => ['required', 'integer'],
            'phone' => ['required', 'string', 'max:20'],
            'target_soc' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $connector = Connector::with('charger.station')
            ->where('id', $data['connector_id'])
            ->whereHas('charger', fn ($q) => $q->where('station_id', $station->id))
            ->firstOrFail();

        $user = $customers->findByPhone($data['phone']);
        if (! $user || $user->status !== 'active') {
            return response()->json(['ok' => false, 'error' => 'مشتری فعالی با این شماره پیدا نشد.'], 422);
        }
        if (ChargingSession::where('user_id', $user->id)->where('status', 'active')->exists()) {
            return response()->json(['ok' => false, 'error' => 'این مشتری همین حالا یک جلسهٔ شارژ فعال دارد.'], 422);
        }
        if ($connector->operational_status !== 'active') {
            return response()->json(['ok' => false, 'error' => 'این کانکتور در پنل غیرفعال است.'], 422);
        }
        $snap = $availability->snapshot($connector);
        $replug = $snap['ocpp_status'] === 'Finishing' && $snap['charger_online'];
        if (! $snap['reservable'] && ! $replug) {
            return response()->json(['ok' => false, 'error' => $snap['reason'] ?? 'کانکتور در دسترس نیست.'], 422);
        }
        $wallet = $user->wallet;
        if (! $wallet || (float) $wallet->balance < 10000) {
            return response()->json(['ok' => false, 'error' => 'موجودی کیف پول مشتری کافی نیست ('.Persian::money((int) round((float) ($wallet->balance ?? 0) / 10)).' تومان).'], 422);
        }

        $vehicle = Vehicle::where('user_id', $user->id)->orderByDesc('is_default')->first();
        $targetSoc = (int) ($data['target_soc'] ?? ($user->meta['app_prefs']['charge_target_soc'] ?? 80));
        $tariff = Tariff::effectiveFor($station->id, $connector->charger->connector_type);

        try {
            $session = $charging->requestStart($user, $connector, 'admin', $tariff, $vehicle, $targetSoc);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $session->update(['meta' => array_merge($session->meta ?? [], ['started_by_admin' => $request->user()->id])]);
        $audit->record('charging.admin_start', $session, null, [
            'customer' => $user->phone, 'connector' => $connector->id, 'charger' => $connector->charger->charge_point_id, 'target_soc' => $targetSoc,
        ]);

        return response()->json([
            'ok' => true,
            'session' => $session->uuid,
            'message' => 'شارژ برای '.($user->name ?: $user->phone).' شروع شد؛ هزینه از کیف پول مشتری کسر می‌شود.',
        ]);
    }

    public function recent(Station $station): JsonResponse
    {
        return response()->json([
            'commands' => $this->commands->recentForStation($station),
        ]);
    }

    public function live(Station $station): JsonResponse
    {
        $live = $this->remote->liveConnectors($station);

        return response()->json([
            'online' => $live['online'],
            'edge_online' => $live['edge_online'],
            'connectors' => $live['connectors'],
        ]);
    }
}
