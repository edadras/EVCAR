<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Payer', 'email' => 'p@example.test', 'phone' => '09120000077',
            'password' => 'secret-password', 'role' => UserRole::Customer, 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->user->id, 'balance' => 0, 'currency' => 'IRR']);
    }

    private function token(): array
    {
        $t = $this->postJson('/api/v1/mobile/auth/login', ['phone' => '09120000077', 'password' => 'secret-password'])->json('access_token');

        return ['Authorization' => 'Bearer '.$t];
    }

    public function test_zarinpal_request_redirect_verify_credits_wallet_once(): void
    {
        config(['safircharge.payment.driver' => 'zarinpal', 'safircharge.payment.zarinpal.merchant_id' => 'MERCHANT']);
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000012345']]),
            '*/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 987654, 'card_pan' => '6037****1234']]),
        ]);
        $auth = $this->token();

        $this->getJson('/api/v1/mobile/wallet', $auth)->assertJsonCount(1, 'gateways')->assertJsonPath('gateways.0.id', 'zarin');
        $this->postJson('/api/v1/mobile/topup', ['amount' => 50000, 'gateway' => 'mellat'], $auth)->assertStatus(422);

        $res = $this->postJson('/api/v1/mobile/topup', ['amount' => 50000, 'gateway' => 'zarin'], $auth)->assertOk();
        $this->assertSame('https://payment.zarinpal.com/pg/StartPay/A0000000000000000000000000000012345', $res->json('url'));
        $payment = Payment::firstOrFail();
        $this->assertSame($payment->uuid, $res->json('payment'));
        $this->assertSame('pending', $payment->status);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'request.json') && $r['amount'] === 500000 && $r['merchant_id'] === 'MERCHANT');

        // تقلب: Authority نادرست
        $this->get('/payment/callback/'.$payment->uuid.'?Authority=WRONG&Status=OK')->assertOk();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(0.0, (float) $this->user->wallet()->first()->balance);

        $payment->refresh()->update(['status' => 'pending']);
        $this->get('/payment/callback/'.$payment->uuid.'?Authority=A0000000000000000000000000000012345&Status=OK')
            ->assertOk()->assertSee('پرداخت موفق بود');
        $this->get('/payment/callback/'.$payment->uuid.'?Authority=A0000000000000000000000000000012345&Status=OK')->assertOk();

        $this->assertSame(500000.0, (float) $this->user->wallet()->first()->balance);
        $this->getJson('/api/v1/mobile/payments/'.$payment->uuid, $auth)
            ->assertJsonPath('status', 'paid')->assertJsonPath('reference', '987654')->assertJsonPath('balance', 50000);
    }

    public function test_cancelled_payment_does_not_credit(): void
    {
        config(['safircharge.payment.driver' => 'zarinpal', 'safircharge.payment.zarinpal.merchant_id' => 'M']);
        Http::fake(['*/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'AUTH1']])]);
        $this->postJson('/api/v1/mobile/topup', ['amount' => 20000, 'gateway' => 'zarin'], $this->token())->assertOk();
        $payment = Payment::firstOrFail();

        $this->get('/payment/callback/'.$payment->uuid.'?Authority=AUTH1&Status=NOK')->assertOk()->assertSee('پرداخت انجام نشد');
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame(0.0, (float) $this->user->wallet()->first()->balance);
    }

    public function test_simulated_driver_is_refused_in_production(): void
    {
        $auth = $this->token();
        app()->detectEnvironment(fn () => 'production');
        $this->postJson('/api/v1/mobile/topup', ['amount' => 20000, 'gateway' => 'mellat'], $auth)->assertStatus(422);
        $this->assertSame(0, Payment::count());
    }
}
