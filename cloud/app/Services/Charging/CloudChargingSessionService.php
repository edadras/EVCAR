<?php

namespace App\Services\Charging;

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeCommand;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Edge\EdgeCommandService;
use App\Services\Ocpp\CloudOcppCommander;
use App\Support\ChargeEstimator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CloudChargingSessionService
{
    public function __construct(private EdgeCommandService $edgeCommands) {}

    /**
     * درخواست شروع شارژ از اپ آنلاین. اگر ایستگاه کنترلر Edge دارد، دستور remote_start
     * برای آن صف می‌شود و Edge با OCPP شارژر را روشن می‌کند؛ انرژی و هزینهٔ واقعی
     * از طریق سینک (charging.session.progress / stopped) برمی‌گردد.
     */
    public function requestStart(User $user, Connector $connector, string $authMethod, ?Tariff $tariff, ?Vehicle $vehicle, int $targetSoc, ?int $startSoc = null): ChargingSession
    {
        $connector->loadMissing('charger.station.edgeStation');
        $station = $connector->charger->station;
        $edge = $station?->edgeStation;
        $startSoc ??= ChargeEstimator::DEFAULT_START_SOC;

        // ایستگاه بدون Edge: شارژر مستقیم به سرور OCPP ابری وصل است.
        if ($connector->charger->connectsDirectly()) {
            $direct = app(DirectChargingService::class);
            $connected = app(CloudOcppCommander::class)->isConnected($connector->charger);
            if ($connected || ! config('safircharge.charging.allow_without_edge')) {
                return $direct->start($user, $connector, $authMethod, $tariff, $vehicle, $targetSoc, $startSoc);
            }
            $edge = null; // فقط محیط توسعه: جلسهٔ شبیه‌سازی‌شده بدون شارژر واقعی
        } elseif (! $edge) {
            throw new \RuntimeException('این ایستگاه برای اتصال از طریق Edge تنظیم شده ولی کنترلر Edge آن ثبت نشده است.');
        }

        return DB::transaction(function () use ($user, $connector, $authMethod, $tariff, $vehicle, $targetSoc, $startSoc, $station, $edge) {
            $session = ChargingSession::create([
                'uuid' => (string) Str::uuid(),
                'station_id' => $connector->charger->station_id,
                'charger_id' => $connector->charger_id,
                'connector_id' => $connector->id,
                'user_id' => $user->id,
                'vehicle_id' => $vehicle?->id,
                'tariff_id' => $tariff?->id,
                'edge_station_uuid' => $edge?->uuid,
                'auth_method' => $authMethod,
                'status' => 'active',
                'started_at' => now(),
                'energy_kwh' => 0,
                'cost_amount' => 0,
                'meta' => [
                    'soc' => null,
                    'start_soc' => $startSoc,
                    'target_soc' => $targetSoc,
                    'power_kw' => $edge ? 0 : (int) ($connector->max_power_kw ?: $connector->charger->max_power_kw),
                    'rated_power_kw' => (int) ($connector->max_power_kw ?: $connector->charger->max_power_kw),
                    'edge_backed' => (bool) $edge,
                    'edge_state' => $edge ? 'requested' : 'simulated',
                ],
            ]);

            if ($edge) {
                $command = $this->edgeCommands->queue($station, 'remote_start', $connector, [
                    'cloud_session_uuid' => $session->uuid,
                    'cloud_user_uuid' => $user->uuid,
                    'auth_method' => $authMethod,
                    'target_soc' => $targetSoc,
                    'start_soc' => $startSoc,
                    'battery_kwh' => $vehicle?->battery_kwh,
                ], $user);
                $session->update(['meta' => array_merge($session->meta, ['start_command_uuid' => $command->uuid])]);
            } else {
                $connector->update(['ocpp_status' => 'Charging']);
            }

            return $session->fresh();
        });
    }

    /**
     * درخواست توقف. برای جلسهٔ متصل به Edge، دستور remote_stop صف می‌شود و صورتحساب
     * نهایی از روی کنتور شارژر توسط Edge محاسبه و سینک می‌شود (بدون برداشت دوباره در ابر).
     */
    public function requestStop(ChargingSession $session, User $user): ChargingSession
    {
        if ($session->meta['direct_ocpp'] ?? false) {
            return app(DirectChargingService::class)->stop($session);
        }

        if (! $this->isEdgeBacked($session)) {
            return $this->stop($session, $user);
        }

        $meta = $session->meta ?? [];
        $startCommand = ! empty($meta['start_command_uuid'])
            ? EdgeCommand::where('uuid', $meta['start_command_uuid'])->first()
            : null;

        // Edge هنوز دستور شروع را برنداشته — لغو بدون هزینه.
        if (! $session->edge_session_uuid && $startCommand?->status === 'pending') {
            $startCommand->update(['status' => 'cancelled', 'completed_at' => now(), 'error_message' => 'Cancelled by user']);

            return $this->closeWithoutCharge($session, 'Cancelled');
        }

        $session->loadMissing(['station', 'connector']);
        $this->edgeCommands->queue($session->station, 'remote_stop', $session->connector, [
            'cloud_session_uuid' => $session->uuid,
            'session_uuid' => $session->edge_session_uuid,
        ], $user);

        $session->update(['meta' => array_merge($meta, [
            'edge_state' => 'stop_requested',
            'stop_requested_at' => now()->toIso8601String(),
        ])]);

        return $session->fresh();
    }

    /** نتیجهٔ ناموفق دستور remote_start از Edge. */
    public function startFailed(string $sessionUuid, ?string $error): void
    {
        $session = ChargingSession::where('uuid', $sessionUuid)->where('status', 'active')->first();
        if (! $session || $session->edge_session_uuid) {
            return;
        }

        $session->update(['meta' => array_merge($session->meta ?? [], ['edge_error' => $error])]);
        $this->closeWithoutCharge($session, 'EdgeStartFailed');
    }

    /** درخواست‌های شروعی که Edge در زمان مقرر به آن‌ها پاسخ نداد (مثلاً Edge آفلاین). */
    public function expireStaleRequests(): int
    {
        $minutes = max(1, (int) config('safircharge.charging.remote_start_timeout_minutes', 3));
        $count = 0;

        ChargingSession::query()
            ->where('status', 'active')
            ->whereNull('edge_session_uuid')
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->get()
            ->filter(fn (ChargingSession $s) => ! empty($s->meta['start_command_uuid']))
            ->each(function (ChargingSession $session) use (&$count) {
                $commandUuid = $session->meta['start_command_uuid'] ?? null;
                if ($commandUuid) {
                    EdgeCommand::where('uuid', $commandUuid)
                        ->whereIn('status', ['pending', 'dispatched'])
                        ->update(['status' => 'failed', 'error_message' => 'Expired', 'completed_at' => now()]);
                }
                $this->closeWithoutCharge($session, 'EdgeTimeout');
                $count++;
            });

        return $count;
    }

    /** انرژی/هزینهٔ این جلسه از کنتور واقعی شارژر می‌آید (از طریق Edge یا OCPP مستقیم). */
    public function isEdgeBacked(ChargingSession $session): bool
    {
        return (bool) ($session->meta['edge_backed'] ?? false)
            || (bool) ($session->meta['direct_ocpp'] ?? false)
            || $session->synced_from_edge;
    }

    private function closeWithoutCharge(ChargingSession $session, string $reason): ChargingSession
    {
        $session->update([
            'status' => 'stopped',
            'stopped_at' => now(),
            'duration_seconds' => 0,
            'energy_kwh' => 0,
            'cost_amount' => 0,
            'stop_reason' => $reason,
            'meta' => array_merge($session->meta ?? [], ['edge_state' => 'cancelled']),
        ]);

        return $session->fresh();
    }

    public function estimateEnergyKwh(ChargingSession $session, int $durationSeconds): float
    {
        if ((float) $session->energy_kwh > 0 || $this->isEdgeBacked($session)) {
            return (float) $session->energy_kwh;
        }

        $powerKw = (float) ($session->meta['power_kw'] ?? $session->charger?->max_power_kw ?? 22);

        return round($powerKw * ($durationSeconds / 3600) * 0.85, 3);
    }

    public function estimateCost(ChargingSession $session, float $energyKwh): float
    {
        if ((float) $session->cost_amount > 0 || $this->isEdgeBacked($session)) {
            return (float) $session->cost_amount;
        }

        $tariff = $this->tariffFor($session);
        $secs = $session->started_at ? (int) $session->started_at->diffInSeconds($session->stopped_at ?? now(), true) : 0;

        return app(TariffCalculator::class)->cost($tariff, $energyKwh, $secs, $session->started_at, (int) ($session->meta['idle_seconds'] ?? 0), $this->isMember($session));
    }

    /** کاربر عضو یک حساب سازمانی است (تخفیف اعضا). */
    public function isMember(ChargingSession $session): bool
    {
        return $session->user_id !== null && User::whereKey($session->user_id)->whereNotNull('organization_id')->exists();
    }

    public function tariffFor(ChargingSession $session): ?Tariff
    {
        return ($session->tariff_id ? Tariff::with('rules')->find($session->tariff_id) : null)
            ?? Tariff::with('rules')->where('is_default', true)->where('status', 'active')->first()
            ?? Tariff::with('rules')->where('status', 'active')->first();
    }

    /** @return array{pct:int,energy:float,cost:int,secs:int,power:int,goal:int,eta:?int,state:string,battery_kwh:float} */
    public function liveMetrics(ChargingSession $session): array
    {
        $secs = $session->status === 'active'
            ? ($session->started_at ? (int) $session->started_at->diffInSeconds(now(), true) : 0)
            : (int) $session->duration_seconds;
        $energy = $this->estimateEnergyKwh($session, $secs);
        $costRial = $this->estimateCost($session, $energy);
        $meta = $session->meta ?? [];
        $goal = (int) ($meta['target_soc'] ?? 80);
        $battery = $this->batteryKwh($session);
        $power = (float) ($meta['power_kw'] ?? 0);
        if ($power <= 0 && ! $this->isEdgeBacked($session) && $session->status === 'active') {
            $power = (float) ($meta['rated_power_kw'] ?? $session->charger?->max_power_kw ?? 0);
        }

        $reported = ($meta['soc_source'] ?? null) === 'charger' && isset($meta['soc']) ? (float) $meta['soc'] : null;
        $pct = ChargeEstimator::socPercent($reported, (float) ($meta['start_soc'] ?? ChargeEstimator::DEFAULT_START_SOC), $energy, $battery);

        return [
            'pct' => $pct,
            'energy' => round($energy, 3),
            'cost' => (int) round($costRial / 10),
            'secs' => $secs,
            'power' => (int) round($power),
            'goal' => $goal,
            'eta' => $session->status === 'active' ? ChargeEstimator::etaMinutes($pct, $goal, $power, $battery) : null,
            'state' => (string) ($meta['edge_state'] ?? 'charging'),
            'battery_kwh' => $battery,
        ];
    }

    public function batteryKwh(ChargingSession $session): float
    {
        $kwh = $session->vehicle_id ? (float) Vehicle::whereKey($session->vehicle_id)->value('battery_kwh') : 0;
        if ($kwh <= 0 && $session->user_id) {
            $kwh = (float) Vehicle::where('user_id', $session->user_id)->orderByDesc('is_default')->value('battery_kwh');
        }

        return $kwh > 0 ? $kwh : ChargeEstimator::DEFAULT_BATTERY_KWH;
    }

    public function stop(ChargingSession $session, User $user): ChargingSession
    {
        return DB::transaction(function () use ($session, $user) {
            $session = ChargingSession::where('id', $session->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $stoppedAt = now();
            $duration = $session->started_at ? $session->started_at->diffInSeconds($stoppedAt) : 0;
            $energy = $this->estimateEnergyKwh($session, $duration);
            $cost = $this->estimateCost($session, $energy);
            $meta = $session->meta ?? [];
            $soc = ChargeEstimator::socPercent(null, (float) ($meta['start_soc'] ?? ChargeEstimator::DEFAULT_START_SOC), $energy, $this->batteryKwh($session));

            $session->update([
                'status' => 'stopped',
                'stopped_at' => $stoppedAt,
                'duration_seconds' => $duration,
                'energy_kwh' => $energy,
                'cost_amount' => $cost,
                'meta' => array_merge($session->meta ?? [], ['soc' => $soc]),
            ]);

            if ($cost > 0) {
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                if ($wallet) {
                    $newBalance = max(0, (float) $wallet->balance - $cost);
                    $wallet->update(['balance' => $newBalance]);

                    WalletTransaction::create([
                        'uuid' => (string) Str::uuid(),
                        'wallet_id' => $wallet->id,
                        'type' => 'debit',
                        'amount' => $cost,
                        'balance_after' => $newBalance,
                        'reference_type' => ChargingSession::class,
                        'reference_id' => $session->id,
                        'description' => 'هزینه شارژ — '.($session->station?->name ?? 'ایستگاه'),
                        'occurred_at' => $stoppedAt,
                    ]);
                }
            }

            if ($session->connector_id) {
                Connector::where('id', $session->connector_id)
                    ->where('ocpp_status', 'Charging')
                    ->update(['ocpp_status' => 'Available']);
            }

            return $session->fresh();
        });
    }
}
