<?php

namespace App\Services\Support;

use App\Models\AiConversation;
use App\Models\ChargingSession;
use App\Models\Reservation;
use App\Models\RfidCard;
use App\Models\Station;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\App\MobileAppPresenter;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Charging\ConnectorAvailabilityService;
use Illuminate\Support\Str;

/**
 * ابزارهای دستیار هوشمند پشتیبانی. همه فقط‌خواندنی و محدود به دادهٔ همان مشتری‌اند؛
 * تنها اقدام نوشتنی «ثبت درخواست پشتیبانی» (ارجاع به کارشناس انسانی) است.
 * مبالغ همه به تومان برگردانده می‌شوند.
 */
class AiSupportTools
{
    public function __construct(
        private CloudChargingSessionService $charging,
        private ConnectorAvailabilityService $availability,
        private MobileAppPresenter $presenter,
    ) {}

    /** @return list<array<string, mixed>> تعریف ابزارها (ثابت و مرتب برای کش شدن پیشوند درخواست) */
    public function definitions(): array
    {
        $none = ['type' => 'object', 'properties' => new \stdClass, 'required' => [], 'additionalProperties' => false];

        return [
            [
                'name' => 'get_account_summary',
                'description' => 'Current customer account: name, wallet balance and bonus (toman), active charging session with live metrics, vehicles, RFID cards, unread notifications. Use for any question about the customer\'s own balance, active charge, cards or vehicles.',
                'inputSchema' => $none,
                'strict' => true,
            ],
            [
                'name' => 'get_recent_sessions',
                'description' => 'The customer\'s recent charging sessions (newest first) with station, start time, energy kWh, duration, cost in toman, status and stop reason. Use to explain a bill, a failed or stopped charge, or past usage.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'description' => 'How many sessions, 1-10']],
                    'required' => ['limit'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
            [
                'name' => 'get_wallet_transactions',
                'description' => 'Recent wallet transactions (top-ups, charging debits, refunds) with amounts in toman and resulting balance.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['limit' => ['type' => 'integer', 'description' => 'How many transactions, 1-15']],
                    'required' => ['limit'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
            [
                'name' => 'find_stations',
                'description' => 'Search active charging stations by name, city or address. Returns code, address, free connectors right now, max power and connector types.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => ['string', 'null'], 'description' => 'Persian name/city/address fragment, or null for all stations'],
                        'only_available' => ['type' => 'boolean', 'description' => 'Only stations with a free connector now'],
                    ],
                    'required' => ['query', 'only_available'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
            [
                'name' => 'get_tariffs',
                'description' => 'Current public charging tariff: energy price per kWh, idle (occupancy) fee per minute and free grace minutes, off-peak hours and price. Amounts in toman.',
                'inputSchema' => $none,
                'strict' => true,
            ],
            [
                'name' => 'list_my_tickets',
                'description' => 'The customer\'s support tickets with number, subject, status and the latest staff reply.',
                'inputSchema' => $none,
                'strict' => true,
            ],
            [
                'name' => 'create_support_ticket',
                'description' => 'Escalate to a human support agent by opening a ticket; the conversation transcript is attached automatically. Use when the customer asks for a person, for refunds or billing disputes, a charger that needs a technician, account changes you cannot do, or anything you could not resolve. Tell the customer the ticket number afterwards.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => ['type' => 'string', 'enum' => ['payment', 'charger', 'rfid', 'station', 'account', 'other']],
                        'priority' => ['type' => 'string', 'enum' => ['normal', 'high'], 'description' => 'high when the customer is stuck at a station, was charged incorrectly, or has a safety problem'],
                        'subject' => ['type' => 'string', 'description' => 'Short Persian subject'],
                        'summary' => ['type' => 'string', 'description' => 'Persian summary of the problem and what was already checked'],
                        'session_reference' => ['type' => ['string', 'null'], 'description' => 'Session reference from get_recent_sessions if the issue is about a specific charge, else null'],
                    ],
                    'required' => ['category', 'priority', 'subject', 'summary', 'session_reference'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(string $name, array $input, User $user, AiConversation $conversation): array
    {
        return match ($name) {
            'get_account_summary' => $this->accountSummary($user),
            'get_recent_sessions' => $this->recentSessions($user, (int) ($input['limit'] ?? 5)),
            'get_wallet_transactions' => $this->transactions($user, (int) ($input['limit'] ?? 10)),
            'find_stations' => $this->findStations($input['query'] ?? null, (bool) ($input['only_available'] ?? false)),
            'get_tariffs' => $this->presenter->tariffBreakdown() + ['currency' => 'toman'],
            'list_my_tickets' => $this->tickets($user),
            'create_support_ticket' => $this->createTicket($user, $conversation, $input),
            default => ['error' => 'unknown tool'],
        };
    }

    private function toman(mixed $rial): int
    {
        return (int) round((float) ($rial ?? 0) / 10);
    }

    /** @return array<string, mixed> */
    private function accountSummary(User $user): array
    {
        $user->loadMissing(['wallet', 'vehicles']);
        $active = ChargingSession::with(['station', 'charger', 'connector'])
            ->where('user_id', $user->id)->where('status', 'active')->latest('started_at')->first();
        $live = $active ? $this->charging->liveMetrics($active) : null;

        return [
            'name' => $user->name,
            'phone' => $user->phone,
            'wallet_balance_toman' => $this->toman($user->wallet?->balance),
            'bonus_balance_toman' => $this->toman($user->wallet?->bonus_balance),
            'active_session' => $active ? [
                'reference' => substr($active->uuid, 0, 8),
                'station' => $active->station?->name,
                'charge_point' => $active->charger?->charge_point_id,
                'connector' => $active->connector?->connector_number,
                'started_at' => $active->started_at?->toIso8601String(),
                'battery_percent' => $live['pct'] ?? null,
                'energy_kwh' => $live['energy'] ?? null,
                'power_kw' => $live['power'] ?? null,
                'cost_so_far_toman' => $live['cost'] ?? null,
                'minutes_to_target' => $live['eta'] ?? null,
                'target_percent' => $live['goal'] ?? null,
                'state' => $live['state'] ?? null,
            ] : null,
            'vehicles' => $user->vehicles->map(fn ($v) => [
                'name' => trim($v->brand.' '.$v->model), 'battery_kwh' => (int) $v->battery_kwh, 'is_default' => (bool) $v->is_default,
                'plate' => "{$v->plate_two} {$v->plate_letter} {$v->plate_three} - {$v->plate_province}",
            ])->values(),
            'rfid_cards' => RfidCard::where('user_id', $user->id)->get()->map(fn ($c) => [
                'label' => $c->card_label, 'last4' => substr((string) $c->id_tag, -4), 'status' => $c->status,
            ])->values(),
            'upcoming_reservations' => Reservation::with('station')->where('user_id', $user->id)
                ->where('reserved_until', '>', now())->orderBy('reserved_from')->limit(3)->get()
                ->map(fn ($r) => ['station' => $r->station?->name, 'from' => $r->reserved_from?->toIso8601String(), 'status' => $r->status])->values(),
            'unread_notifications' => $user->userNotifications()->whereNull('read_at')->count(),
            'charge_target_percent' => (int) ($user->meta['app_prefs']['charge_target_soc'] ?? 80),
        ];
    }

    /** @return array<string, mixed> */
    private function recentSessions(User $user, int $limit): array
    {
        $rows = ChargingSession::with(['station', 'charger'])
            ->where('user_id', $user->id)->latest('started_at')->limit(max(1, min(10, $limit)))->get();

        return ['sessions' => $rows->map(fn (ChargingSession $s) => [
            'reference' => substr($s->uuid, 0, 8),
            'station' => $s->station?->name,
            'charge_point' => $s->charger?->charge_point_id,
            'started_at' => $s->started_at?->toIso8601String(),
            'stopped_at' => $s->stopped_at?->toIso8601String(),
            'status' => $s->status,
            'energy_kwh' => round((float) $s->energy_kwh, 2),
            'duration_minutes' => $s->duration_seconds ? (int) ceil($s->duration_seconds / 60) : null,
            'cost_toman' => $this->toman($s->cost_amount),
            'stop_reason' => $s->stop_reason,
            'auth_method' => $s->auth_method,
            'idle_minutes' => isset($s->meta['idle_seconds']) ? (int) round($s->meta['idle_seconds'] / 60) : null,
        ])->values()];
    }

    /** @return array<string, mixed> */
    private function transactions(User $user, int $limit): array
    {
        $wallet = $user->wallet()->first();
        if (! $wallet) {
            return ['transactions' => []];
        }

        return ['transactions' => WalletTransaction::where('wallet_id', $wallet->id)->latest('occurred_at')
            ->limit(max(1, min(15, $limit)))->get()
            ->map(fn (WalletTransaction $t) => [
                'type' => $t->type,
                'description' => $t->description,
                'amount_toman' => $this->toman($t->amount),
                'balance_after_toman' => $this->toman($t->balance_after),
                'at' => $t->occurred_at?->toIso8601String(),
            ])->values()];
    }

    /** @return array<string, mixed> */
    private function findStations(?string $query, bool $onlyAvailable): array
    {
        $q = Station::where('status', 'active')->with(['chargers.connectors']);
        if ($query) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$query}%")->orWhere('city', 'like', "%{$query}%")->orWhere('address', 'like', "%{$query}%"));
        }
        $stations = $q->limit(20)->get()->map(function (Station $st) {
            $connectors = $st->chargers->flatMap->connectors;
            $free = $connectors->filter(fn ($c) => $this->availability->snapshot($c)['reservable'])->count();

            return [
                'code' => $st->code,
                'name' => $st->name,
                'city' => $st->city,
                'address' => $st->address,
                'free_connectors' => $free,
                'total_connectors' => $connectors->count(),
                'max_power_kw' => (int) $st->chargers->max('max_power_kw'),
                'connector_types' => $st->chargers->pluck('connector_type')->filter()->unique()->values(),
                'hours' => is_array($st->operating_hours) ? ($st->operating_hours['label'] ?? null) : null,
            ];
        });
        if ($onlyAvailable) {
            $stations = $stations->where('free_connectors', '>', 0);
        }

        return ['stations' => $stations->take(10)->values()];
    }

    /** @return array<string, mixed> */
    private function tickets(User $user): array
    {
        return ['tickets' => SupportTicket::with(['messages' => fn ($q) => $q->where('is_staff_reply', true)->where('is_internal', false)->latest()])
            ->where('user_id', $user->id)->latest()->limit(5)->get()
            ->map(fn (SupportTicket $t) => [
                'number' => $t->ticket_number,
                'subject' => $t->subject,
                'status' => $this->presenter->ticketStatusLabel($t->status),
                'created_at' => $t->created_at?->toIso8601String(),
                'latest_staff_reply' => $t->messages->first()?->message,
            ])->values()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function createTicket(User $user, AiConversation $conversation, array $input): array
    {
        if ($conversation->support_ticket_id && $conversation->ticket) {
            return ['ticket_number' => $conversation->ticket->ticket_number, 'note' => 'A ticket already exists for this conversation.'];
        }

        $session = null;
        if (! empty($input['session_reference'])) {
            $session = ChargingSession::where('user_id', $user->id)->where('uuid', 'like', $input['session_reference'].'%')->first();
        }

        $ticket = app(AiSupportService::class)->escalate($user, $conversation, [
            'category' => in_array($input['category'] ?? '', ['payment', 'charger', 'rfid', 'station', 'account', 'other'], true) ? $input['category'] : 'other',
            'priority' => ($input['priority'] ?? 'normal') === 'high' ? 'high' : 'normal',
            'subject' => Str::limit((string) ($input['subject'] ?? 'درخواست پشتیبانی'), 120, ''),
            'summary' => (string) ($input['summary'] ?? ''),
            'station_id' => $session?->station_id,
        ]);

        return ['ticket_number' => $ticket->ticket_number, 'status' => 'open', 'note' => 'A human agent will reply in the app\'s support section.'];
    }

    /** متن گفتگو برای پیوست تیکت. */
    public static function transcript(AiConversation $conversation): string
    {
        return $conversation->messages()->whereNotNull('display_text')->get()
            ->map(fn ($m) => ($m->role === 'user' ? 'مشتری: ' : 'دستیار: ').$m->display_text)
            ->implode("\n");
    }

    public static function ticketMessage(SupportTicket $ticket, User $user, string $text): TicketMessage
    {
        return TicketMessage::create(['support_ticket_id' => $ticket->id, 'user_id' => $user->id, 'message' => $text, 'is_staff_reply' => false]);
    }
}
