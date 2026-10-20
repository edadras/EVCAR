<?php

namespace App\Services\Charging;

use App\Models\CachedRfidCard;
use App\Models\CachedTariff;
use App\Models\CachedUser;
use App\Models\CachedVehicle;
use App\Models\CachedWallet;
use App\Models\EdgeChargingSession;
use App\Models\EdgeConnector;
use App\Models\PendingTransaction;
use App\Models\SessionMeterValue;
use App\Models\SyncOutbox;
use App\Services\Ocpp\ChargePointCommander;
use App\Services\Ocpp\TariffCalculator;
use App\Services\Sync\SyncOutboxService;
use App\Services\Sync\SyncPushRunner;
use App\Support\ChargeEstimator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * چرخهٔ عمر جلسهٔ شارژ روی Edge.
 *
 * با درایور builtin، شروع/توقف واقعاً به شارژر (OCPP RemoteStart/RemoteStopTransaction)
 * ارسال می‌شود و انرژی و هزینه از MeterValues/StopTransaction خود شارژر محاسبه می‌شود.
 * با درایور simulator (بدون سخت‌افزار) انرژی بر اساس توان و زمان تخمین زده می‌شود.
 */
class EdgeChargingService
{
    /** وضعیت‌های کانکتور که شروع شارژ را مجاز نمی‌کنند. */
    // «Reserved» مسدود نمی‌شود: خود شارژر فقط idTag صاحب رزرو را می‌پذیرد.
    private const BLOCKED_CONNECTOR_STATUSES = ['Faulted', 'Unavailable', 'Charging', 'SuspendedEV', 'SuspendedEVSE'];

    public function __construct(
        private SyncOutboxService $outbox,
        private SyncPushRunner $push,
        private ConnectorAuthService $auth,
        private ChargePointCommander $commander,
        private TariffCalculator $tariffs,
    ) {}

    /** @param  array<string, mixed>  $extraMeta */
    public function start(CachedUser $user, string $connectorUuid, array $extraMeta = []): EdgeChargingSession
    {
        if (EdgeChargingSession::where('cached_user_uuid', $user->uuid)->where('status', 'active')->exists()) {
            throw new \RuntimeException('جلسه شارژ فعال دارید.');
        }

        $connector = EdgeConnector::with('charger')
            ->where(function ($q) use ($connectorUuid) {
                $q->where('uuid', $connectorUuid)
                    ->orWhere('cloud_connector_uuid', $connectorUuid);
            })
            ->firstOrFail();

        if (! $this->auth->canStart($user, $connector)) {
            throw new \RuntimeException($this->auth->denyMessage());
        }

        $grant = $this->auth->activeGrant($user, $connector);
        $authMethod = match ($grant?->grant_type) {
            'anpr' => 'anpr',
            'qr' => 'qr',
            'rfid' => 'rfid',
            default => 'wallet',
        };

        return $this->startOnConnector($user, $connector, $authMethod, $extraMeta);
    }

