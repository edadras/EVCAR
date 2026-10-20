<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSupportTest extends TestCase
{
    use RefreshDatabase;

    /** @var resource|null */
    private static $server = null;

    private static int $port = 0;

    private User $user;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$port = random_int(20000, 40000);
        $cmd = sprintf('exec php -S 127.0.0.1:%d %s', self::$port, escapeshellarg(__DIR__.'/../Fixtures/fake-anthropic.php'));
        self::$server = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['FAKE_ANTHROPIC_DIR' => sys_get_temp_dir(), 'PATH' => getenv('PATH')]);
        for ($i = 0; $i < 50 && ! @fsockopen('127.0.0.1', self::$port); $i++) {
            usleep(100_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key', 'services.anthropic.base_url' => 'http://127.0.0.1:'.self::$port]);
        $this->user = User::create([
            'name' => 'Sara', 'email' => 'ai@example.test', 'phone' => '09120000088',
            'password' => 'secret-password', 'role' => UserRole::Customer, 'status' => 'active',
        ]);
        Wallet::create(['user_id' => $this->user->id, 'balance' => 1_234_560, 'currency' => 'IRR']);
    }

    private function auth(): array
    {
        $t = $this->postJson('/api/v1/mobile/auth/login', ['phone' => '09120000088', 'password' => 'secret-password'])->json('access_token');

        return ['Authorization' => 'Bearer '.$t];
    }

    private function lastRequest(): array
    {
        return json_decode((string) file_get_contents(sys_get_temp_dir().'/fake-anthropic-last.json'), true);
    }

    public function test_assistant_answers_from_account_tool_and_replays_history_verbatim(): void
    {
        $auth = $this->auth();
        $res = $this->postJson('/api/v1/mobile/assistant', ['message' => 'موجودی کیف پولم چقدر است؟'], $auth)->assertOk();

        $texts = collect($res->json('messages'))->pluck('text')->all();
        $this->assertSame('موجودی کیف پولم چقدر است؟', $texts[0]);
        $this->assertStringContainsString('123456', end($texts)); // ۱٬۲۳۴٬۵۶۰ ریال = ۱۲۳٬۴۵۶ تومان از ابزار
        $this->assertContains('get_account_summary', collect($res->json('messages'))->pluck('tools')->flatten()->all());

        $req = $this->lastRequest();
        $this->assertSame('claude-opus-5-5', $req['model']);
        $this->assertSame('default', $req['fallbacks']);
        $this->assertSame('ephemeral', $req['system'][0]['cache_control']['type']);
        // نوبت دستیار (thinking + tool_use) بدون تغییر بازپخش شده و نتیجهٔ ابزار با tool_use_id آمده است
        $this->assertSame(['thinking', 'text', 'tool_use'], array_column($req['messages'][1]['content'], 'type'));
        $this->assertSame('sig-b', $req['messages'][1]['content'][0]['signature']);
        $this->assertSame('toolu_a1', $req['messages'][2]['content'][0]['tool_use_id']);

        // پیام دوم در همان گفتگو: تاریخچهٔ قبلی دقیقاً تکرار می‌شود (فقط اضافه)
        $this->postJson('/api/v1/mobile/assistant', ['message' => 'سلام', 'conversation' => $res->json('conversation')], $auth)->assertOk();
        $req2 = $this->lastRequest();
        $this->assertSame(array_slice($req['messages'], 0, 3), array_slice($req2['messages'], 0, 3));
        $this->assertCount(5, $req2['messages']);
    }

    public function test_escalation_tool_creates_ticket_with_transcript(): void
    {
        $res = $this->postJson('/api/v1/mobile/assistant', ['message' => 'هزینه دوبار کم شده، می‌خواهم با کارشناس صحبت کنم'], $this->auth())->assertOk();

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame('ai', $ticket->source);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame('payment', $ticket->category);
        $this->assertStringContainsString('متن گفتگو با دستیار هوشمند', $ticket->messages()->first()->message);
        $this->assertSame('escalated', AiConversation::first()->status);
        $this->assertSame($ticket->ticket_number, $res->json('ticket.number'));
    }

    public function test_refusal_and_manual_escalation_and_web_app_route(): void
    {
        $auth = $this->auth();
        $res = $this->postJson('/api/v1/mobile/assistant', ['message' => 'یک چیز ممنوع'], $auth)->assertOk();
        $this->assertStringContainsString('کارشناس', collect($res->json('messages'))->last()['text']);

        $this->postJson('/api/v1/mobile/assistant/escalate', ['conversation' => $res->json('conversation')], $auth)
            ->assertOk()->assertJsonPath('status', 'escalated');
        $this->assertSame(1, SupportTicket::count());

        $this->actingAs($this->user)->get('/app/assistant')->assertOk()->assertSee('دستیار');
    }

    public function test_without_api_key_answers_from_faq(): void
    {
        config(['services.anthropic.key' => '']);
        $res = $this->postJson('/api/v1/mobile/assistant', ['message' => 'چگونه کیف پولم را شارژ کنم'], $this->auth())->assertOk();
        $this->assertFalse($res->json('ai_enabled'));
        $this->assertStringContainsString('افزایش موجودی', collect($res->json('messages'))->last()['text']);
    }
}
