<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Partner;
use App\Models\SessionMeterValue;
use App\Models\Station;
use App\Models\StationReview;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerEngagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Station $station;

    private Connector $connector;

    private ChargingSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Sara Mohammadi', 'email' => 's@x.test', 'phone' => '09120000066', 'password' => 'secret-password', 'role' => UserRole::Customer, 'status' => 'active']);
        Wallet::create(['user_id' => $this->user->id, 'balance' => 500_000, 'currency' => 'IRR']);
        $partner = Partner::create(['code' => 'P1', 'name' => 'Partner', 'revenue_share_percent' => 70, 'platform_fee_percent' => 30, 'settlement_cycle' => 'monthly', 'status' => 'active']);
        $this->station = Station::create(['code' => 'ST-ENG', 'name' => 'Eng Station', 'partner_id' => $partner->id, 'status' => 'active']);
        $charger = Charger::create(['station_id' => $this->station->id, 'charge_point_id' => 'CP-ENG', 'status' => 'active', 'is_online' => true]);
        $this->connector = Connector::create(['charger_id' => $charger->id, 'connector_number' => 1, 'ocpp_status' => 'Charging', 'operational_status' => 'active']);
        $this->session = ChargingSession::create([
            'uuid' => (string) Str::uuid(), 'station_id' => $this->station->id, 'charger_id' => $charger->id, 'connector_id' => $this->connector->id,
            'user_id' => $this->user->id, 'auth_method' => 'qr', 'status' => 'completed', 'started_at' => now()->subHour(), 'stopped_at' => now()->subMinutes(20),
            'energy_kwh' => 12.5, 'cost_amount' => 400_000, 'duration_seconds' => 2400,
        ]);
        foreach ([5, 15, 30] as $i => $min) {
            SessionMeterValue::create(['charging_session_id' => $this->session->id, 'sampled_at' => now()->subHour()->addMinutes($min), 'energy_wh' => 3000 * ($i + 1), 'power_w' => 20000 - $i * 4000]);
        }
    }

    private function api(): array
    {
        $t = $this->postJson('/api/v1/mobile/auth/login', ['phone' => '09120000066', 'password' => 'secret-password'])->json('access_token');

        return ['Authorization' => 'Bearer '.$t];
    }

    public function test_receipt_and_review_update_station_rating(): void
    {
        $h = $this->api();
        $r = $this->getJson('/api/v1/mobile/sessions/'.$this->session->uuid, $h)->assertOk();
        $this->assertSame(40000, $r->json('total'));
        $this->assertTrue($r->json('can_review'));
        $this->assertCount(3, $r->json('curve'));
        $this->assertEquals(20.0, $r->json('curve.0.kw'));

        $this->postJson('/api/v1/mobile/sessions/'.$this->session->uuid.'/review', ['rating' => 4, 'comment' => 'خوب بود', 'tags' => ['fast', 'clean']], $h)
            ->assertOk()->assertJsonPath('receipt.review.rating', 4)->assertJsonPath('receipt.can_review', false);
        $this->assertEquals(4.0, $this->station->fresh()->meta['rating']);
        $this->assertSame(1, $this->station->fresh()->meta['reviews']);

        $reviews = $this->getJson('/api/v1/mobile/stations/ST-ENG/reviews', $h)->assertOk();
        $this->assertSame('Sara M.', $reviews->json('items.0.author'));
        $this->assertSame(1, $reviews->json('distribution.4'));

        $charger = $this->getJson('/api/v1/mobile/charger?station=ST-ENG', $h)->assertOk();
        $this->assertSame(1, $charger->json('reviews.count'));
        $this->assertArrayHasKey('not_starting', $charger->json('problems'));
    }

    public function test_low_rating_with_broken_tag_opens_ticket_and_admin_can_reply(): void
    {
        $this->postJson('/api/v1/mobile/sessions/'.$this->session->uuid.'/review', ['rating' => 1, 'tags' => ['broken'], 'comment' => 'کابل خراب بود'], $this->api())->assertOk();
        $ticket = SupportTicket::where('station_id', $this->station->id)->firstOrFail();
        $this->assertSame('review', $ticket->source);
        $this->assertSame('high', $ticket->priority);

        $admin = User::create(['name' => 'Ops', 'email' => 'ops@x.test', 'password' => 'secret-password', 'role' => UserRole::SuperAdmin, 'status' => 'active']);
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->get(route('admin.support.reviews', ['filter' => 'low']))->assertOk()->assertSee('کابل خراب بود');
        $review = StationReview::firstOrFail();
        $this->actingAs($admin)->post(route('admin.support.reviews.update', $review), ['reply' => 'کابل تعویض شد، ممنون از گزارش.'])->assertRedirect();
        $this->assertSame(1, UserNotification::where('user_id', $this->user->id)->where('type', 'review_reply')->count());
        $this->actingAs($admin)->post(route('admin.support.reviews.update', $review), ['is_hidden' => 1])->assertRedirect();
        $this->assertSame(0, $this->station->fresh()->meta['reviews']);
    }

    public function test_report_problem_merges_duplicates(): void
    {
        $h = $this->api();
        $first = $this->postJson('/api/v1/mobile/stations/ST-ENG/report', ['problem' => 'not_starting', 'connector' => 'A1', 'description' => 'خطای E12'], $h)->assertOk();
        $this->postJson('/api/v1/mobile/stations/ST-ENG/report', ['problem' => 'slow'], $h)->assertOk()->assertJsonPath('ticket.id', $first->json('ticket.id'));
        $ticket = SupportTicket::findOrFail($first->json('ticket.id'));
        $this->assertSame('high', $ticket->priority);
        $this->assertSame('station', $ticket->category);
        $this->assertSame(2, $ticket->messages()->count());
    }

    public function test_watch_notifies_once_when_connector_becomes_free(): void
    {
        $h = $this->api();
        $this->postJson('/api/v1/mobile/stations/ST-ENG/watch', [], $h)->assertOk()->assertJson(['watching' => true, 'free' => 0]);
        $this->artisan('stations:notify-watchers')->assertSuccessful();
        $this->assertSame(0, UserNotification::where('type', 'station_free')->count());

        $this->connector->update(['ocpp_status' => 'Available']); // رویداد مدل → اعلان فوری
        $this->assertSame(1, UserNotification::where('type', 'station_free')->count());
        $this->artisan('stations:notify-watchers')->assertSuccessful();
        $this->assertSame(1, UserNotification::where('type', 'station_free')->count());
    }

    public function test_insights_and_web_pages(): void
    {
        $ins = $this->getJson('/api/v1/mobile/insights', $this->api())->assertOk();
        $this->assertCount(6, $ins->json('months'));
        $this->assertSame(40000, $ins->json('totals.cost'));
        $this->assertSame(1, $ins->json('totals.sessions'));

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->user)->get(route('app.session', $this->session->uuid))->assertOk()->assertSee('رسید جلسهٔ شارژ')->assertSee('تجربهٔ شما چطور بود؟');
        $this->actingAs($this->user)->post(route('app.session.review', $this->session->uuid), ['rating' => 5])->assertRedirect(route('app.session', $this->session->uuid));
        $this->actingAs($this->user)->get(route('app.insights'))->assertOk()->assertSee('تحلیل هزینه و مصرف');
        $this->actingAs($this->user)->get(route('app.charger', ['station' => 'ST-ENG']))->assertOk()->assertSee('امتیاز و نظرات')->assertSee('خبرم کن');
        $this->actingAs($this->user)->get(route('app.history'))->assertOk()->assertSee($this->session->uuid);
        $this->actingAs($this->user)->postJson(route('app.station.favorite', 'ST-ENG'))->assertOk()->assertJson(['favorite' => true]);
        $this->actingAs($this->user)->post(route('app.station.report', 'ST-ENG'), ['problem' => 'blocked'])->assertRedirect();
        $this->assertSame('web', SupportTicket::latest('id')->first()->source);

        $other = User::create(['name' => 'X', 'email' => 'o@x.test', 'phone' => '09120000055', 'password' => 'secret-password', 'role' => UserRole::Customer, 'status' => 'active']);
        $this->actingAs($other)->get(route('app.session', $this->session->uuid))->assertNotFound();
    }
}
