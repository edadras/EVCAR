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
        if ($uuid && ($existing = WalletTransaction::where('uuid', $uuid)->first())) {
            return $existing;
        }

        $user = User::where('uuid', $payload['cloud_user_uuid'] ?? '')->first();
        if (! $user) {
            throw new \RuntimeException('کاربر برای برداشت کیف پول یافت نشد.');
        }

        Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['uuid' => (string) Str::uuid(), 'balance' => 0, 'currency' => 'IRR']
        );

        // موجودی ابر منبع حقیقت است: مبلغ از موجودی فعلی کم می‌شود. «balance_after» ارسالی
        // Edge بر پایهٔ کش محلی است و ممکن است شارژهای آنلاینِ بعد از آخرین سینک را نداشته باشد.
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
        $amount = abs((float) ($payload['amount'] ?? 0));
        $balanceAfter = round((float) $wallet->balance - $amount, 2);
        $wallet->update(['balance' => $balanceAfter]);

        $sessionId = null;
        if (! empty($payload['session_uuid'])) {
            $sessionId = ChargingSession::where('edge_session_uuid', $payload['session_uuid'])->value('id');
        }
        if (! $sessionId && ! empty($payload['cloud_session_uuid'])) {
            $sessionId = ChargingSession::where('uuid', $payload['cloud_session_uuid'])->value('id');
        }

        return WalletTransaction::create([
            'uuid' => $uuid ?: (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'amount' => $amount,
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
