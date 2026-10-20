<?php

namespace App\Services\Charging;

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CloudChargingSessionService
{
    public function estimateEnergyKwh(ChargingSession $session, int $durationSeconds): float
    {
        if ((float) $session->energy_kwh > 0) {
            return (float) $session->energy_kwh;
        }

        $powerKw = (float) ($session->meta['power_kw'] ?? $session->charger?->max_power_kw ?? 22);

        return round($powerKw * ($durationSeconds / 3600) * 0.85, 3);
    }

    public function estimateCost(ChargingSession $session, float $energyKwh): float
    {
        if ((float) $session->cost_amount > 0) {
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
        $secs = (int) ($session->duration_seconds
            ?? ($session->started_at ? now()->diffInSeconds($session->started_at) : 0));
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

        $secs = $session->started_at ? now()->diffInSeconds($session->started_at) : 0;
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
