<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Partner;
use App\Models\RepairOrder;
use App\Models\Reservation;
use App\Models\Station;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Admin\AdminViewDataService;
use App\Services\Edge\EdgeCommandService;
use App\Services\ReservationService;
use App\Support\Persian;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiController extends Controller
{
    public function __construct(
        private ReservationService $reservations,
        private AdminViewDataService $viewData,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.$q.'%';
        $phoneLike = '%'.$this->normalizeDigits($q).'%';
        $results = [];

        User::query()
            ->where('role', UserRole::Customer)
            ->where(function ($query) use ($like, $phoneLike) {
                $query->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $phoneLike)
                    ->orWhere('email', 'like', $like);
            })
            ->limit(6)
            ->get(['id', 'name', 'phone'])
            ->each(function (User $u) use (&$results) {
                $results[] = [
                    'type' => 'user',
                    'label' => $u->name,
                    'meta' => Persian::digits($u->phone ?? ''),
                    'url' => route('admin.users.index', ['q' => $u->name]),
                ];
            });

        Station::query()
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('city', 'like', $like);
            })
            ->limit(6)
            ->get(['id', 'name', 'city', 'code'])
            ->each(function (Station $s) use (&$results) {
                $results[] = [
                    'type' => 'station',
                    'label' => $s->name,
                    'meta' => ($s->city ?? '').' · '.$s->code,
                    'url' => route('admin.stations.show', $s),
                ];
            });

        ChargingSession::query()
            ->with('user:id,name')
            ->where(function ($query) use ($like) {
                $query->where('session_code', 'like', $like)
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', $like));
            })
            ->latest('started_at')
            ->limit(6)
            ->get(['id', 'session_code', 'user_id', 'status'])
            ->each(function (ChargingSession $s) use (&$results) {
                $results[] = [
                    'type' => 'session',
                    'label' => $s->session_code ?? 'جلسه #'.$s->id,
                    'meta' => ($s->user?->name ?? 'مهمان').' · '.$s->status,
                    'url' => route('admin.sessions.index', ['q' => $s->session_code]),
                ];
            });

        return response()->json(['results' => $results]);
    }

    public function users(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 1) {
            return response()->json(['users' => []]);
        }

        $like = '%'.$q.'%';
        $phoneLike = '%'.$this->normalizeDigits($q).'%';

        $users = User::query()
            ->where('role', UserRole::Customer)
            ->where(function ($query) use ($like, $phoneLike) {
                $query->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $phoneLike)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'phone', 'email']);

        return response()->json([
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => Persian::digits($u->phone ?? ''),
                'label' => $u->name.' — '.Persian::digits($u->phone ?? ''),
            ])->values(),
        ]);
    }

    public function walletTopup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:1000'],
            'credit_type' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['uuid' => (string) Str::uuid(), 'currency' => 'IRR', 'status' => 'active']
        );

        $amountRials = (float) $data['amount'] * 10;
        $creditType = $data['credit_type'] ?? 'normal';
        $isBonus = $creditType === 'bonus';
        $txType = match ($creditType) {
            'bonus' => 'bonus',
            'compensation' => 'compensation',
            default => 'topup',
        };

        if ($isBonus) {
            $wallet->increment('bonus_balance', $amountRials);
        } else {
            $wallet->increment('balance', $amountRials);
        }

        $wallet->refresh();

        WalletTransaction::create([
            'uuid' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => $txType,
            'amount' => $amountRials,
            'balance_after' => $isBonus ? $wallet->bonus_balance : $wallet->balance,
            'description' => trim(($data['reason'] ?? 'شارژ دستی').(! empty($data['note']) ? ' — '.$data['note'] : '')),
            'occurred_at' => now(),
            'meta' => ['admin_id' => $request->user()?->id, 'credit_type' => $creditType],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'کیف پول با موفقیت شارژ شد.',
            'balance' => Persian::money((int) round((float) $wallet->balance / 10)),
        ]);
    }

    public function storeReservation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'station_id' => ['required', 'exists:stations,id'],
            'connector_id' => ['required', 'exists:connectors,id'],
            'reserved_from' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:180'],
        ]);

        $from = Carbon::parse($data['reserved_from']);
        $until = $from->copy()->addMinutes((int) $data['duration_minutes']);

        if ($error = $this->reservations->validateNewReservation(
            (int) $data['user_id'],
            (int) $data['station_id'],
            $from,
            $until,
            (int) $data['connector_id'],
        )) {
            return response()->json(['ok' => false, 'message' => $error], 422);
        }

        $connector = Connector::with('charger')->findOrFail($data['connector_id']);

        $reservation = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $data['user_id'],
            'station_id' => $data['station_id'],
            'charger_id' => $connector->charger_id,
            'connector_id' => $connector->id,
            'status' => 'confirmed',
            'reserved_from' => $from,
            'reserved_until' => $until,
            'meta' => ['created_by_admin' => $request->user()?->id],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'رزرو با موفقیت ثبت شد.',
            'code' => 'RS‑'.Persian::digits(8800 + $reservation->id),
        ]);
    }

    public function cancelReservation(Request $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->status === 'cancelled') {
            return response()->json(['ok' => false, 'message' => 'این رزرو قبلاً لغو شده است.'], 422);
        }

        $this->reservations->cancel($reservation, $request->user()?->id);

        return response()->json([
            'ok' => true,
            'message' => 'رزرو با موفقیت لغو شد.',
        ]);
    }

    public function storeRepair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'charger_id' => ['required', 'exists:chargers,id'],
            'issue' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'parts' => ['nullable', 'array'],
        ]);

        $charger = Charger::findOrFail($data['charger_id']);
        $partsNote = collect($data['parts'] ?? [])
            ->map(fn ($p) => trim(($p['name'] ?? '').' ×'.($p['qty'] ?? 1)))
            ->filter()
            ->implode('، ');

        $order = RepairOrder::create([
            'uuid' => (string) Str::uuid(),
            'charger_id' => $charger->id,
            'station_id' => $charger->station_id,
            'status' => 'open',
            'priority' => 'normal',
            'issue_description' => $data['issue'].($data['description'] ? ' — '.$data['description'] : ''),
            'resolution_notes' => $partsNote ?: null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'opened_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'سفارش تعمیر ثبت شد.',
            'code' => 'RP‑'.Persian::digits(8800 + $order->id),
        ]);
    }

    public function storeInventoryIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:32'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'invoice' => ['nullable', 'string', 'max:64'],
        ]);

        $sku = 'INV-'.strtoupper(Str::random(6));
        $item = InventoryItem::create([
            'uuid' => (string) Str::uuid(),
            'sku' => $sku,
            'name' => $data['name'],
            'category' => $data['category'],
            'quantity_on_hand' => (int) $data['quantity'],
            'status' => 'active',
            'meta' => [
                'supplier' => $data['supplier'] ?? null,
                'unit_price' => (float) $data['unit_price'] * 10,
                'invoice' => $data['invoice'] ?? null,
            ],
        ]);

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'movement_type' => 'in',
            'quantity' => (int) $data['quantity'],
            'notes' => 'ورود کالا توسط ادمین',
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'کالا در انبار ثبت شد.',
            'sku' => $item->sku,
        ]);
    }

    public function storeInventoryAssign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'charger_id' => ['required', 'exists:chargers,id'],
            'partner_id' => ['required', 'exists:partners,id'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $charger = Charger::findOrFail($data['charger_id']);
        $partner = Partner::findOrFail($data['partner_id']);
        $meta = $charger->meta ?? [];
        $meta['sale_price'] = (float) $data['sale_price'] * 10;
        $meta['assigned_partner_id'] = $partner->id;
        $charger->update([
            'meta' => $meta,
            'status' => 'deployed',
        ]);

        $itemId = InventoryItem::query()->where('category', 'charger')->value('id')
            ?? InventoryItem::query()->value('id');
        if ($itemId) {
            InventoryMovement::create([
                'inventory_item_id' => $itemId,
                'movement_type' => 'assign',
                'quantity' => 1,
                'partner_id' => $partner->id,
                'station_id' => $charger->station_id,
                'notes' => $data['notes'] ?? ('واگذاری '.$charger->charge_point_id.' به '.$partner->name),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'واگذاری دستگاه ثبت شد.',
        ]);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:percent,credit,event'],
            'value' => ['nullable', 'string', 'max:64'],
            'audience' => ['nullable', 'string', 'max:64'],
        ]);

        $discount = null;
        $bonus = null;
        if ($data['type'] === 'percent') {
            $discount = (float) $this->normalizeDigits($data['value'] ?? '0');
        } elseif ($data['type'] === 'credit') {
            $bonus = (float) $this->normalizeDigits($data['value'] ?? '0') * 10;
        }

        $campaign = Campaign::create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['title'],
            'type' => match ($data['type']) {
                'credit' => 'bonus',
                'event' => 'night_charge',
                default => 'discount',
            },
            'status' => 'active',
            'discount_percent' => $discount,
            'bonus_amount' => $bonus,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'conditions' => [
                'description' => $data['body'] ?? null,
                'audience' => $data['audience'] ?? 'all',
            ],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'طرح با موفقیت منتشر شد.',
            'id' => $campaign->id,
        ]);
    }

    public function storeStaff(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:super_admin,financial_manager,station_operator,support,corporate_manager'],
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'phone' => $this->normalizeDigits($data['phone']),
            'email' => $data['email'] ?? null,
            'password' => Hash::make(Str::random(12)),
            'role' => UserRole::from($data['role']),
            'status' => 'active',
            'phone_verified_at' => now(),
            'email_verified_at' => $data['email'] ? now() : null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'کاربر مدیر ایجاد شد.',
            'id' => $user->id,
        ]);
    }

    public function approveRefund(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->category !== 'payment') {
            return response()->json(['message' => 'این تیکت مربوط به استرداد نیست.'], 422);
        }

        if (! in_array($ticket->status, ['open', 'in_progress'], true)) {
            return response()->json(['message' => 'این درخواست قبلاً رسیدگی شده است.'], 422);
        }

        $amountToman = $this->viewData->refundAmountToman($ticket);
        $amountRials = (float) $amountToman * 10;

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $ticket->user_id],
            ['uuid' => (string) Str::uuid(), 'currency' => 'IRR', 'status' => 'active']
        );

        $wallet->increment('balance', $amountRials);
        $wallet->refresh();

        WalletTransaction::create([
            'uuid' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => 'refund',
            'amount' => $amountRials,
            'balance_after' => $wallet->balance,
            'reference_type' => 'support_ticket',
            'reference_id' => $ticket->id,
            'description' => 'استرداد وجه — '.$ticket->subject,
            'occurred_at' => now(),
            'meta' => ['admin_id' => $request->user()?->id, 'ticket_id' => $ticket->id],
        ]);

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'استرداد '.Persian::money($amountToman).' تأیید و به کیف پول واریز شد.',
        ]);
    }

    public function rejectRefund(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->category !== 'payment') {
            return response()->json(['message' => 'این تیکت مربوط به استرداد نیست.'], 422);
        }

        if (! in_array($ticket->status, ['open', 'in_progress'], true)) {
            return response()->json(['message' => 'این درخواست قبلاً رسیدگی شده است.'], 422);
        }

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'درخواست استرداد رد شد.',
        ]);
    }

    public function reportsData(Request $request): JsonResponse
    {
        return response()->json($this->viewData->reportsBundle($request->only([
            'range', 'city', 'connector', 'date_from', 'date_to',
        ])));
    }

    public function anprLive(Request $request): JsonResponse
    {
        $stationId = $request->integer('station_id') ?: null;

        return response()->json($this->viewData->anprBundle($stationId));
    }

    public function stopSession(ChargingSession $session, EdgeCommandService $commands): JsonResponse
    {
        $session->load(['connector.charger', 'station.edgeStation']);

        if ($session->status !== 'active') {
            return response()->json(['ok' => false, 'error' => 'این جلسه فعال نیست.'], 422);
        }

        $station = $session->station;
        if (! $station?->edgeStation) {
            return response()->json(['ok' => false, 'error' => 'ایستگاه Edge برای توقف از راه دور ندارد.'], 422);
        }

        if (! $session->connector) {
            return response()->json(['ok' => false, 'error' => 'کانکتور جلسه مشخص نیست.'], 422);
        }

        try {
            $cmd = $commands->queue($station, 'remote_stop', $session->connector, [], $request->user());
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'uuid' => $cmd->uuid,
            'message' => 'دستور توقف در صف Edge قرار گرفت.',
        ]);
    }

    private function normalizeDigits(string $value): string
    {
        return Persian::toAsciiDigits($value);
    }
}
