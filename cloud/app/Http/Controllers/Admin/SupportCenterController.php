<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Audit\AuditService;
use App\Services\Notifications\UserNotifier;
use App\Services\Support\AiSupportService;
use App\Support\Persian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** مرکز پشتیبانی مشتریان در پنل مدیریت: تیکت‌ها، پاسخ، ارجاع، اعتبار جبرانی و گفتگوهای دستیار هوشمند. */
class SupportCenterController extends Controller
{
    public const CATEGORIES = [
        'payment' => 'پرداخت و کیف پول', 'charger' => 'مشکل در شارژ', 'rfid' => 'کارت RFID',
        'station' => 'ایستگاه و دستگاه', 'account' => 'حساب کاربری', 'other' => 'سایر',
    ];

    public const STATUSES = ['open' => 'باز', 'in_progress' => 'در حال بررسی', 'resolved' => 'حل‌شده', 'closed' => 'بسته'];

    public const PRIORITIES = ['low' => 'پایین', 'normal' => 'عادی', 'high' => 'بالا', 'critical' => 'بحرانی'];

    public const SOURCES = ['app' => 'اپ', 'web' => 'وب‌اپ', 'ai' => 'دستیار هوشمند', 'staff' => 'کارشناس'];

    private const DEFAULT_CANNED = [
        ['title' => 'دریافت درخواست', 'text' => 'سلام، درخواست شما دریافت شد و در حال بررسی است. نتیجه را در همین گفتگو اطلاع می‌دهیم.'],
        ['title' => 'استرداد انجام شد', 'text' => 'سلام، مبلغ مورد نظر به کیف پول شما بازگردانده شد. از صبر شما سپاسگزاریم.'],
        ['title' => 'اعزام تکنسین', 'text' => 'سلام، مشکل شارژر ثبت شد و تکنسین برای بررسی اعزام می‌شود. تا رفع مشکل لطفاً از کانکتور دیگری استفاده کنید.'],
        ['title' => 'راهنمای شروع شارژ', 'text' => 'سلام، برای شروع شارژ ابتدا کابل را به خودرو وصل کنید، سپس QR روی همان کانکتور را در اپ اسکن و «شروع شارژ» را بزنید.'],
        ['title' => 'اطلاعات بیشتر', 'text' => 'سلام، برای بررسی دقیق‌تر لطفاً زمان تقریبی، نام ایستگاه و شمارهٔ کانکتور را بفرمایید.'],
    ];

    public function __construct(private AiSupportService $ai, private UserNotifier $notifier, private AuditService $audit) {}

    public function index(Request $request): View
    {
        $q = SupportTicket::with(['user', 'assignee', 'station'])->withCount('messages');
        $status = $request->query('status', 'active');
        match ($status) {
            'active' => $q->whereIn('status', ['open', 'in_progress']),
            'all' => null,
            default => $q->where('status', $status),
        };
        if ($p = $request->query('priority')) {
            $q->where('priority', $p);
        }
        if ($s = $request->query('source')) {
            $q->where('source', $s);
        }
        if ($request->query('mine')) {
            $q->where('assigned_to', $request->user()->id);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('ticket_number', 'like', "%{$term}%")->orWhere('subject', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")));
        }
        $tickets = $q->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->latest('updated_at')->paginate(25)->withQueryString();

        return view('admin.support', [
            'tickets' => $tickets,
            'stats' => $this->stats(),
            'faults' => $this->faults(),
            'ai' => $this->aiStats(),
            'filters' => $request->only(['status', 'priority', 'source', 'mine', 'q']) + ['status' => $status],
            'labels' => ['categories' => self::CATEGORIES, 'statuses' => self::STATUSES, 'priorities' => self::PRIORITIES, 'sources' => self::SOURCES],
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['user.wallet', 'user.vehicles', 'assignee', 'station', 'messages.user']);
        $user = $ticket->user;

        return view('admin.support-ticket', [
            'ticket' => $ticket,
            'customer' => $user,
            'sessions' => $user ? ChargingSession::with('station')->where('user_id', $user->id)->latest('started_at')->limit(6)->get() : collect(),
            'otherTickets' => $user ? SupportTicket::where('user_id', $user->id)->whereKeyNot($ticket->id)->latest()->limit(5)->get() : collect(),
            'conversation' => AiConversation::where('support_ticket_id', $ticket->id)->first(),
            'staff' => User::whereNotIn('role', [UserRole::Customer->value, UserRole::Partner->value])->where('status', 'active')->orderBy('name')->get(['id', 'name', 'role']),
            'canned' => $this->canned(),
            'aiEnabled' => $this->ai->enabled(),
            'labels' => ['categories' => self::CATEGORIES, 'statuses' => self::STATUSES, 'priorities' => self::PRIORITIES, 'sources' => self::SOURCES],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'internal' => ['sometimes', 'boolean'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(self::STATUSES))],
        ]);
        $internal = (bool) ($data['internal'] ?? false);

        TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_staff_reply' => true,
            'is_internal' => $internal,
        ]);

