<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Partner;
use App\Models\Settlement;
use App\Models\Station;
use App\Models\User;
use App\Services\Finance\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'secret-password', 'role' => UserRole::SuperAdmin, 'status' => 'active']);
        $partner = Partner::create(['code' => 'P1', 'name' => 'Partner', 'revenue_share_percent' => 70, 'platform_fee_percent' => 30, 'settlement_cycle' => 'monthly', 'status' => 'active']);
        $this->station = Station::create(['code' => 'S1', 'name' => 'Station', 'partner_id' => $partner->id, 'status' => 'active']);
        $charger = Charger::create(['station_id' => $this->station->id, 'charge_point_id' => 'CP-X', 'status' => 'active']);
        $connector = Connector::create(['charger_id' => $charger->id, 'connector_number' => 1, 'ocpp_status' => 'Available', 'operational_status' => 'active']);

        foreach ([['stopped', 110_000, 'Remote'], ['active', 50_000, null], ['stopped', 0, 'Cancelled'], ['stopped', 220_000, 'EmergencyStop']] as [$status, $cost, $reason]) {
            ChargingSession::create([
                'uuid' => (string) Str::uuid(), 'station_id' => $this->station->id, 'charger_id' => $charger->id, 'connector_id' => $connector->id,
                'auth_method' => 'qr', 'status' => $status, 'started_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3),
                'energy_kwh' => $cost / 10000, 'cost_amount' => $cost, 'duration_seconds' => 1800, 'stop_reason' => $reason,
            ]);
        }
    }

    public function test_tax_and_sessions_exports(): void
    {
        $from = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $tax = $this->actingAs($this->admin)->get(route('admin.exports', 'tax').'?from='.$from.'&to='.$to);
        $tax->assertOk();
        $csv = $tax->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        // فقط جلسه‌های پایان‌یافته: 110000 + 0 + 220000 = 330000؛ خالص با ۱۰٪ مالیات = 300000
        $this->assertStringContainsString(',330000,300000,10,30000', $csv);

        $sessions = $this->actingAs($this->admin)->get(route('admin.exports', 'sessions').'?from='.$from.'&to='.$to);
        $this->assertSame(5, substr_count(trim($sessions->streamedContent()), "\n") + 1);
    }

    public function test_monthly_settlement_counts_only_finished_sessions(): void
    {
        $this->assertSame(1, app(SettlementService::class)->generateMonthly());
        $settlement = Settlement::firstOrFail();
        $this->assertEquals(330000, (float) $settlement->gross_revenue);
        $this->assertEquals(231000, (float) $settlement->partner_share);
        $this->assertSame(0, app(SettlementService::class)->generateMonthly(), 'no duplicates');
    }

    public function test_insights_and_alerts_pages_render(): void
    {
        $this->actingAs($this->admin)->get(route('admin.insights'))->assertOk()->assertSee('Station');
        $this->actingAs($this->admin)->get(route('admin.alerts.index'))->assertOk();
    }
}
