<?php

namespace App\Services\Charging;

use App\Models\CachedTariff;
use App\Models\CachedUser;
use App\Models\CachedWallet;
use App\Models\EdgeChargingSession;
use App\Models\EdgeConnector;
use App\Models\PendingTransaction;
use App\Services\Sync\SyncOutboxService;
use App\Services\Sync\SyncPushRunner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EdgeChargingService
{
    public function __construct(
        private SyncOutboxService $outbox,
        private SyncPushRunner $push,
        private ConnectorAuthService $auth,
    ) {}

    public function start(CachedUser $user, string $connectorUuid): EdgeChargingSession
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

        $wallet = CachedWallet::where('cached_user_id', $user->id)->first();
        if (! $wallet || (float) $wallet->balance < 10000) {
            throw new \RuntimeException('موجودی کیف پول کافی نیست.');
        }

        $tariff = CachedTariff::query()->orderByDesc('synced_at')->first();

        return DB::transaction(function () use ($user, $connector, $wallet, $tariff, $authMethod) {
            $session = EdgeChargingSession::create([
                'uuid' => (string) Str::uuid(),
                'charger_id' => $connector->charger_id,
                'connector_id' => $connector->id,
                'cached_user_uuid' => $user->uuid,
                'cached_tariff_uuid' => $tariff?->uuid,
                'auth_method' => $authMethod,
                'status' => 'active',
                'started_at' => now(),
                'energy_kwh' => 0,
                'cost_amount' => 0,
                'pending_sync' => true,
                'meta' => [
                    'soc' => 10,
                    'target_soc' => 80,
                    'power_kw' => (int) ($connector->max_power_kw ?: $connector->charger?->max_power_kw ?: 22),
                    'cloud_connector_uuid' => $connector->cloud_connector_uuid,
                    'cloud_charger_uuid' => $connector->charger?->cloud_charger_uuid,
                ],
            ]);

            $this->outbox->enqueue('charging.session.started', $this->sessionPayload($session, $user), 'charging_session', $session->uuid);
            $this->push->pushPending(5);

            return $session;
        });
    }

    public function stop(CachedUser $user): EdgeChargingSession
    {
        $session = EdgeChargingSession::with(['charger', 'connector'])
            ->where('cached_user_uuid', $user->uuid)
            ->where('status', 'active')
            ->latest('started_at')
            ->firstOrFail();

        return DB::transaction(function () use ($session, $user) {
            $stoppedAt = now();
            $duration = $session->started_at ? $session->started_at->diffInSeconds($stoppedAt) : 0;
            $energy = $this->estimateEnergyKwh($session, $duration);
            $cost = $this->estimateCost($session, $energy);

            $session->update([
                'status' => 'stopped',
                'stopped_at' => $stoppedAt,
                'duration_seconds' => $duration,
                'energy_kwh' => $energy,
                'cost_amount' => $cost,
                'pending_sync' => true,
                'meta' => array_merge($session->meta ?? [], [
                    'soc' => min(99, max(10, (int) round(10 + ($energy / max(1, $this->targetKwh($session))) * 70))),
                ]),
            ]);

            $wallet = CachedWallet::where('cached_user_id', $user->id)->lockForUpdate()->first();
            if ($wallet && $cost > 0) {
                $wallet->update(['balance' => max(0, (float) $wallet->balance - $cost)]);
                $tx = PendingTransaction::create([
                    'uuid' => (string) Str::uuid(),
                    'charging_session_uuid' => $session->uuid,
                    'cached_user_uuid' => $user->uuid,
                    'type' => 'wallet_debit',
                    'amount' => $cost,
                    'status' => 'completed',
                    'idempotency_key' => 'edge-debit-'.$session->uuid,
                    'pending_sync' => true,
                    'occurred_at' => $stoppedAt,
                    'payload' => ['balance_after' => (float) $wallet->balance],
                ]);

                $this->outbox->enqueue('wallet.debited', [
                    'uuid' => $tx->uuid,
                    'session_uuid' => $session->uuid,
                    'cloud_user_uuid' => $user->cloud_user_uuid,
                    'amount' => (float) $cost,
                    'balance_after' => (float) $wallet->balance,
                    'currency' => $wallet->currency ?? 'IRR',
                    'description' => 'شارژ محلی ایستگاه',
                    'occurred_at' => $stoppedAt->toIso8601String(),
                ], 'wallet_transaction', $tx->uuid);
            }

            $this->outbox->enqueue('charging.session.stopped', $this->sessionPayload($session->fresh(), $user), 'charging_session', $session->uuid);
            $this->push->pushPending(10);

            return $session->fresh();
        });
    }

    /** @return array<string, mixed> */
    private function sessionPayload(EdgeChargingSession $session, CachedUser $user): array
    {
        return [
            'uuid' => $session->uuid,
            'cloud_user_uuid' => $user->cloud_user_uuid,
            'cached_user_uuid' => $user->uuid,
            'cloud_connector_uuid' => $session->meta['cloud_connector_uuid'] ?? $session->connector?->cloud_connector_uuid,
            'cloud_charger_uuid' => $session->meta['cloud_charger_uuid'] ?? $session->charger?->cloud_charger_uuid,
            'auth_method' => $session->auth_method,
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
            'stopped_at' => $session->stopped_at?->toIso8601String(),
            'energy_kwh' => (float) $session->energy_kwh,
            'duration_seconds' => (int) $session->duration_seconds,
            'cost_amount' => (float) $session->cost_amount,
            'currency' => $session->currency ?? 'IRR',
            'meta' => $session->meta,
        ];
    }

    private function estimateEnergyKwh(EdgeChargingSession $session, int $durationSeconds): float
    {
        $powerKw = (float) ($session->meta['power_kw'] ?? 22);

        return round($powerKw * ($durationSeconds / 3600) * 0.85, 3);
    }

    private function estimateCost(EdgeChargingSession $session, float $energyKwh): float
    {
        $tariff = $session->cached_tariff_uuid
            ? CachedTariff::where('uuid', $session->cached_tariff_uuid)->first()
            : CachedTariff::query()->first();

        $perKwh = (float) ($tariff?->price_per_kwh ?? 32000);
        $fee = (float) ($tariff?->session_fee ?? 0);

        return round($energyKwh * $perKwh + $fee, 2);
    }

    private function targetKwh(EdgeChargingSession $session): float
    {
        $powerKw = (float) ($session->meta['power_kw'] ?? 22);
        $targetSoc = (int) ($session->meta['target_soc'] ?? 80);

        return max(5, $powerKw * ($targetSoc / 100));
    }

    /** @return array{pct:int,energy:float,cost:int,secs:int,power:int,goal:int,eta:?int} */
    public function liveMetrics(EdgeChargingSession $session): array
    {
        $secs = (int) ($session->duration_seconds
            ?? ($session->started_at ? now()->diffInSeconds($session->started_at) : 0));
        $energy = (float) $session->energy_kwh > 0
            ? (float) $session->energy_kwh
            : $this->estimateEnergyKwh($session, $secs);
        $costRial = (float) $session->cost_amount > 0
            ? (float) $session->cost_amount
            : $this->estimateCost($session, $energy);
        $goal = (int) ($session->meta['target_soc'] ?? 80);
        $power = (int) ($session->meta['power_kw'] ?? $session->charger?->max_power_kw ?? 0);
        $targetKwh = $this->targetKwh($session);
        $pct = 10;
        if (isset($session->meta['soc']) && (float) $session->energy_kwh > 0) {
            $pct = min(99, max(5, (int) $session->meta['soc']));
        } elseif ($targetKwh > 0 && $energy > 0) {
            $pct = min(99, max(5, (int) round(($energy / $targetKwh) * 100)));
        } elseif ($secs > 0) {
            $pct = min(99, max(5, (int) round(10 + ($secs / 3600) * 40)));
        }

        $left = max(0, $goal - $pct);
        $eta = ($left > 0 && $power > 0)
            ? max(1, (int) round($left / max(1, $power) * 60 / max(1, $goal) * 10))
            : null;

        return [
            'pct' => $pct,
            'energy' => $energy,
            'cost' => (int) round($costRial / 10),
            'secs' => $secs,
            'power' => $power,
            'goal' => $goal,
            'eta' => $eta,
        ];
    }
}
