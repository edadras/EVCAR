<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tariff;
use App\Models\User;
use App\Services\Charging\TariffCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTariffRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_form_saves_peak_offpeak_rules_idle_fee_and_they_are_billed(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'a@example.test', 'password' => 'secret-password',
            'role' => UserRole::SuperAdmin, 'status' => 'active',
        ]);

        $this->actingAs($admin)->post(route('admin.tariffs.store'), [
            'name' => 'Smart', 'code' => 'SMART', 'pricing_model' => 'per_kwh',
            'price_per_kwh' => 10000, 'session_fee' => 0, 'status' => 'active', 'is_default' => 1,
            'peak_multiplier' => 1.5, 'peak_start' => '17:00', 'peak_end' => '23:00',
            'offpeak_multiplier' => 0.5, 'offpeak_start' => '00:00', 'offpeak_end' => '06:00',
            'idle_fee_per_minute' => 2000, 'idle_grace_minutes' => 5, 'member_discount_percent' => 10,
        ])->assertRedirect(route('admin.tariffs.index'));

        $tariff = Tariff::with('rules')->where('code', 'SMART')->firstOrFail();
        $this->assertCount(2, $tariff->rules);
        $this->assertEquals(2000, $tariff->meta['idle_fee_per_minute']);

        $calc = app(TariffCalculator::class);
        $at = fn (string $t) => CarbonImmutable::parse('2026-10-20 '.$t, 'Asia/Tehran');
        $this->assertEquals(150_000.0, $calc->cost($tariff, 10, 600, $at('18:00')));
        $this->assertEquals(50_000.0, $calc->cost($tariff, 10, 600, $at('03:00')));
        $this->assertEquals(100_000.0, $calc->cost($tariff, 10, 600, $at('12:00')));
        $this->assertEquals(90_000.0, $calc->cost($tariff, 10, 600, $at('12:00'), 0, true));
        $this->assertEquals(110_000.0, $calc->cost($tariff, 10, 600, $at('12:00'), 10 * 60));

        // حذف ضریب‌ها قوانین را پاک می‌کند
        $this->actingAs($admin)->put(route('admin.tariffs.update', $tariff), [
            'name' => 'Smart', 'code' => 'SMART', 'pricing_model' => 'per_kwh',
            'price_per_kwh' => 10000, 'status' => 'active',
        ])->assertRedirect();
        $this->assertCount(0, $tariff->fresh()->rules);
    }
}