        $updates = [];
        if (! $internal && ! $ticket->first_response_at) {
            $updates['first_response_at'] = now();
        }
        $newStatus = $data['status'] ?? (! $internal && $ticket->status === 'open' ? 'in_progress' : null);
        if ($newStatus && $newStatus !== $ticket->status) {
            $updates['status'] = $newStatus;
            $updates['resolved_at'] = in_array($newStatus, ['resolved', 'closed'], true) ? now() : null;
        }
        if (! $ticket->assigned_to) {
            $updates['assigned_to'] = $request->user()->id;
        }
        $ticket->update($updates + ['updated_at' => now()]);

        if (! $internal && $ticket->user) {
            $this->notifier->notify($ticket->user, 'ticket_reply', 'پاسخ پشتیبانی به '.$ticket->ticket_number,
                Str::limit($data['message'], 140), ['ticket' => $ticket->id]);
        }

        return back()->with('success', $internal ? 'یادداشت داخلی ثبت شد.' : 'پاسخ برای مشتری ارسال شد.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(self::STATUSES))],
            'priority' => ['required', 'in:'.implode(',', array_keys(self::PRIORITIES))],
            'category' => ['required', 'in:'.implode(',', array_keys(self::CATEGORIES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);
        $wasOpen = ! in_array($ticket->status, ['resolved', 'closed'], true);
        $ticket->update($data + [
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null,
        ]);
        if ($wasOpen && $data['status'] === 'resolved' && $ticket->user) {
            $this->notifier->notify($ticket->user, 'ticket_resolved', 'درخواست '.$ticket->ticket_number.' حل شد', 'اگر مشکل همچنان باقی است، در همان گفتگو پیام دهید تا دوباره بررسی شود.', ['ticket' => $ticket->id]);
        }

        return back()->with('success', 'تیکت به‌روزرسانی شد.');
    }

    /** اعتبار جبرانی/استرداد به کیف پول مشتری (با ثبت در گفتگو و ممیزی). */
    public function credit(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:5000000'],
            'type' => ['required', 'in:refund,compensation'],
            'reason' => ['required', 'string', 'max:200'],
        ]);
        $user = $ticket->user;
        abort_unless($user, 404);
        $rial = $data['amount'] * 10;

        DB::transaction(function () use ($user, $rial, $data, $ticket, $request) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
                ?? Wallet::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'balance' => 0, 'currency' => 'IRR', 'status' => 'active']);
            $balance = (float) $wallet->balance + $rial;
            $wallet->update(['balance' => $balance]);
            $tx = WalletTransaction::create([
                'uuid' => (string) Str::uuid(),
                'wallet_id' => $wallet->id,
                'type' => $data['type'],
                'amount' => $rial,
                'balance_after' => $balance,
                'reference_type' => SupportTicket::class,
                'reference_id' => $ticket->id,
                'description' => ($data['type'] === 'refund' ? 'استرداد' : 'اعتبار جبرانی').' — '.$data['reason'].' ('.$ticket->ticket_number.')',
                'occurred_at' => now(),
            ]);
            TicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'message' => '['.($data['type'] === 'refund' ? 'استرداد' : 'اعتبار جبرانی').'] '.Persian::money($data['amount']).' تومان — '.$data['reason'],
                'is_staff_reply' => true,
                'is_internal' => true,
            ]);
            $this->audit->record('support.wallet_credit', $tx, null, ['amount_toman' => $data['amount'], 'ticket' => $ticket->ticket_number, 'reason' => $data['reason']]);
        });

        $this->notifier->notify($user, 'wallet_credit', 'افزایش اعتبار کیف پول',
            Persian::money($data['amount']).' تومان بابت درخواست '.$ticket->ticket_number.' به کیف پول شما اضافه شد.', ['ticket' => $ticket->id]);

        return back()->with('success', Persian::money($data['amount']).' تومان به کیف پول مشتری اضافه شد.');
    }

    public function draft(SupportTicket $ticket): JsonResponse
    {
        return response()->json(['text' => $this->ai->draftReply($ticket), 'ai' => $this->ai->enabled()]);
    }

    public function storeCanned(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:60'], 'text' => ['required', 'string', 'max:1000']]);
        $list = $this->canned();
        $list[] = $data;
        SystemSetting::updateOrCreate(['group' => 'support', 'key' => 'canned_replies'], ['value' => array_values($list)]);

        return back()->with('success', 'پاسخ آماده ذخیره شد.');
    }

    public function destroyCanned(int $index): RedirectResponse
    {
        $list = $this->canned();
        unset($list[$index]);
        SystemSetting::updateOrCreate(['group' => 'support', 'key' => 'canned_replies'], ['value' => array_values($list)]);

        return back()->with('success', 'پاسخ آماده حذف شد.');
    }

    public function conversations(Request $request): View
    {
        $q = AiConversation::with(['user', 'ticket'])->where('message_count', '>', 0)->latest('last_message_at');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }

        return view('admin.support-ai', [
            'conversations' => $q->paginate(25)->withQueryString(),
            'ai' => $this->aiStats(),
            'instructions' => SystemSetting::text('app', 'ai_support_instructions', ''),
            'enabled' => $this->ai->enabled(),
            'model' => config('services.anthropic.support_model'),
            'canned' => $this->canned(),
        ]);
    }

    public function conversation(AiConversation $conversation): View
    {
        $conversation->load(['user', 'ticket', 'messages']);

        return view('admin.support-ai-show', ['conversation' => $conversation]);
    }

    public function updateAiSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['instructions' => ['nullable', 'string', 'max:4000']]);
        SystemSetting::updateOrCreate(['group' => 'app', 'key' => 'ai_support_instructions'], ['value' => ['fa' => (string) ($data['instructions'] ?? '')]]);

        return back()->with('success', 'دستورالعمل دستیار هوشمند ذخیره شد.');
    }

    /** @return list<array{title:string,text:string}> */
    private function canned(): array
    {
        $value = SystemSetting::where('group', 'support')->where('key', 'canned_replies')->first()?->value;

        return is_array($value) && $value !== [] ? array_values($value) : self::DEFAULT_CANNED;
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $responded = SupportTicket::whereNotNull('first_response_at')->where('created_at', '>=', now()->subDays(30))->get(['created_at', 'first_response_at']);
        $avg = $responded->isEmpty() ? null : (int) round($responded->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_response_at)));

        return [
            'open' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
            'unanswered' => SupportTicket::whereIn('status', ['open', 'in_progress'])->whereNull('first_response_at')->count(),
            'high' => SupportTicket::whereIn('priority', ['high', 'critical'])->whereIn('status', ['open', 'in_progress'])->count(),
            'resolved_week' => SupportTicket::whereIn('status', ['resolved', 'closed'])->where('resolved_at', '>=', now()->subWeek())->count(),
            'avg_first_response_min' => $avg,
        ];
    }

    /** @return array<string, mixed> */
    private function aiStats(): array
    {
        $week = AiConversation::where('message_count', '>', 0)->where('created_at', '>=', now()->subWeek());
        $total = (clone $week)->count();
        $escalated = (clone $week)->where('status', 'escalated')->count();

        return [
            'week' => $total,
            'escalated' => $escalated,
            'self_served_pct' => $total ? (int) round(100 * ($total - $escalated) / $total) : null,
            'tokens' => (int) AiConversation::where('created_at', '>=', now()->subMonth())->sum(DB::raw('input_tokens + output_tokens')),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function faults()
    {
        return Charger::with(['station', 'connectors'])
            ->where(fn ($q) => $q->where('is_online', false)->orWhereHas('connectors', fn ($c) => $c->whereIn('ocpp_status', ['Faulted', 'Unavailable'])))
            ->latest('updated_at')->limit(10)->get()
            ->map(function (Charger $ch) {
                $faulted = $ch->connectors->firstWhere('ocpp_status', 'Faulted') ?? $ch->connectors->firstWhere('ocpp_status', 'Unavailable');
                $code = $faulted?->meta['error_code'] ?? ($ch->is_online ? ($faulted?->ocpp_status ?? 'Faulted') : 'ConnectionLost');

                return [
                    'charger' => $ch,
                    'code' => $code,
                    'critical' => ! $ch->is_online || in_array($code, ['GroundFailure', 'PowerMeterFailure', 'OverCurrentFailure'], true),
                    'open_tickets' => SupportTicket::where('station_id', $ch->station_id)->whereIn('status', ['open', 'in_progress'])->count(),
                ];
            });
    }
}
