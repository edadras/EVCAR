<?php

namespace App\Services\Sync\Handlers;

use App\Models\ChargingSession;
use App\Models\EdgeStation;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;

class WalletDebitedHandler
{
    /** @param  array<string, mixed>  $payload */
    public function handle(EdgeStation $edge, array $payload): WalletTransaction
    {
        $uuid = (string) ($payload['uuid'] ?? '');
        if ($uuid && WalletTransaction::where('uuid', $uuid)->exists()) {
            return WalletTransaction::where('uuid', $uuid)->first();
        }

        $user = User::where('uuid', $payload['cloud_user_uuid'] ?? '')->first();
        if (! $user) {
            throw new \RuntimeException('کاربر برای برداشت کیف پول یافت نشد.');
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['uuid' => (string) Str::uuid(), 'balance' => 0, 'currency' => 'IRR']
        );

        $amount = (float) ($payload['amount'] ?? 0);
        $balanceAfter = isset($payload['balance_after'])
            ? (float) $payload['balance_after']
            : max(0, (float) $wallet->balance - $amount);

        $wallet->update(['balance' => $balanceAfter]);

        $sessionId = null;
        if (! empty($payload['session_uuid'])) {
            $sessionId = ChargingSession::where('edge_session_uuid', $payload['session_uuid'])->value('id');
        }

        return WalletTransaction::create([
            'uuid' => $uuid ?: (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => -abs($amount),
            'balance_after' => $balanceAfter,
            'reference_type' => $sessionId ? ChargingSession::class : null,
            'reference_id' => $sessionId,
            'edge_station_uuid' => $edge->uuid,
            'idempotency_key' => 'edge-'.$uuid,
            'description' => $payload['description'] ?? 'شارژ محلی ایستگاه',
            'occurred_at' => $payload['occurred_at'] ?? now(),
            'meta' => ['synced_from_edge' => true],
        ]);
    }
}
