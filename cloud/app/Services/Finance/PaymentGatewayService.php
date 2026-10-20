<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * شارژ کیف پول از درگاه بانکی.
 *
 * درایورها (config safircharge.payment.driver):
 *  - simulated: بدون درگاه واقعی؛ پرداخت بلافاصله موفق ثبت می‌شود (فقط توسعه/دمو).
 *  - zarinpal: درگاه زرین‌پال (API نسخهٔ ۴): درخواست → هدایت کاربر به StartPay → بازگشت به callback → verify.
 *
 * واریز به کیف پول فقط یک بار و پس از تأیید (verify) انجام می‌شود.
 */
class PaymentGatewayService
{
    public function driver(): string
    {
        return (string) config('safircharge.payment.driver', 'simulated');
    }

    /** @return list<array{id:string,label:string,logo:string}> */
    public function gateways(): array
    {
        $all = [
            'mellat' => ['id' => 'mellat', 'label' => 'به‌پرداخت ملت', 'logo' => 'به'],
            'saman' => ['id' => 'saman', 'label' => 'سامان (سپ)', 'logo' => 'سپ'],
            'zarin' => ['id' => 'zarin', 'label' => 'زرین‌پال', 'logo' => 'زر'],
        ];

        // با درگاه واقعی فقط همان درگاهی نمایش داده می‌شود که قرارداد و کد پذیرنده دارد.
        return $this->driver() === 'zarinpal' ? [$all['zarin']] : array_values($all);
    }

    /**
     * @return array{payment: Payment, redirect_url: ?string}
     *                                                        redirect_url برای درگاه واقعی آدرس صفحهٔ بانک است؛ برای حالت شبیه‌سازی null (پرداخت انجام شده).
     */
    public function start(User $user, int $amountToman, string $gateway, string $source = 'web'): array
    {
        $alias = config('safircharge.payment.gateway_aliases.'.$gateway, $gateway);
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'method' => 'gateway',
            'status' => 'pending',
            'amount' => $amountToman * 10,
            'currency' => 'IRR',
            'gateway' => $this->driver() === 'zarinpal' ? 'zarinpal' : $alias,
            'gateway_payload' => ['source' => $source, 'driver' => $this->driver()],
        ]);

        if ($this->driver() !== 'zarinpal') {
            $this->markPaid($payment, 'SC-'.Str::upper(Str::substr(str_replace('-', '', (string) Str::uuid()), 0, 12)));

            return ['payment' => $payment->fresh(), 'redirect_url' => null];
        }

        $response = Http::timeout(20)->acceptJson()->post($this->zarinpalBase().'/pg/v4/payment/request.json', [
            'merchant_id' => (string) config('safircharge.payment.zarinpal.merchant_id'),
            'amount' => (int) $payment->amount,
            'currency' => 'IRR',
            'callback_url' => route('payment.callback', ['payment' => $payment->uuid]),
            'description' => 'شارژ کیف پول SafirCharge',
            'metadata' => array_filter(['mobile' => $user->phone, 'email' => $user->email]),
        ]);

        $authority = $response->json('data.authority');
        if (! $response->successful() || (int) $response->json('data.code') !== 100 || ! $authority) {
            $payment->update(['status' => 'failed', 'gateway_payload' => array_merge($payment->gateway_payload ?? [], ['request_error' => $response->json('errors') ?: $response->status()])]);
            Log::warning('zarinpal request failed', ['payment' => $payment->uuid, 'body' => $response->body()]);

            throw new \RuntimeException('اتصال به درگاه پرداخت برقرار نشد. لطفاً دوباره تلاش کنید.');
        }

        $payment->update(['gateway_payload' => array_merge($payment->gateway_payload ?? [], ['authority' => $authority])]);

        return ['payment' => $payment->fresh(), 'redirect_url' => $this->zarinpalBase().'/pg/StartPay/'.$authority];
    }

    /** بازگشت از درگاه: تأیید تراکنش و واریز به کیف پول. */
    public function verify(Payment $payment, ?string $authority, ?string $status): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }

        $expected = $payment->gateway_payload['authority'] ?? null;
        if (! $authority || ! $expected || ! hash_equals((string) $expected, $authority)) {
            $payment->update(['status' => 'failed']);

            return $payment->fresh();
        }

        if ($status !== 'OK') {
            $payment->update(['status' => 'cancelled']);

            return $payment->fresh();
        }

        $response = Http::timeout(20)->acceptJson()->post($this->zarinpalBase().'/pg/v4/payment/verify.json', [
            'merchant_id' => (string) config('safircharge.payment.zarinpal.merchant_id'),
            'amount' => (int) $payment->amount,
            'authority' => $authority,
        ]);

        $code = (int) $response->json('data.code');
        if ($response->successful() && in_array($code, [100, 101], true)) {
            $this->markPaid($payment, (string) $response->json('data.ref_id'), [
                'card_pan' => $response->json('data.card_pan'),
                'verify_code' => $code,
            ]);
        } else {
            $payment->update([
                'status' => 'failed',
                'gateway_payload' => array_merge($payment->gateway_payload ?? [], ['verify_error' => $response->json('errors') ?: $response->status()]),
            ]);
        }

        return $payment->fresh();
    }

    /** @param  array<string, mixed>  $extra */
    private function markPaid(Payment $payment, string $reference, array $extra = []): void
    {
        DB::transaction(function () use ($payment, $reference, $extra) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($locked->status === 'paid') {
                return;
            }

            $locked->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_reference' => $reference,
                'gateway_payload' => array_merge($locked->gateway_payload ?? [], array_filter($extra, fn ($v) => $v !== null)),
            ]);

            $wallet = Wallet::where('user_id', $locked->user_id)->lockForUpdate()->first()
                ?? Wallet::create(['uuid' => (string) Str::uuid(), 'user_id' => $locked->user_id, 'balance' => 0, 'currency' => 'IRR', 'status' => 'active']);
            $newBalance = (float) $wallet->balance + (float) $locked->amount;
            $wallet->update(['balance' => $newBalance]);

            WalletTransaction::create([
                'uuid' => (string) Str::uuid(),
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $locked->amount,
                'balance_after' => $newBalance,
                'reference_type' => Payment::class,
                'reference_id' => $locked->id,
                'description' => 'شارژ کیف پول از درگاه',
                'occurred_at' => now(),
            ]);
        });
    }

    private function zarinpalBase(): string
    {
        return config('safircharge.payment.zarinpal.sandbox') ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
    }
}