    /**
     * شروع شارژ روی کانکتور مشخص (پس از احراز هویت).
     *
     * @param  array<string, mixed>  $extraMeta
     */
    public function startOnConnector(
        CachedUser $user,
        EdgeConnector $connector,
        string $authMethod,
        array $extraMeta = [],
        ?string $cloudSessionUuid = null,
    ): EdgeChargingSession {
        $connector->loadMissing('charger');
        $charger = $connector->charger;

        if (EdgeChargingSession::where('connector_id', $connector->id)->where('status', 'active')->exists()) {
            throw new \RuntimeException('این کانکتور در حال استفاده است.');
        }

        if ($this->commander->isMetered()) {
            if (! $charger || ! $this->commander->isConnected($charger)) {
                throw new \RuntimeException('شارژر به کنترلر ایستگاه متصل نیست. لطفاً چند لحظه بعد دوباره تلاش کنید.');
            }
            if (in_array($connector->ocpp_status, self::BLOCKED_CONNECTOR_STATUSES, true)) {
                throw new \RuntimeException('کانکتور آماده شارژ نیست (وضعیت: '.$connector->ocpp_status.').');
            }
        }

        $wallet = CachedWallet::where('cached_user_id', $user->id)->first();
        if (! $wallet || $this->spendable($wallet) < $this->minimumBalance()) {
            throw new \RuntimeException('موجودی کیف پول کافی نیست.');
        }

        $tariff = $this->tariffs->current();
        $metered = $this->commander->isMetered();

        $session = EdgeChargingSession::create([
            'uuid' => (string) Str::uuid(),
            'cloud_session_uuid' => $cloudSessionUuid,
            'charger_id' => $connector->charger_id,
            'connector_id' => $connector->id,
            'cached_user_uuid' => $user->uuid,
            'cached_tariff_uuid' => $tariff?->uuid,
            'id_tag' => $this->idTagFor($user),
            'auth_method' => $authMethod,
            'status' => 'active',
            'started_at' => now(),
            'energy_kwh' => 0,
            'cost_amount' => 0,
            'pending_sync' => true,
            'meta' => array_merge([
                'ocpp_state' => $metered ? 'starting' : 'charging',
                'soc' => null,
                'target_soc' => 80,
                'power_kw' => $metered ? 0 : (int) ($connector->max_power_kw ?: $charger?->max_power_kw ?: 22),
                'rated_power_kw' => (int) ($connector->max_power_kw ?: $charger?->max_power_kw ?: 22),
                'cloud_connector_uuid' => $connector->cloud_connector_uuid,
                'cloud_charger_uuid' => $charger?->cloud_charger_uuid,
            ], $extraMeta),
        ]);

        if ($this->commander->driver() !== ChargePointCommander::DRIVER_SIMULATOR) {
            try {
                $response = $this->commander->call(
                    (string) $charger?->charge_point_id,
                    'RemoteStartTransaction',
                    ['connectorId' => (int) $connector->connector_number, 'idTag' => $session->id_tag],
                    (float) $this->commander->setting('remote_start_wait', 20),
                );
            } catch (\Throwable $e) {
                $this->cancel($session, 'RemoteStartFailed');

                throw new \RuntimeException('ارسال دستور شروع به شارژر ناموفق بود: '.$e->getMessage(), 0, $e);
            }

            $status = (string) ($response['status'] ?? 'Rejected');
            if ($status === 'Rejected') {
                $this->cancel($session, 'RemoteStartRejected');

                throw new \RuntimeException('شارژر درخواست شروع را رد کرد. از اتصال صحیح کابل و آماده بودن کانکتور مطمئن شوید.');
            }
        }

        $session->refresh();
        if ($session->status === 'active') {
            $this->emitStarted($session);
            $this->pushSoon(5);
        }

        return $session;
    }

    /**
     * شروع جلسه وقتی شارژر خودش (کارت RFID روی دستگاه) تراکنش را آغاز کرده است.
     */
    public function startFromCharger(CachedUser $user, EdgeConnector $connector, string $idTag, ?CarbonInterface $startedAt = null): EdgeChargingSession
    {
        $connector->loadMissing('charger');
        $tariff = $this->tariffs->current();

        return EdgeChargingSession::create([
            'uuid' => (string) Str::uuid(),
            'charger_id' => $connector->charger_id,
            'connector_id' => $connector->id,
            'cached_user_uuid' => $user->uuid,
            'cached_tariff_uuid' => $tariff?->uuid,
            'id_tag' => $idTag,
            'auth_method' => 'rfid',
            'status' => 'active',
            'started_at' => $startedAt ?? now(),
            'energy_kwh' => 0,
            'cost_amount' => 0,
            'pending_sync' => true,
            'meta' => [
                'ocpp_state' => 'charging',
                'soc' => null,
                'target_soc' => 80,
                'power_kw' => 0,
                'rated_power_kw' => (int) ($connector->max_power_kw ?: $connector->charger?->max_power_kw ?: 22),
                'cloud_connector_uuid' => $connector->cloud_connector_uuid,
                'cloud_charger_uuid' => $connector->charger?->cloud_charger_uuid,
                'started_on_charger' => true,
            ],
        ]);
    }

