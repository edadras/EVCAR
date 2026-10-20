<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private SupportTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => '']);
        $this->admin = User::create(['name' => 'Agent', 'email' => 'agent@x.test', 'password' => 'secret-password', 'role' => UserRole::SuperAdmin, 'status' => 'active']);
        $this->customer = User::create(['name' => 'Reza', 'email' => 'reza@x.test', 'phone' => '09120000077', 'password' => 'secret-password', 'role' => UserRole::Customer, 'status' => 'active']);
        Wallet::create(['user_id' => $this->customer->id, 'balance' => 100_000, 'currency' => 'IRR']);
        $this->ticket = SupportTicket::create([
            'ticket_number' => 'TK-1001', 'user_id' => $this->customer->id, 'category' => 'payment',
            'priority' => 'normal', 'status' => 'open', 'subject' => 'کسر دوبارهٔ هزینه', 'body' => 'دو بار کسر شد',
        ]);
        TicketMessage::create(['support_ticket_id' => $this->ticket->id, 'user_id' => $this->customer->id, 'message' => 'دو بار کسر شد', 'is_staff_reply' => false]);
    }

    public function test_index_and_ticket_pages_render(): void
    {
        $this->actingAs($this->admin)->get(route('admin.support'))->assertOk()->assertSee('TK-1001');
        $this->actingAs($this->admin)->get(route('admin.support', ['status' => 'all', 'q' => 'Reza']))->assertOk()->assertSee('TK-1001');
        $this->actingAs($this->admin)->get(route('admin.support.ticket', $this->ticket))->assertOk()->assertSee('کسر دوبارهٔ هزینه')->assertSee('پروندهٔ مشتری');
        $conv = AiConversation::create(['user_id' => $this->customer->id, 'status' => 'escalated', 'support_ticket_id' => $this->ticket->id, 'title' => 'شارژ قطع شد', 'channel' => 'app', 'message_count' => 1, 'last_message_at' => now()]);
        $conv->messages()->create(['role' => 'user', 'content' => [['type' => 'text', 'text' => 'شارژ قطع شد']], 'display_text' => 'شارژ قطع شد', 'source' => 'user']);
        $this->actingAs($this->admin)->get(route('admin.support.ai'))->assertOk()->assertSee('شارژ قطع شد');
        $this->actingAs($this->admin)->get(route('admin.support.ai.show', $conv->uuid))->assertOk()->assertSee('TK-1001');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('"support":1', false);
    }

    public function test_reply_notifies_customer_and_internal_note_stays_hidden(): void
    {
        $this->actingAs($this->admin)->post(route('admin.support.reply', $this->ticket), ['message' => 'یادداشت محرمانه کارشناس', 'internal' => 1])->assertRedirect();
        $this->ticket->refresh();
        $this->assertNull($this->ticket->first_response_at);
        $this->assertSame(0, UserNotification::where('user_id', $this->customer->id)->count());

        $this->actingAs($this->admin)->post(route('admin.support.reply', $this->ticket), ['message' => 'بررسی شد، مبلغ برگشت می‌خورد'])->assertRedirect();
        $this->ticket->refresh();
        $this->assertNotNull($this->ticket->first_response_at);
        $this->assertSame('in_progress', $this->ticket->status);
        $this->assertSame($this->admin->id, $this->ticket->assigned_to);
        $this->assertSame(1, UserNotification::where('user_id', $this->customer->id)->count());

        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/mobile/auth/login', ['phone' => '09120000077', 'password' => 'secret-password'])->json('access_token');
        $res = $this->getJson('/api/v1/mobile/tickets/'.$this->ticket->id, ['Authorization' => 'Bearer '.$token])->assertOk();
        $body = json_encode($res->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('مبلغ برگشت', $body);
        $this->assertStringNotContainsString('محرمانه', $body);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->customer)->get(route('app.ticket', ['id' => $this->ticket->id]))->assertOk()
            ->assertSee('مبلغ برگشت')->assertDontSee('محرمانه');
    }

    public function test_credit_adds_to_wallet_and_resolve_notifies(): void
    {
        $this->actingAs($this->admin)->post(route('admin.support.credit', $this->ticket), ['amount' => 25_000, 'type' => 'refund', 'reason' => 'کسر تکراری'])->assertRedirect();
        $this->assertEquals(350_000, (float) Wallet::where('user_id', $this->customer->id)->value('balance'));
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'refund', 'amount' => 250_000]);
        $this->assertDatabaseHas('ticket_messages', ['support_ticket_id' => $this->ticket->id, 'is_internal' => true]);

        $this->actingAs($this->admin)->post(route('admin.support.update', $this->ticket), ['status' => 'resolved', 'priority' => 'high', 'category' => 'payment'])->assertRedirect();
        $this->assertNotNull($this->ticket->refresh()->resolved_at);
        $this->assertSame(['wallet_credit', 'ticket_resolved'], UserNotification::where('user_id', $this->customer->id)->orderBy('id')->pluck('type')->all());
    }

    public function test_draft_without_api_key_returns_template_and_canned_replies_are_editable(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.support.draft', $this->ticket))->assertOk()->assertJson(['ai' => false])->assertJsonStructure(['text']);

        $this->actingAs($this->admin)->post(route('admin.support.canned.store'), ['title' => 'شارژ نشد', 'text' => 'لطفاً کانکتور را جدا و دوباره وصل کنید.'])->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.support.ticket', $this->ticket))->assertSee('شارژ نشد');
        $this->actingAs($this->admin)->post(route('admin.support.ai.settings'), ['instructions' => 'ساعت کاری ۸ تا ۲۰'])->assertRedirect();
        $this->actingAs($this->admin)->get(route('admin.support.ai'))->assertSee('ساعت کاری ۸ تا ۲۰');
    }
}
