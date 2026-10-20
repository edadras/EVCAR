<?php

namespace App\Services\Support;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\App\MobileAppPresenter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * دستیار هوشمند پشتیبانی مشتریان (Claude) برای وب‌اپ و اپ موبایل.
 *
 * - پاسخ‌ها بر پایهٔ دادهٔ واقعی حساب از طریق ابزارهای فقط‌خواندنی (AiSupportTools)؛
 * - ارجاع به کارشناس انسانی با ثبت تیکت و پیوست متن گفتگو؛
 * - تاریخچه فقط اضافه می‌شود و محتوای هر نوبت دقیقاً همان‌طور که از API آمده دوباره فرستاده می‌شود؛
 * - بدون کلید API (یا هنگام خطا) پاسخ از پرسش‌های متداول و پیشنهاد گفتگو با کارشناس.
 */
class AiSupportService
{
    private const MAX_TOOL_ROUNDS = 6;

    /** گفتگوهای طولانی‌تر در گفتگوی تازه ادامه می‌یابند (تاریخچهٔ قبلی هرگز کوتاه یا ویرایش نمی‌شود). */
    private const MAX_HISTORY_MESSAGES = 80;

    public function __construct(
        private AiSupportTools $tools,
        private MobileAppPresenter $presenter,
    ) {}

    public function enabled(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }

    public function conversationFor(User $user, ?string $uuid = null, string $channel = 'app'): AiConversation
    {
        if ($uuid) {
            $found = AiConversation::where('uuid', $uuid)->where('user_id', $user->id)->first();
            if ($found) {
                return $found;
            }
        }

        return AiConversation::where('user_id', $user->id)->where('status', 'active')->latest('last_message_at')->first()
            ?? AiConversation::create(['user_id' => $user->id, 'channel' => $channel, 'status' => 'active']);
    }

    public function startNew(User $user, string $channel = 'app'): AiConversation
    {
        AiConversation::where('user_id', $user->id)->where('status', 'active')->update(['status' => 'closed']);

        return AiConversation::create(['user_id' => $user->id, 'channel' => $channel, 'status' => 'active']);
    }

    /** @return array<string, mixed> */
    public function payload(AiConversation $conversation): array
    {
        $conversation->loadMissing('ticket');

        return [
            'conversation' => $conversation->uuid,
            'status' => $conversation->status,
            'ai_enabled' => $this->enabled(),
            'ticket' => $conversation->ticket ? ['id' => $conversation->ticket->id, 'number' => $conversation->ticket->ticket_number] : null,
            'messages' => $conversation->messages()->whereNotNull('display_text')->get()->map(fn (AiMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'text' => $m->display_text,
                'tools' => $m->tools_used ?? [],
                'time' => $m->created_at?->format('H:i'),
            ])->values(),
            'suggestions' => $this->suggestions(),
        ];
    }

    /** @return list<string> */
    public function suggestions(): array
    {
        return [
            'موجودی کیف پولم چقدر است؟',
            'آخرین شارژم چقدر هزینه داشت؟',
            'نزدیک‌ترین ایستگاه آزاد کجاست؟',
            'شارژ شروع نمی‌شود، چه کار کنم؟',
            'با کارشناس صحبت کنم',
        ];
    }

    /**
     * پیام مشتری را می‌فرستد و پاسخ دستیار را برمی‌گرداند.
     *
     * @return array<string, mixed>
     */
    public function send(User $user, AiConversation $conversation, string $text): array
    {
        $text = trim(Str::limit($text, 2000, ''));
        $this->guardRate($user);

        if ($conversation->status !== 'active' || $conversation->messages()->count() >= self::MAX_HISTORY_MESSAGES) {
            $conversation = $this->startNew($user, $conversation->channel);
        }

        $this->store($conversation, 'user', $text, $text, source: 'user');
        if (! $conversation->title) {
            $conversation->update(['title' => Str::limit($text, 60)]);
        }

        if (! $this->enabled()) {
            $reply = $this->fallbackReply($text);
            $this->store($conversation, 'assistant', [['type' => 'text', 'text' => $reply]], $reply, source: 'fallback');
            $this->touch($conversation);

            return $this->payload($conversation->fresh());
        }

        try {
            $this->runModel($user, $conversation);
        } catch (RateLimitException|APIConnectionException|APIStatusException $e) {
            Log::warning('AI support call failed', ['conversation' => $conversation->uuid, 'error' => $e->getMessage()]);
            $reply = 'در حال حاضر دستیار هوشمند در دسترس نیست. '.$this->fallbackReply($text);
            // نوبت ناقص (پرسش بی‌پاسخ ابزار) را نمی‌سازیم؛ فقط یک پاسخ متنی ساده اضافه می‌شود.
            $this->store($conversation, 'assistant', [['type' => 'text', 'text' => $reply]], $reply, source: 'fallback');
        }
        $this->touch($conversation);

        return $this->payload($conversation->fresh());
    }