    public function stop(CachedUser $user): EdgeChargingSession
    {
        $session = EdgeChargingSession::with(['charger', 'connector'])
            ->where('cached_user_uuid', $user->uuid)
            ->where('status', 'active')
            ->latest('started_at')
            ->firstOrFail();

        return $this->stopSession($session);
    }

    public function stopSession(EdgeChargingSession $session, string $reason = 'Remote'): EdgeChargingSession
    {
        $session->loadMissing(['charger', 'connector']);
        if ($session->status !== 'active') {
            return $session;
        }

        $driver = $this->commander->driver();
        $chargePointId = (string) $session->charger?->charge_point_id;

        if ($driver === ChargePointCommander::DRIVER_SIMULATOR) {
            return $this->finalize($session, null, now(), $reason, $this->estimateEnergyKwh($session, $this->elapsedSeconds($session)));
        }

        if ($driver === ChargePointCommander::DRIVER_GATEWAY) {
            if ($session->ocpp_transaction_id) {
                $this->commander->call($chargePointId, 'RemoteStopTransaction', ['transactionId' => (int) $session->ocpp_transaction_id]);
            } elseif (($gatewayChargerId = (int) ($session->charger?->meta['ocpp_gateway_charger_id'] ?? 0)) > 0) {
                $this->commander->gatewayRemoteStopCharger($gatewayChargerId);
            } else {
                throw new \RuntimeException('شناسه تراکنش OCPP برای توقف شارژ یافت نشد.');
            }

            return $this->finalize($session, null, now(), $reason, $this->estimateEnergyKwh($session, $this->elapsedSeconds($session)));
        }

        if (! $session->ocpp_transaction_id) {
            // شارژر هنوز تراکنش را آغاز نکرده (کابل وصل نشده) — جلسه لغو می‌شود.
            return $this->cancel($session, 'Cancelled');
        }

        if (! $session->charger || ! $this->commander->isConnected($session->charger)) {
            // شارژر قطع است؛ با آخرین مقدار کنتور بسته می‌شود. StopTransaction بعدی فقط ثبت می‌شود.
            $this->mergeMeta($session, ['force_closed' => true]);

            return $this->finalize($session, null, now(), 'ChargerOffline');
        }

        $this->mergeMeta($session, ['ocpp_state' => 'stopping']);
        $response = $this->commander->call(
            $chargePointId,
            'RemoteStopTransaction',
            ['transactionId' => (int) $session->ocpp_transaction_id],
            (float) $this->commander->setting('remote_stop_wait', 10),
        );

        if (($response['status'] ?? 'Rejected') === 'Rejected') {
            $this->mergeMeta($session, ['ocpp_state' => 'charging']);

            throw new \RuntimeException('شارژر درخواست توقف را رد کرد.');
        }

        // شارژر پس از پذیرش، StopTransaction را با مقدار نهایی کنتور می‌فرستد.
        $deadline = microtime(true) + 8;
        while (! ChargePointCommander::insideServer() && microtime(true) < $deadline) {
            if ($session->refresh()->status !== 'active') {
                break;
            }
            usleep(200_000);
        }

        return $session->refresh();
    }

