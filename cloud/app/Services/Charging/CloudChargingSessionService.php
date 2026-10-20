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
    public function requestStart(User $user, Connector $connector, string $authMethod, ?Tariff $tariff, ?Vehicle $vehicle, int $targetSoc): ChargingSession
    {
        $connector->loadMissing('charger.station.edgeStation');
        $station = $connector->charger->station;
        $edge = $station?->edgeStation;

        if (! $edge && ! config('safircharge.charging.allow_without_edge')) {
            throw new \RuntimeException('این ایستگاه هنوز به کنترلر محلی (Edge) متصل نیست و شروع شارژ از راه دور ممکن نیست.');
        }

        return DB::transaction(function () use ($user, $connector, $authMethod, $tariff, $vehicle, $targetSoc, $station, $edge) {
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

    public function isEdgeBacked(ChargingSession $session): bool
    {
        return (bool) ($session->meta['edge_backed'] ?? false) || $session->synced_from_edge;
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

        $tariff = $session->tariff_id
            ? Tariff::find($session->tariff_id)
            : Tariff::where('is_default', true)->where('status', 'active')->first()
                ?? Tariff::where('status', 'active')->first();

        if ($tariff?->pricing_model === 'per_minute' && $tariff->price_per_minute) {
            $mins = max(1, (int) ceil(($session->duration_seconds ?? 0) / 60));

            return round($mins * (float) $tariff->price_per_minute + (float) ($tariff->session_fee ?? 0), 2);
        }

        $perKwh = (float) ($tariff?->price_per_kwh ?? 0);
        if ($perKwh <= 0) {
            return 0;
        }

        return round($energyKwh * $perKwh + (float) ($tariff->session_fee ?? 0), 2);
    }

    /** @return array{pct:int,energy:float,cost:int,secs:int,power:int,goal:int,eta:?int} */
    public function liveMetrics(ChargingSession $session): array
    {
        $secs = $session->status === 'active'
            ? ($session->started_at ? (int) $session->started_at->diffInSeconds(now(), true) : 0)
            : (int) $session->duration_seconds;
        $energy = $this->estimateEnergyKwh($session, $secs);
        $costRial = $this->estimateCost($session, $energy);
        $goal = (int) ($session->meta['target_soc'] ?? 80);
        $power = (int) ($session->meta['power_kw'] ?? $session->charger?->max_power_kw ?? 0);

        $pct = $this->socPercent($session, $energy);

        return [
            'pct' => $pct,
            'energy' => $energy,
            'cost' => (int) round($costRial / 10),
            'secs' => $secs,
            'power' => $power,
            'goal' => $goal,
            'eta' => $this->etaMinutes($pct, $goal, $power),
        ];
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
            $targetKwh = $this->targetKwh($session);
            $soc = min(99, max(10, (int) round(10 + ($energy / max(1, $targetKwh)) * 70)));

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

    private function socPercent(ChargingSession $session, float $energyKwh): int
    {
        if (isset($session->meta['soc']) && (float) $session->energy_kwh > 0) {
            return min(99, max(5, (int) $session->meta['soc']));
        }

        $targetKwh = $this->targetKwh($session);
        if ($targetKwh > 0 && $energyKwh > 0) {
            return min(99, max(5, (int) round(($energyKwh / $targetKwh) * 100)));
        }

        if (isset($session->meta['soc'])) {
            return min(99, max(5, (int) $session->meta['soc']));
        }

        $secs = $session->started_at ? (int) $session->started_at->diffInSeconds(now(), true) : 0;
        if ($secs > 0) {
            return min(99, max(5, (int) round(10 + ($secs / 3600) * 40)));
        }

        return 10;
    }

    private function targetKwh(ChargingSession $session): float
    {
        $powerKw = (float) ($session->meta['power_kw'] ?? $session->charger?->max_power_kw ?? 22);
        $targetSoc = (int) ($session->meta['target_soc'] ?? 80);

        return max(5, $powerKw * ($targetSoc / 100));
    }

    private function etaMinutes(int $pct, int $goal, int $powerKw): ?int
    {
        $left = max(0, $goal - $pct);
        if ($left <= 0 || $powerKw <= 0) {
            return null;
        }

        return max(1, (int) round($left / max(1, $powerKw) * 60 / max(1, $goal) * 10));
    }
}