    /** حلقهٔ ابزار: تا پاسخ نهایی یا سقف دور ابزار. */
    private function runModel(User $user, AiConversation $conversation): void
    {
        $client = new Client(
            apiKey: (string) config('services.anthropic.key'),
            baseUrl: config('services.anthropic.base_url') ?: null,
            requestOptions: ['timeout' => 60, 'maxRetries' => 2],
        );
        $messages = $this->history($conversation);

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $client->beta->messages->create(
                maxTokens: 4096,
                messages: $messages,
                model: (string) config('services.anthropic.support_model', 'claude-opus-5-5'),
                system: [['type' => 'text', 'text' => $this->systemPrompt(), 'cacheControl' => ['type' => 'ephemeral']]],
                tools: $this->tools->definitions(),
                outputConfig: ['effort' => (string) config('services.anthropic.support_effort', 'medium')],
                // اگر مدل به‌دلیل سیاست‌ها پاسخ ندهد، سرور همان درخواست را روی مدل جایگزین پیش‌فرض اجرا می‌کند.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
                metadata: ['userID' => 'u-'.$user->id],
            );

            $conversation->increment('input_tokens', (int) ($response->usage->inputTokens ?? 0));
            $conversation->increment('output_tokens', (int) ($response->usage->outputTokens ?? 0));

            $content = json_decode(json_encode($response->content), true) ?: [];
            $text = $this->textOf($response);
            $toolUses = array_values(array_filter($response->content, fn ($b) => $b instanceof BetaToolUseBlock));

            if ($response->stopReason === 'refusal') {
                $reply = 'متأسفم، نمی‌توانم به این درخواست پاسخ دهم. اگر مشکلی در شارژ یا حساب خود دارید، می‌توانم آن را برای کارشناس پشتیبانی ثبت کنم.';
                $this->store($conversation, 'assistant', [['type' => 'text', 'text' => $reply]], $reply, source: 'fallback');

                return;
            }

            if ($response->stopReason !== 'tool_use' || $toolUses === []) {
                $this->store($conversation, 'assistant', $content, $text !== '' ? $text : 'متوجه نشدم؛ لطفاً سؤال را دوباره بپرسید.');

                return;
            }

            $this->store($conversation, 'assistant', $content, $text !== '' ? $text : null, array_map(fn ($b) => $b->name, $toolUses));
            $messages[] = ['role' => 'assistant', 'content' => $content];

            $results = [];
            foreach ($toolUses as $block) {
                try {
                    $output = $this->tools->run($block->name, is_array($block->input) ? $block->input : [], $user, $conversation->fresh());
                    $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => json_encode($output, JSON_UNESCAPED_UNICODE)];
                } catch (\Throwable $e) {
                    report($e);
                    $results[] = ['type' => 'tool_result', 'toolUseID' => $block->id, 'content' => 'Tool failed: internal error', 'isError' => true];
                }
            }
            // همهٔ نتایج ابزار در یک پیام کاربر
            $this->store($conversation, 'user', $results, null, source: 'model');
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        $reply = 'بررسی این مورد بیش از حد طول کشید. می‌توانم درخواست شما را برای کارشناس پشتیبانی ثبت کنم.';
        $this->store($conversation, 'assistant', [['type' => 'text', 'text' => $reply]], $reply, source: 'fallback');
    }

    /** @return list<array<string, mixed>> تاریخچه دقیقاً همان‌طور که ذخیره شده (فقط اضافه‌شونده، بدون ویرایش یا حذف نوبت‌های قبلی). */
    private function history(AiConversation $conversation): array
    {
        return $conversation->messages()->get()
            ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => $m->content])->values()->all();
    }

    private function textOf(BetaMessage $response): string
    {
        $parts = [];
        foreach ($response->content as $block) {
            if (($block->type ?? null) === 'text') {
                $parts[] = $block->text;
            }
        }

        return trim(implode("\n", $parts));
    }

    /** @param  string|array<int, mixed>  $content */
    private function store(AiConversation $conversation, string $role, string|array $content, ?string $display, array $tools = [], string $source = 'model'): AiMessage
    {
        return AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'display_text' => $display,
            'tools_used' => $tools ?: null,
            'source' => $source,
        ]);
    }

    private function touch(AiConversation $conversation): void
    {
        $conversation->update([
            'last_message_at' => now(),
            'message_count' => $conversation->messages()->whereNotNull('display_text')->count(),
        ]);
    }

    private function guardRate(User $user): void
    {
        $key = 'ai-support:'.$user->id.':'.now()->format('YmdH');
        $count = Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, 1, now()->addHour());
        }
        if ($count > (int) config('services.anthropic.support_hourly_limit', 40)) {
            throw new \RuntimeException('تعداد پیام‌ها در این ساعت زیاد است؛ کمی بعد دوباره تلاش کنید یا درخواست پشتیبانی ثبت کنید.');
        }
    }

    /**
     * ارجاع به کارشناس: تیکت با متن گفتگو.
     *
     * @param  array{category?:string,priority?:string,subject?:string,summary?:string,station_id?:?int}  $data
     */
    public function escalate(User $user, AiConversation $conversation, array $data = []): SupportTicket
    {
        if ($conversation->support_ticket_id && ($existing = SupportTicket::find($conversation->support_ticket_id))) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $conversation, $data) {
            $summary = trim((string) ($data['summary'] ?? ''));
            $transcript = AiSupportTools::transcript($conversation);
            $ticket = SupportTicket::create([
                'uuid' => (string) Str::uuid(),
                'ticket_number' => 'TK-'.random_int(10000, 99999),
                'user_id' => $user->id,
                'station_id' => $data['station_id'] ?? null,
                'category' => $data['category'] ?? 'other',
                'priority' => $data['priority'] ?? 'normal',
                'source' => 'ai',
                'status' => 'open',
                'subject' => $data['subject'] ?? ($conversation->title ?: 'درخواست از دستیار هوشمند'),
                'body' => $summary !== '' ? $summary : Str::limit($transcript, 1500),
            ]);
            AiSupportTools::ticketMessage($ticket, $user, ($summary !== '' ? $summary."\n\n" : '')."— متن گفتگو با دستیار هوشمند —\n".$transcript);
            $conversation->update(['status' => 'escalated', 'support_ticket_id' => $ticket->id]);

            return $ticket;
        });
    }

    /**
     * پیش‌نویس پاسخ کارشناس برای یک تیکت (پنل مدیریت). کارشناس قبل از ارسال آن را ویرایش و تأیید می‌کند.
     */
    public function draftReply(SupportTicket $ticket): string
    {
        $ticket->loadMissing(['user', 'messages.user', 'station']);
        $user = $ticket->user;
        if (! $this->enabled() || ! $user) {
            return 'سلام '.($user?->name ?? '').' عزیز،'."\n".'درخواست شما بررسی شد. ';
        }

        $thread = $ticket->messages->map(fn ($m) => ($m->is_internal ? '[یادداشت داخلی] ' : ($m->is_staff_reply ? 'کارشناس: ' : 'مشتری: ')).$m->message)->implode("\n");
        $context = json_encode([
            'account' => $this->tools->run('get_account_summary', [], $user, new AiConversation),
            'recent_sessions' => $this->tools->run('get_recent_sessions', ['limit' => 5], $user, new AiConversation),
            'recent_transactions' => $this->tools->run('get_wallet_transactions', ['limit' => 5], $user, new AiConversation),
        ], JSON_UNESCAPED_UNICODE);

        try {
            $client = new Client(apiKey: (string) config('services.anthropic.key'), baseUrl: config('services.anthropic.base_url') ?: null, requestOptions: ['timeout' => 60, 'maxRetries' => 2]);
            $response = $client->beta->messages->create(
                maxTokens: 2048,
                messages: [['role' => 'user', 'content' => "Ticket {$ticket->ticket_number} — category: {$ticket->category}, priority: {$ticket->priority}, station: ".($ticket->station?->name ?? '-')."\nSubject: {$ticket->subject}\n\nThread:\n{$thread}\n\nCustomer data (amounts in toman):\n{$context}\n\nWrite the next reply from the support agent to the customer."]],
                model: (string) config('services.anthropic.support_model', 'claude-opus-5-5'),
                system: 'You draft replies for SafirCharge (EV charging, Iran) human support agents. Write in polite, concise Persian, addressed to the customer by name, using Persian digits and toman. Base every fact on the thread and customer data; do not promise refunds or actions the agent has not decided — where a decision is needed, leave a clear placeholder like [مبلغ استرداد] for the agent. Never include internal notes verbatim. Output only the reply text.',
                outputConfig: ['effort' => 'medium'],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
            $text = $response->stopReason === 'refusal' ? '' : $this->textOf($response);

            return $text !== '' ? $text : 'سلام '.$user->name.' عزیز، ';
        } catch (RateLimitException|APIConnectionException|APIStatusException $e) {
            Log::warning('AI draft failed', ['ticket' => $ticket->id, 'error' => $e->getMessage()]);

            return 'سلام '.$user->name.' عزیز، ';
        }
    }

    private function systemPrompt(): string
    {
        $contact = $this->presenter->supportContact();
        $faq = collect($contact['faq'])->map(fn ($f) => "س: {$f['q']}\nج: {$f['a']}")->implode("\n\n");
        $custom = trim(SystemSetting::text('app', 'ai_support_instructions', ''));

        return <<<PROMPT
You are the customer support assistant of SafirCharge, an electric-vehicle charging network in Iran. You talk to drivers inside the SafirCharge mobile app and web app.

Always reply in Persian (Farsi), in a warm, concise and practical tone. Use Persian digits for numbers and toman for money. Keep answers short (a few sentences or a short list); this is a phone chat.

How to work:
- For anything about this customer's own account (balance, active or past charges, bills, cards, vehicles, tickets) call the tools and answer from their results. Never guess amounts, times or statuses.
- For station availability call find_stations; for prices call get_tariffs.
- You cannot move money, issue refunds, start or stop chargers, or change account data. When the customer needs one of those, or asks for a human, or the problem needs a technician, or you could not solve it, offer to hand the case to a human agent and call create_support_ticket once they agree (or right away when they explicitly ask for a person). Then give them the ticket number and say the reply will appear in the support section.
- Never ask for card numbers, passwords or one-time codes.
- If a question is unrelated to charging, the app or the customer's account, briefly say you can only help with SafirCharge.

Product facts:
- Starting a charge: scan the QR code on the connector in the app (or open the station page and authorise), tap a RFID card on the charger, or let a camera-equipped station recognise the registered licence plate. Charging cost is deducted from the wallet; a minimum wallet balance of 1,000 toman is required to start.
- Charging stops automatically at the customer's target percentage (80/90/100% in settings) or when they press stop. After the cable stays connected past the grace period an idle fee per minute may apply.
- If the phone is on the station's WiFi, the app can charge through the station's local controller even without internet; the bill syncs later.
- Wallet top-up is done from the Wallet tab through the bank gateway. Failed bank payments that were debited are returned by the bank within 72 hours.
- Reservations hold a connector for 15 minutes after the reserved time.
- Every session has a receipt (History → tap a session) with the cost breakdown and power chart; customers can rate the station there.
- On a station page customers can report a broken charger ("گزارش خرابی") and, when every connector is busy, tap "خبرم کن" to get a notification as soon as one is free (valid for 3 hours).
- "تحلیل هزینه و مصرف" (from History or Account) shows six months of spending, energy and savings versus petrol.
- Phone support: {$contact['phone']}.

Frequently asked questions:
{$faq}
{$custom}
PROMPT;
    }

    /** پاسخ بدون مدل: نزدیک‌ترین پرسش متداول + پیشنهاد کارشناس. */
    public function fallbackReply(string $text): string
    {
        $faq = $this->presenter->supportContact()['faq'];
        $words = array_filter(preg_split('/[\s،,؟?!.]+/u', $text) ?: [], fn ($w) => mb_strlen($w) > 2);
        $best = null;
        $bestScore = 0;
        foreach ($faq as $item) {
            $score = 0;
            foreach ($words as $w) {
                $score += mb_stripos($item['q'].' '.$item['a'], $w) !== false ? 1 : 0;
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$item, $score];
            }
        }

        if ($best && $bestScore > 0) {
            return $best['a']."\n\nاگر مشکل حل نشد، دکمهٔ «گفتگو با کارشناس» را بزنید تا درخواست شما ثبت شود.";
        }

        return 'برای بررسی دقیق، دکمهٔ «گفتگو با کارشناس» را بزنید تا درخواست شما با متن همین گفتگو برای پشتیبانی ثبت شود. شمارهٔ تماس پشتیبانی: '.$this->presenter->supportContact()['phone'];
    }
}