    /**
     * بستن نهایی جلسه: محاسبهٔ انرژی/هزینه، برداشت از کیف پول و ارسال رویداد به ابر.
     * ایمن در برابر فراخوانی تکراری.
     */
    public function finalize(
        EdgeChargingSession $session,
        ?int $meterStopWh,
        ?CarbonInterface $stoppedAt,
        string $reason,
        ?float $energyKwh = null,
    ): EdgeChargingSession {
        $stoppedAt ??= now();

        $result = DB::transaction(function () use ($session, $meterStopWh, $stoppedAt, $reason, $energyKwh) {
            /** @var EdgeChargingSession|null $locked */
            $locked = EdgeChargingSession::whereKey($session->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'active') {
                return [$locked ?? $session, false];
            }

            if ($energyKwh === null) {
                $energyKwh = (float) $locked->energy_kwh;
                if ($meterStopWh !== null && $locked->meter_start_wh !== null && $meterStopWh >= (int) $locked->meter_start_wh) {
                    $energyKwh = ($meterStopWh - (int) $locked->meter_start_wh) / 1000;
                }
            }
            $energyKwh = round(max(0, $energyKwh), 3);

            $startedAt = $locked->started_at ?? $stoppedAt;
            $duration = max(0, (int) $startedAt->diffInSeconds($stoppedAt, true));
            $idle = $this->idleSeconds($locked->meta ?? [], $stoppedAt);
            // بدون تحویل انرژی (لغو، کابل وصل نشد، رد شدن) هیچ هزینه‌ای گرفته نمی‌شود.
            $cost = $energyKwh > 0 ? $this->costFor($locked, $energyKwh, $duration, $idle) : 0.0;

            $meta = $locked->meta ?? [];
            $meta['ocpp_state'] = 'finished';
            $meta['power_kw'] = 0;
            $meta['idle_seconds'] = $idle;
            unset($meta['idle_since']);
            if (($meta['soc_source'] ?? null) !== 'charger') {
                $meta['soc'] = ChargeEstimator::socPercent(null, (float) ($meta['start_soc'] ?? ChargeEstimator::DEFAULT_START_SOC), $energyKwh, $this->batteryKwh($locked));
                $meta['soc_source'] = 'estimate';
            }

            $locked->update([
                'status' => 'stopped',
                'stopped_at' => $stoppedAt,
                'duration_seconds' => $duration,
                'energy_kwh' => $energyKwh,
                'cost_amount' => $cost,
                'meter_stop_wh' => $meterStopWh ?? $locked->meter_stop_wh,
                'stop_reason' => substr($reason, 0, 64),
                'pending_sync' => true,
                'meta' => $meta,
            ]);

            $user = CachedUser::where('uuid', $locked->cached_user_uuid)->first();
            if ($user) {
                $this->debitWallet($locked, $user, $cost, $stoppedAt);
            }

            if ($this->startedEventSent($locked)) {
                $this->outbox->enqueue('charging.session.stopped', $this->sessionPayload($locked->fresh(), $user), 'charging_session', $locked->uuid);
            }

            return [$locked->fresh(), true];
        });

        [$fresh, $changed] = $result;
        if ($changed) {
            $this->pushSoon(10);
        }

        return $fresh;
    }

    /** لغو جلسه‌ای که هیچ انرژی‌ای منتقل نکرده است. */
    public function cancel(EdgeChargingSession $session, string $reason): EdgeChargingSession
    {
        return $this->finalize($session, null, now(), $reason, 0.0);
    }

