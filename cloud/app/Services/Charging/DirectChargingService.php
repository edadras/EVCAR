<?php

namespace App\Services\Charging;

use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\RfidCard;
use App\Models\SessionMeterValue;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Ocpp\CloudOcppCommander;
use App\Support\ChargeEstimator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * شارژ مستقیم از سرور اصلی برای ایستگاه‌هایی که کنترلر محلی (Edge) ندارند.
 * شارژر با OCPP 1.6J به `php artisan ocpp:serve` ابر وصل است.
 */
class DirectChargingService
{
    private const BLOCKED_STATUSES = ['Faulted', 'Unavailable', 'Charging', 'SuspendedEV', 'SuspendedEVSE', 'Reserved'];

    public function __construct(
        private CloudOcppCommander $commander,
        private TariffCalculator $tariffs,
    ) {}

    public function start(
        User $user,
        Connector $connector,
        string $authMethod,
        ?Tariff $tariff,
        ?Vehicle $vehicle,
        int $targetSoc,
        int $startSoc,
    ): ChargingSession {
        $connector->loadMissing('charger');
        $charger = $connector->charger;

        if (! $this->commander->isConnected($charger)) {
            throw new \RuntimeException('شارژر به سرور متصل نیست. لطفاً چند لحظه بعد دوباره تلاش کنید.');
        }
        if (in_array($connector->ocpp_status, self::BLOCKED_STATUSES, true)) {
            throw new \RuntimeException('کانکتور آماده شارژ نیست (وضعیت: '.$connector->ocpp_status.').');
        }
        if (ChargingSession::where('connector_id', $connector->id)->where('status', 'active')->exists()) {
            throw new \RuntimeException('این کانکتور در حال استفاده است.');
        }

        $session = ChargingSession::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $charger->station_id,
            'charger_id' => $charger->id,
            'connector_id' => $connector->id,
            'user_id' => $user->id,
            'vehicle_id' => $vehicle?->id,
            'tariff_id' => $tariff?->id,
            'id_tag' => $this->idTagFor($user),
            'auth_method' => $authMethod,
            'status' => 'active',
            'started_at' => now(),
            'energy_kwh' => 0,
            'cost_amount' => 0,
            'meta' => [
                'direct_ocpp' => true,
                'edge_state' => 'starting',
                'soc' => null,
                'start_soc' => $startSoc,
                'target_soc' => $targetSoc,
                'power_kw' => 0,
                'rated_power_kw' => (int) ($connector->max_power_kw ?: $charger->max_power_kw),
            ],
        ]);

        try {
            $response = $this->commander->call(
                $charger->charge_point_id,
                'RemoteStartTransaction',
                ['connectorId' => (int) $connector->connector_number, 'idTag' => $session->id_tag],
                (float) $this->commander->setting('remote_start_wait', 20),
            );
        } catch (\Throwable $e) {
            $this->cancel($session, 'RemoteStartFailed');

            throw new \RuntimeException('ارسال دستور شروع به شارژر ناموفق بود: '.$e->getMessage(), 0, $e);
        }

        if (($response['status'] ?? 'Rejected') === 'Rejected') {
            $this->cancel($session, 'RemoteStartRejected');

            throw new \RuntimeException('شارژر درخواست شروع را رد کرد. از اتصال صحیح کابل و آماده بودن کانکتور مطمئن شوید.');
        }

        return $session->refresh();
    }

    /** شروع جلسه وقتی کاربر کارت RFID را روی خود شارژر زده است. */
    public function startFromCharger(User $user, Connector $connector, string $idTag, CarbonInterface $startedAt): ChargingSession
    {
        $connector->loadMissing('charger');
        $vehicle = Vehicle::where('user_id', $user->id)->orderByDesc('is_default')->first();
        $tariff = Tariff::where('is_default', true)->where('status', 'active')->first()
            ?? Tariff::where('status', 'active')->first();

        return ChargingSession::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $connector->charger->station_id,
            'charger_id' => $connector->charger_id,
            'connector_id' => $connector->id,
            'user_id' => $user->id,
            'vehicle_id' => $vehicle?->id,
            'tariff_id' => $tariff?->id,
            'id_tag' => $idTag,
            'auth_method' => 'rfid',
            'status' => 'active',
            'started_at' => $startedAt,
            'energy_kwh' => 0,
            'cost_amount' => 0,
            'meta' => [
                'direct_ocpp' => true,
                'edge_state' => 'charging',
                'target_soc' => (int) ($user->meta['app_prefs']['charge_target_soc'] ?? 80),
                'power_kw' => 0,
                'started_on_charger' => true,
            ],
        ]);
    }

    public function stop(ChargingSession $session): ChargingSession
    {
        $session->loadMissing('charger');
        if ($session->status !== 'active') {
            return $session;
        }

        if (! $session->ocpp_transaction_id) {
            return $this->cancel($session, 'Cancelled');
        }

        if (! $session->charger || ! $this->commander->isConnected($session->charger)) {
            $this->mergeMeta($session, ['force_closed' => true]);

            return $this->finalize($session, null, now(), 'ChargerOffline');
        }

        $this->mergeMeta($session, ['edge_state' => 'stop_requested']);
        $response = $this->commander->call(
            $session->charger->charge_point_id,
            'RemoteStopTransaction',
            ['transactionId' => (int) $session->ocpp_transaction_id],
            (float) $this->commander->setting('remote_stop_wait', 10),
        );

        if (($response['status'] ?? 'Rejected') === 'Rejected') {
            $this->mergeMeta($session, ['edge_state' => 'charging']);

            throw new \RuntimeException('شارژر درخواست توقف را رد کرد.');
        }

        $deadline = microtime(true) + 8;
        while (! CloudOcppCommander::insideServer() && microtime(true) < $deadline) {
            if ($session->refresh()->status !== 'active') {
                break;
            }
            usleep(200_000);
        }

        return $session->refresh();
    }

    public function cancel(ChargingSession $session, string $reason): ChargingSession
    {
        return $this->finalize($session, null, now(), $reason, 0.0);
    }

    /** بستن نهایی جلسه با انرژی کنتور شارژر و برداشت یک‌باره از کیف پول. */
    public function finalize(ChargingSession $session, ?int $meterStopWh, ?CarbonInterface $stoppedAt, string $reason, ?float $energyKwh = null): ChargingSession
    {
        $stoppedAt ??= now();

        return DB::transaction(function () use ($session, $meterStopWh, $stoppedAt, $reason, $energyKwh) {
            $locked = ChargingSession::whereKey($session->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'active') {
                return $locked ?? $session;
            }

            if ($energyKwh === null) {
                $energyKwh = (float) $locked->energy_kwh;
                if ($meterStopWh !== null && $locked->meter_start_wh !== null && $meterStopWh >= (int) $locked->meter_start_wh) {
                    $energyKwh = ($meterStopWh - (int) $locked->meter_start_wh) / 1000;
                }
            }
            $energyKwh = round(max(0, $energyKwh), 3);
            $duration = max(0, (int) ($locked->started_at ?? $stoppedAt)->diffInSeconds($stoppedAt, true));
            $meta = $locked->meta ?? [];
            $idle = $this->idleSeconds($meta, $stoppedAt);
            $cost = $energyKwh > 0 || $duration > 0 ? $this->costFor($locked, $energyKwh, $duration, $idle) : 0.0;
            if ($energyKwh <= 0 && in_array($reason, ['Cancelled', 'RemoteStartFailed', 'RemoteStartRejected', 'NoCableConnected'], true)) {
                $cost = 0.0;
            }

            if (($meta['soc_source'] ?? null) !== 'charger') {
                $meta['soc'] = ChargeEstimator::socPercent(null, (float) ($meta['start_soc'] ?? ChargeEstimator::DEFAULT_START_SOC), $energyKwh, $this->batteryKwh($locked));
            }
            $meta['edge_state'] = 'finished';
            $meta['power_kw'] = 0;
            $meta['idle_seconds'] = $idle;

            $locked->update([
                'status' => 'stopped',
                'stopped_at' => $stoppedAt,
                'duration_seconds' => $duration,
                'energy_kwh' => $energyKwh,
                'cost_amount' => $cost,
                'meter_stop_wh' => $meterStopWh ?? $locked->meter_stop_wh,
                'stop_reason' => substr($reason, 0, 64),
                'meta' => $meta,
            ]);

            if ($cost > 0 && $locked->user_id) {
                $this->debit($locked, $cost, $stoppedAt);
            }

            if ($locked->connector_id) {
                Connector::whereKey($locked->connector_id)->where('ocpp_status', 'Charging')->update(['ocpp_status' => 'Finishing']);
            }

            return $locked->fresh();
        });
    }

    /**
     * @param  array{energy_wh?:?int,power_w?:?float,voltage_v?:?float,current_a?:?float,soc?:?int,sampled_at?:CarbonInterface}  $reading
     * @return bool true اگر باید شارژ متوقف شود (پایان موجودی یا رسیدن به حد توقف)
     */
    public function applyMeterReading(ChargingSession $session, array $reading, array $raw = []): bool
    {
        if ($session->status !== 'active') {
            return false;
        }

        $meta = $session->meta ?? [];
        $updates = [];
        $energyWh = $reading['energy_wh'] ?? null;
        if ($energyWh !== null) {
            if ($session->meter_start_wh === null) {
                $updates['meter_start_wh'] = $energyWh;
                $session->meter_start_wh = $energyWh;
            }
            $kwh = ($energyWh - (int) $session->meter_start_wh) / 1000;
            if ($kwh >= (float) $session->energy_kwh) {
                $updates['energy_kwh'] = round($kwh, 3);
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
        $meta['edge_state'] = ($meta['edge_state'] ?? 'charging') === 'starting' ? 'charging' : ($meta['edge_state'] ?? 'charging');

        $energy = (float) ($updates['energy_kwh'] ?? $session->energy_kwh);
        $secs = $session->started_at ? (int) $session->started_at->diffInSeconds(now(), true) : 0;
        $updates['cost_amount'] = $this->costFor($session, $energy, $secs, $this->idleSeconds($meta, now()));
        $updates['meta'] = $meta;
        $session->update($updates);

        SessionMeterValue::create([
            'charging_session_id' => $session->id,
            'sampled_at' => $reading['sampled_at'] ?? now(),
            'energy_wh' => $energyWh,
            'power_w' => isset($reading['power_w']) ? (int) round(max(0, (float) $reading['power_w'])) : null,
            'voltage_v' => isset($reading['voltage_v']) ? (int) round((float) $reading['voltage_v']) : null,
            'current_a' => $reading['current_a'] ?? null,
            'soc_percent' => isset($reading['soc']) ? max(0, min(100, (int) $reading['soc'])) : null,
            'raw_payload' => $raw ?: null,
        ]);

        $wallet = $session->user_id ? Wallet::where('user_id', $session->user_id)->first() : null;
        $lowBalance = $wallet !== null && (float) $session->cost_amount >= $this->spendable($wallet);
        $socKnown = ($meta['soc_source'] ?? null) === 'charger' || isset($meta['start_soc']);
        $soc = ChargeEstimator::socPercent(($meta['soc_source'] ?? null) === 'charger' ? (float) $meta['soc'] : null, (float) ($meta['start_soc'] ?? ChargeEstimator::DEFAULT_START_SOC), $energy, $this->batteryKwh($session));

        return $lowBalance || ($socKnown && $soc >= (int) ($meta['target_soc'] ?? 100));
    }

    /** ثبت شروع/پایان دورهٔ «اشغال بدون شارژ» (خودرو پر شده ولی کابل وصل است). */
    public function markIdle(ChargingSession $session, bool $idle): void
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

    /** @return array{status:string,user:?User} */
    public function authorizeIdTag(Charger $charger, string $idTag): array
    {
        $pending = ChargingSession::where('charger_id', $charger->id)
            ->where('status', 'active')
            ->whereRaw('UPPER(id_tag) = ?', [strtoupper($idTag)])
            ->first();
        if ($pending) {
            return ['status' => 'Accepted', 'user' => $pending->user_id ? User::find($pending->user_id) : null];
        }

        $card = RfidCard::whereRaw('UPPER(id_tag) = ?', [strtoupper($idTag)])->first();
        if (! $card) {
            return ['status' => 'Invalid', 'user' => null];
        }
        if ($card->status !== 'active') {
            return ['status' => 'Blocked', 'user' => null];
        }
        if ($card->expires_at && $card->expires_at->isPast()) {
            return ['status' => 'Expired', 'user' => null];
        }

        $user = $card->user_id ? User::find($card->user_id) : null;
        if (! $user || ($user->status ?? 'active') !== 'active') {
            return ['status' => 'Blocked', 'user' => null];
        }

        $wallet = Wallet::where('user_id', $user->id)->first();
        if (! $wallet || $this->spendable($wallet) < (float) $this->commander->setting('min_wallet_balance', 10000)) {
            return ['status' => 'Blocked', 'user' => $user];
        }

        return ['status' => 'Accepted', 'user' => $user];
    }

    public function idTagFor(User $user): string
    {
        $card = RfidCard::where('user_id', $user->id)->where('status', 'active')->orderBy('id')->value('id_tag');
        if (is_string($card) && $card !== '' && strlen($card) <= 20) {
            return $card;
        }

        return 'SC'.strtoupper(substr(str_replace('-', '', (string) $user->uuid), 0, 18));
    }

    public function spendable(Wallet $wallet): float
    {
        return (float) $wallet->balance + (float) ($wallet->credit_limit ?? 0);
    }

    private function debit(ChargingSession $session, float $cost, CarbonInterface $at): void
    {
        $key = 'cloud-ocpp-'.$session->uuid;
        if (WalletTransaction::where('idempotency_key', $key)->exists()) {
            return;
        }

        $wallet = Wallet::where('user_id', $session->user_id)->lockForUpdate()->first();
        if (! $wallet) {
            return;
        }

        $balance = round((float) $wallet->balance - $cost, 2);
        $wallet->update(['balance' => $balance]);

        WalletTransaction::create([
            'uuid' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $cost,
            'balance_after' => $balance,
            'reference_type' => ChargingSession::class,
            'reference_id' => $session->id,
            'idempotency_key' => $key,
            'description' => 'هزینه شارژ — '.($session->station?->name ?? 'ایستگاه'),
            'occurred_at' => $at,
        ]);
    }

    private function costFor(ChargingSession $session, float $energyKwh, int $secs, int $idleSeconds): float
    {
        $tariff = app(CloudChargingSessionService::class)->tariffFor($session);

        return $this->tariffs->cost($tariff, $energyKwh, $secs, $session->started_at, $idleSeconds);
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

    private function batteryKwh(ChargingSession $session): float
    {
        return app(CloudChargingSessionService::class)->batteryKwh($session);
    }

    /** @param array<string, mixed> $values */
    private function mergeMeta(ChargingSession $session, array $values): void
    {
        $session->update(['meta' => array_merge($session->meta ?? [], $values)]);
    }
}