    /**
     * ثبت یک نمونهٔ کنتور (MeterValues) روی جلسه.
     *
     * @param  array{energy_wh?:?int,power_w?:?float,voltage_v?:?float,current_a?:?float,soc?:?int,sampled_at?:CarbonInterface}  $reading
     * @return bool true اگر موجودی کاربر تمام شده و باید شارژ متوقف شود
     */
    public function applyMeterReading(EdgeChargingSession $session, array $reading, array $raw = []): bool
    {
        if ($session->status !== 'active') {
            return false;
        }

        $sampledAt = $reading['sampled_at'] ?? now();
        $meta = $session->meta ?? [];
        $updates = [];

        $energyWh = $reading['energy_wh'] ?? null;
        if ($energyWh !== null) {
            if ($session->meter_start_wh === null) {
                $updates['meter_start_wh'] = $energyWh;
                $session->meter_start_wh = $energyWh;
            }
            $energyKwh = ($energyWh - (int) $session->meter_start_wh) / 1000;
            if ($energyKwh >= (float) $session->energy_kwh) {
                $updates['energy_kwh'] = round($energyKwh, 3);
            }
        }

        if (isset($reading['power_w'])) {
            $meta['power_kw'] = round(max(0, (float) $reading['power_w']) / 1000, 2);
        }
        if (isset($reading['voltage_v'])) {
            $meta['voltage_v'] = round((float) $reading['voltage_v'], 1);
        }
        if (isset($reading['current_a'])) {
            $meta['current_a'] = round((float) $reading['current_a'], 1);
        }
        if (isset($reading['soc'])) {
            $meta['soc'] = max(0, min(100, (int) $reading['soc']));
            $meta['soc_source'] = 'charger';
        }
        $meta['last_meter_at'] = $sampledAt->toIso8601String();
        if (($meta['ocpp_state'] ?? null) === 'starting') {
            $meta['ocpp_state'] = 'charging';
        }

        $energy = (float) ($updates['energy_kwh'] ?? $session->energy_kwh);
        $updates['cost_amount'] = $energy > 0 ? $this->costFor($session, $energy, $this->elapsedSeconds($session), $this->idleSeconds($meta, now())) : 0.0;
        $updates['meta'] = $meta;
        $session->update($updates);

        SessionMeterValue::create([
            'charging_session_id' => $session->id,
            'sampled_at' => $sampledAt,
            'energy_wh' => $energyWh,
            'power_w' => isset($reading['power_w']) ? (int) round(max(0, (float) $reading['power_w'])) : null,
            'voltage_v' => isset($reading['voltage_v']) ? (int) round((float) $reading['voltage_v']) : null,
            'current_a' => $reading['current_a'] ?? null,
            'soc_percent' => isset($reading['soc']) ? max(0, min(100, (int) $reading['soc'])) : null,
            'raw_payload' => $raw ?: null,
        ]);

        $this->maybeSyncProgress($session);

        $user = CachedUser::where('uuid', $session->cached_user_uuid)->first();
        $wallet = $user ? CachedWallet::where('cached_user_id', $user->id)->first() : null;

        $lowBalance = $wallet !== null && (float) $session->cost_amount >= $this->spendable($wallet);
        // توقف خودکار در «حد توقف» فقط وقتی SoC معتبر است: گزارش شارژر یا درصد شروعی که کاربر وارد کرده.
        $fresh = $session->fresh();
        $socKnown = ($meta['soc_source'] ?? null) === 'charger' || isset($meta['start_soc']);
        $targetReached = $socKnown && $this->socPercent($fresh, (float) $fresh->energy_kwh) >= (int) ($meta['target_soc'] ?? 100);

        return $lowBalance || $targetReached;
    }

    public function socPercent(EdgeChargingSession $session, float $energyKwh): int
    {
        $meta = $session->meta ?? [];
        $reported = ($meta['soc_source'] ?? null) === 'charger' && isset($meta['soc']) ? (float) $meta['soc'] : null;

        return ChargeEstimator::socPercent($reported, (float) ($meta['start_soc'] ?? ChargeEstimator::DEFAULT_START_SOC), $energyKwh, $this->batteryKwh($session));
    }

    public function batteryKwh(EdgeChargingSession $session): float
    {
        $kwh = (float) ($session->meta['battery_kwh'] ?? 0);
        if ($kwh <= 0 && $session->cached_vehicle_uuid) {
            $kwh = (float) CachedVehicle::where('uuid', $session->cached_vehicle_uuid)->value('battery_kwh');
        }
        if ($kwh <= 0 && $session->cached_user_uuid) {
            $kwh = (float) CachedVehicle::where('cached_user_uuid', $session->cached_user_uuid)->orderByDesc('is_default')->value('battery_kwh');
        }

        return $kwh > 0 ? $kwh : ChargeEstimator::DEFAULT_BATTERY_KWH;
    }

    /** @return array<string, mixed> */
    public function sessionPayload(EdgeChargingSession $session, ?CachedUser $user = null): array
    {
        $user ??= CachedUser::where('uuid', $session->cached_user_uuid)->first();

        return [
            'uuid' => $session->uuid,
            'cloud_session_uuid' => $session->cloud_session_uuid,
            'cloud_user_uuid' => $user?->cloud_user_uuid,
            'cached_user_uuid' => $session->cached_user_uuid,
            'cloud_connector_uuid' => $session->meta['cloud_connector_uuid'] ?? $session->connector?->cloud_connector_uuid,
            'cloud_charger_uuid' => $session->meta['cloud_charger_uuid'] ?? $session->charger?->cloud_charger_uuid,
            'auth_method' => $session->auth_method,
            'status' => $session->status,
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
            'started_at' => $session->started_at?->toIso8601String(),
            'stopped_at' => $session->stopped_at?->toIso8601String(),
            'stop_reason' => $session->stop_reason,
            'energy_kwh' => (float) $session->energy_kwh,
            'duration_seconds' => (int) $session->duration_seconds,
            'cost_amount' => (float) $session->cost_amount,
            'currency' => $session->currency ?? 'IRR',
            'meta' => $session->meta,
        ];
    }

    public function emitStarted(EdgeChargingSession $session): void
    {
        if ($this->startedEventSent($session)) {
            return;
        }

        $this->outbox->enqueue('charging.session.started', $this->sessionPayload($session), 'charging_session', $session->uuid);
    }

    /** idTag ارسالی به شارژر (حداکثر ۲۰ کاراکتر طبق OCPP 1.6). */
    public function idTagFor(CachedUser $user): string
    {
        $card = CachedRfidCard::where('cached_user_uuid', $user->uuid)
            ->where('status', 'active')
            ->orderBy('id')
            ->value('id_tag');

        if (is_string($card) && $card !== '' && strlen($card) <= 20) {
            return $card;
        }

        // همان قاعدهٔ ابر (شناسهٔ کاربر ابری) تا رزرو ReserveNow و شروع از ابر/Edge یک idTag داشته باشند.
        return 'SC'.strtoupper(substr(str_replace('-', '', (string) ($user->cloud_user_uuid ?: $user->uuid)), 0, 18));
    }

    public function minimumBalance(): float
    {
        return (float) $this->commander->setting('min_wallet_balance', 10000);
    }

    public function spendable(CachedWallet $wallet): float
    {
        return (float) $wallet->balance + (float) ($wallet->credit_limit ?? 0);
    }

    /** @return array{pct:int,energy:float,cost:int,secs:int,power:int,goal:int,eta:?int,state:string} */
    public function liveMetrics(EdgeChargingSession $session): array
    {
        $secs = $session->status === 'active'
            ? $this->elapsedSeconds($session)
            : (int) $session->duration_seconds;
        $metered = $this->commander->isMetered();

        $energy = (float) $session->energy_kwh;
        if (! $metered && $energy <= 0) {
            $energy = $this->estimateEnergyKwh($session, $secs);
        }

        $costRial = (float) $session->cost_amount;
        if ($session->status === 'active' && ($costRial <= 0 || ! $metered)) {
            $costRial = $this->costFor($session, $energy, $secs);
        }

        $goal = (int) ($session->meta['target_soc'] ?? 80);
        $power = (int) round((float) ($session->meta['power_kw'] ?? 0));
        $pct = $this->socPercent($session, $energy);
        $eta = $session->status === 'active'
            ? ChargeEstimator::etaMinutes($pct, $goal, (float) $power, $this->batteryKwh($session))
            : null;

        return [
            'pct' => $pct,
            'energy' => round($energy, 3),
            'cost' => (int) round($costRial / 10),
            'secs' => $secs,
            'power' => $power,
            'goal' => $goal,
            'eta' => $eta,
            'state' => (string) ($session->meta['ocpp_state'] ?? 'charging'),
        ];
    }

    private function debitWallet(EdgeChargingSession $session, CachedUser $user, float $cost, CarbonInterface $at): void
    {
        if ($cost <= 0) {
            return;
        }

        $key = 'edge-debit-'.$session->uuid;
        if (PendingTransaction::where('idempotency_key', $key)->exists()) {
            return;
        }

        $wallet = CachedWallet::where('cached_user_id', $user->id)->lockForUpdate()->first();
        $balanceAfter = $wallet ? round((float) $wallet->balance - $cost, 2) : null;
        $wallet?->update(['balance' => $balanceAfter]);

        $tx = PendingTransaction::create([
            'uuid' => (string) Str::uuid(),
            'charging_session_uuid' => $session->uuid,
            'cached_user_uuid' => $user->uuid,
            'type' => 'wallet_debit',
            'amount' => $cost,
            'status' => 'completed',
            'idempotency_key' => $key,
            'pending_sync' => true,
            'occurred_at' => $at,
            'payload' => ['balance_after' => $balanceAfter],
        ]);

        $this->outbox->enqueue('wallet.debited', [
            'uuid' => $tx->uuid,
            'session_uuid' => $session->uuid,
            'cloud_session_uuid' => $session->cloud_session_uuid,
            'cloud_user_uuid' => $user->cloud_user_uuid,
            'amount' => $cost,
            'balance_after' => $balanceAfter,
            'currency' => $wallet?->currency ?? 'IRR',
            'description' => 'شارژ محلی ایستگاه',
            'occurred_at' => $at->toIso8601String(),
        ], 'wallet_transaction', $tx->uuid);
    }

    private function maybeSyncProgress(EdgeChargingSession $session): void
    {
        $interval = (int) $this->commander->setting('progress_sync_seconds', 60);
        if ($interval <= 0 || ! $this->startedEventSent($session)) {
            return;
        }

        $last = SyncOutbox::where('aggregate_uuid', $session->uuid)
            ->where('event_type', 'charging.session.progress')
            ->latest('id')
            ->value('occurred_at');

        if ($last && now()->diffInSeconds($last, true) < $interval) {
            return;
        }

        $this->outbox->enqueue('charging.session.progress', $this->sessionPayload($session->fresh()), 'charging_session', $session->uuid);
    }

    private function startedEventSent(EdgeChargingSession $session): bool
    {
        return SyncOutbox::where('aggregate_uuid', $session->uuid)
            ->where('event_type', 'charging.session.started')
            ->exists();
    }

    /** @param array<string, mixed> $values */
    private function mergeMeta(EdgeChargingSession $session, array $values): void
    {
        $session->update(['meta' => array_merge($session->meta ?? [], $values)]);
    }

    private function pushSoon(int $limit): void
    {
        // داخل حلقهٔ ocpp:serve ارسال همزمان به ابر ممنوع است (ممکن است حلقه را قفل کند)؛
        // زمان‌بند sync:auto رویدادها را ارسال می‌کند.
        if (ChargePointCommander::insideServer()) {
            return;
        }

        try {
            $this->push->pushPending($limit);
        } catch (\Throwable $e) {
            Log::info('edge.sync.push_deferred', ['error' => $e->getMessage()]);
        }
    }

    /** ثبت شروع/پایان «اشغال بدون شارژ» (خودرو پر شده ولی کابل وصل است) برای هزینهٔ اشغال. */
    public function markIdle(EdgeChargingSession $session, bool $idle): void
    {
        $meta = $session->meta ?? [];
        if ($idle && empty($meta['idle_since'])) {
            $meta['idle_since'] = now()->toIso8601String();
        } elseif (! $idle && ! empty($meta['idle_since'])) {
            $meta['idle_seconds'] = $this->idleSeconds($meta, now());
            unset($meta['idle_since']);
        } else {
            return;
        }
        $session->update(['meta' => $meta]);
    }

    /** @param array<string, mixed> $meta */
    private function idleSeconds(array $meta, CarbonInterface $until): int
    {
        $total = (int) ($meta['idle_seconds'] ?? 0);
        if (! empty($meta['idle_since'])) {
            $total += (int) CarbonImmutable::parse($meta['idle_since'])->diffInSeconds($until, true);
        }

        return $total;
    }

    private function costFor(EdgeChargingSession $session, float $energyKwh, int $durationSeconds, int $idleSeconds = 0): float
    {
        $tariff = $session->cached_tariff_uuid
            ? CachedTariff::where('uuid', $session->cached_tariff_uuid)->first()
            : null;

        return $this->tariffs->cost($tariff ?? $this->tariffs->current(), $energyKwh, $durationSeconds, $session->started_at, $idleSeconds);
    }

    private function elapsedSeconds(EdgeChargingSession $session): int
    {
        return $session->started_at ? max(0, (int) $session->started_at->diffInSeconds(now(), true)) : 0;
    }

    private function estimateEnergyKwh(EdgeChargingSession $session, int $durationSeconds): float
    {
        $powerKw = (float) ($session->meta['power_kw'] ?? $session->meta['rated_power_kw'] ?? 22);

        return round($powerKw * ($durationSeconds / 3600) * 0.85, 3);
    }
}
