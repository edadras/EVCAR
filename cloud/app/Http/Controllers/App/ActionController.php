<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RfidCard;
use App\Models\SupportTicket;
use App\Models\Tariff;
use App\Models\TicketMessage;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\App\ChargingAuthorizationService;
use App\Services\Audit\AuditService;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ActionController extends Controller
{
    private const VEHICLE_AUDIT_KEYS = [
        'brand', 'model', 'plate_two', 'plate_letter', 'plate_three',
        'plate_province', 'battery_kwh', 'is_default',
    ];

    private const RFID_AUDIT_KEYS = ['id_tag', 'card_label', 'status'];

    public function __construct(private AuditService $audit) {}

    private function user()
    {
        return auth()->user();
    }

    private function enDigits(string $value): string
    {
        return preg_replace('/\D/', '', str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            range(0, 9),
            $value
        ));
    }

    public function storeTicket(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:payment,charger,rfid,station,account,other'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'session_id' => ['nullable', 'exists:charging_sessions,id'],
        ]);

        $session = null;
        if (! empty($data['session_id'])) {
            $session = ChargingSession::where('id', $data['session_id'])
                ->where('user_id', $this->user()->id)
                ->first();
        }

        $ticket = SupportTicket::create([
            'uuid' => (string) Str::uuid(),
            'ticket_number' => 'TK-'.random_int(1000, 9999),
            'user_id' => $this->user()->id,
            'station_id' => $session?->station_id,
            'category' => $data['category'],
            'priority' => 'normal',
            'status' => 'open',
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);

        TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $this->user()->id,
            'message' => $data['body'],
            'is_staff_reply' => false,
        ]);

        return redirect()
            ->route('app.ticket', ['id' => $ticket->id])
            ->with('success', 'درخواست شما با شماره '.$ticket->ticket_number.' ثبت شد.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $this->user()->password)) {
            return back()->withErrors(['current_password' => 'رمز فعلی نادرست است.']);
        }

        $this->user()->update(['password' => $data['password']]);

        return back()->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }

    public function storeReservation(Request $request, ReservationService $reservations): RedirectResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'exists:stations,id'],
            'connector_id' => ['required', 'exists:connectors,id'],
            'reserved_from' => ['required', 'date', 'after:now'],
            'reserved_until' => ['required', 'date', 'after:reserved_from'],
        ]);

        $from = Carbon::parse($data['reserved_from']);
        $until = Carbon::parse($data['reserved_until']);

        if ($error = $reservations->validateNewReservation(
            $this->user()->id,
            (int) $data['station_id'],
            $from,
            $until,
            (int) $data['connector_id'],
        )) {
            return back()->withErrors(['reservation' => $error])->withInput();
        }

        $connector = Connector::findOrFail($data['connector_id']);

        Reservation::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->user()->id,
            'station_id' => $data['station_id'],
            'charger_id' => $connector->charger_id,
            'connector_id' => $connector->id,
            'status' => 'confirmed',
            'reserved_from' => $from,
            'reserved_until' => $until,
        ]);

        return redirect()
            ->route('app.reserve')
            ->with('success', 'رزرو شما ثبت شد.');
    }

    public function processTopup(Request $request): RedirectResponse
    {
        $gateways = config('safircharge.payment.gateways', ['mellat', 'saman', 'zarinpal']);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000', 'max:50000000'],
            'gateway' => ['required', 'string', 'in:'.implode(',', $gateways)],
        ]);

        $user = $this->user();
        $amountRial = (float) $data['amount'] * 10;
        $gateway = config('safircharge.payment.gateway_aliases.'.$data['gateway'], $data['gateway']);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'method' => 'gateway',
            'status' => 'paid',
            'amount' => $amountRial,
            'currency' => 'IRR',
            'gateway' => $gateway,
            'gateway_reference' => 'SC-'.Str::upper(Str::substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
            'paid_at' => now(),
        ]);

        $wallet = $user->wallet ?? Wallet::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'balance' => 0,
            'currency' => 'IRR',
            'status' => 'active',
        ]);

        $newBalance = (float) $wallet->balance + $amountRial;
        $wallet->update(['balance' => $newBalance]);

        WalletTransaction::create([
            'uuid' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'type' => 'credit',
            'amount' => $amountRial,
            'balance_after' => $newBalance,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'description' => 'شارژ کیف پول از درگاه',
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('app.topup.result', [
                'payment' => $payment->uuid,
                'amount' => $data['amount'],
            ]);
    }

    public function chat(Request $request): View
    {
        $user = $this->user();
        $ticketId = $request->query('id');
        $ticket = null;
        $messages = collect();

        if ($ticketId) {
            $ticket = SupportTicket::with('messages.user')
                ->where('user_id', $user->id)
                ->find($ticketId);
            $messages = $ticket?->messages ?? collect();
        }

        return view('app.chat', [
            'ticket' => $ticket,
            'messages' => $messages,
            'tickets' => SupportTicket::where('user_id', $user->id)->latest()->limit(10)->get(),
        ]);
    }

    public function storeChatMessage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'exists:support_tickets,id'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $ticket = SupportTicket::where('id', $data['ticket_id'])
            ->where('user_id', $this->user()->id)
            ->firstOrFail();

        TicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $this->user()->id,
            'message' => $data['message'],
            'is_staff_reply' => false,
        ]);

        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'open']);
        }

        return redirect()->route('app.chat', ['id' => $ticket->id]);
    }

    public function startCharging(
        Request $request,
        ConnectorAvailabilityService $availability,
        ChargingAuthorizationService $chargeAuth,
        CloudChargingSessionService $charging,
    ): RedirectResponse {
        $data = $request->validate([
            'connector_id' => ['required', 'exists:connectors,id'],
        ]);

        $user = $this->user();
        if (ChargingSession::where('user_id', $user->id)->where('status', 'active')->exists()) {
            return redirect()->route('app.charging');
        }

        $connector = Connector::with('charger.station')->findOrFail($data['connector_id']);

        if (! $chargeAuth->canStart($user, $connector)) {
            return back()->withErrors(['charging' => $chargeAuth->denyMessage()]);
        }
        $snap = $availability->snapshot($connector);
        if (! $snap['reservable']) {
            return back()->withErrors(['charging' => $snap['reason'] ?? 'کانکتور در دسترس نیست.']);
        }

        $wallet = $user->wallet;
        if (! $wallet || (float) $wallet->balance < 10000) {
            return back()->withErrors(['charging' => 'موجودی کیف پول کافی نیست.']);
        }

        $tariff = Tariff::where('is_default', true)->where('status', 'active')->first()
            ?? Tariff::where('status', 'active')->first();

        $vehicle = Vehicle::where('user_id', $user->id)->orderByDesc('is_default')->first();
        $prefs = $user->meta['app_prefs'] ?? [];
        $targetSoc = (int) ($prefs['charge_target_soc'] ?? 80);

        try {
            $charging->requestStart(
                $user,
                $connector,
                $chargeAuth->grantTypeFor($connector) ?? 'qr',
                $tariff,
                $vehicle,
                $targetSoc,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['charging' => $e->getMessage()]);
        }

        return redirect()->route('app.charging');
    }

    public function stopCharging(CloudChargingSessionService $charging): RedirectResponse
    {
        $session = ChargingSession::where('user_id', $this->user()->id)
            ->where('status', 'active')
            ->latest('started_at')
            ->first();

        if (! $session) {
            return redirect()->route('app.charging')->withErrors(['charging' => 'جلسه فعالی برای توقف وجود ندارد.']);
        }

        $session = $charging->requestStop($session, $this->user());

        if ($session->status === 'active') {
            return redirect()->route('app.charging')->with('success', 'دستور توقف برای شارژر ارسال شد؛ صورتحساب نهایی پس از توقف شارژر ثبت می‌شود.');
        }

        return redirect()->route('app.history')->with('success', 'شارژ متوقف شد.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dark_theme' => ['sometimes', 'boolean'],
            'accent' => ['sometimes', 'string', 'max:16'],
            'lang' => ['sometimes', 'in:fa,en'],
            'energy_unit' => ['sometimes', 'in:kwh,pct'],
            'notify_push' => ['sometimes', 'boolean'],
            'notify_sms_end' => ['sometimes', 'boolean'],
            'notify_low_balance' => ['sometimes', 'boolean'],
            'charge_target_soc' => ['sometimes', 'integer', 'in:80,90,100'],
            'plate_auto_charge' => ['sometimes', 'boolean'],
        ]);

        $user = $this->user();
        $meta = $user->meta ?? [];
        $prefs = $meta['app_prefs'] ?? [];

        foreach ($data as $key => $value) {
            $prefs[$key] = $value;
        }

        $meta['app_prefs'] = $prefs;
        $user->meta = $meta;
        $user->save();

        return response()->json(['ok' => true, 'prefs' => $prefs]);
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_model' => ['required', 'string', 'max:120'],
            'plate_two' => ['required', 'string', 'max:2'],
            'plate_letter' => ['required', 'string', 'max:4'],
            'plate_three' => ['required', 'string', 'max:3'],
            'plate_province' => ['required', 'string', 'max:2'],
            'battery_kwh' => ['nullable', 'integer', 'min:10', 'max:200'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $user = $this->user();
        $parts = preg_split('/\s+/u', trim($data['brand_model']), 2, PREG_SPLIT_NO_EMPTY);
        $brand = $parts[0] ?? $data['brand_model'];
        $model = $parts[1] ?? '';

        if ($data['is_default'] ?? false) {
            Vehicle::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $p2 = $this->enDigits($data['plate_two']);
        $p3 = $this->enDigits($data['plate_three']);
        $prov = $this->enDigits($data['plate_province']);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'brand' => $brand,
            'model' => $model,
            'plate_two' => $p2,
            'plate_letter' => $data['plate_letter'],
            'plate_three' => $p3,
            'plate_province' => $prov,
            'plate_normalized' => $p2.$data['plate_letter'].$p3.$prov,
            'battery_kwh' => $data['battery_kwh'] ?? 60,
            'is_default' => (bool) ($data['is_default'] ?? ! Vehicle::where('user_id', $user->id)->exists()),
        ]);

        $this->audit->record(
            'vehicle.created',
            $vehicle,
            null,
            $this->audit->snapshot($vehicle, self::VEHICLE_AUDIT_KEYS),
        );

        return redirect()->route('app.vehicle')->with('success', 'خودرو ثبت شد.');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->user_id === $this->user()->id, 403);

        $data = $request->validate([
            'brand_model' => ['required', 'string', 'max:120'],
            'plate_two' => ['required', 'string', 'max:2'],
            'plate_letter' => ['required', 'string', 'max:4'],
            'plate_three' => ['required', 'string', 'max:3'],
            'plate_province' => ['required', 'string', 'max:2'],
            'battery_kwh' => ['nullable', 'integer', 'min:10', 'max:200'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $user = $this->user();
        $parts = preg_split('/\s+/u', trim($data['brand_model']), 2, PREG_SPLIT_NO_EMPTY);
        $p2 = $this->enDigits($data['plate_two']);
        $p3 = $this->enDigits($data['plate_three']);
        $prov = $this->enDigits($data['plate_province']);
        $makeDefault = filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($makeDefault) {
            Vehicle::where('user_id', $user->id)->where('id', '!=', $vehicle->id)->update(['is_default' => false]);
        }

        $old = $this->audit->snapshot($vehicle, self::VEHICLE_AUDIT_KEYS);

        $vehicle->update([
            'brand' => $parts[0] ?? $data['brand_model'],
            'model' => $parts[1] ?? '',
            'plate_two' => $p2,
            'plate_letter' => $data['plate_letter'],
            'plate_three' => $p3,
            'plate_province' => $prov,
            'plate_normalized' => $p2.$data['plate_letter'].$p3.$prov,
            'battery_kwh' => $data['battery_kwh'] ?? $vehicle->battery_kwh,
            'is_default' => $makeDefault ?: $vehicle->is_default,
        ]);

        if (! Vehicle::where('user_id', $user->id)->where('is_default', true)->exists()) {
            Vehicle::where('user_id', $user->id)->oldest('id')->update(['is_default' => true]);
        }

        $vehicle->refresh();
        $this->audit->record(
            'vehicle.updated',
            $vehicle,
            $old,
            $this->audit->snapshot($vehicle, self::VEHICLE_AUDIT_KEYS),
        );

        return redirect()->route('app.vehicle')->with('success', 'خودرو به‌روزرسانی شد.');
    }

    public function destroyVehicle(Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->user_id === $this->user()->id, 403);
        $userId = $vehicle->user_id;
        $wasDefault = $vehicle->is_default;
        $hasHistory = ChargingSession::where('vehicle_id', $vehicle->id)->exists();
        $old = $this->audit->snapshot($vehicle, self::VEHICLE_AUDIT_KEYS);

        $vehicle->delete();

        $this->audit->record('vehicle.deleted', $vehicle, $old, ['status' => 'deleted']);

        if ($wasDefault) {
            Vehicle::where('user_id', $userId)->oldest('id')->first()?->update(['is_default' => true]);
        }

        $msg = $hasHistory
            ? 'خودرو به‌صورت حذف منطقی ثبت شد. سوابق شارژ حفظ می‌ماند.'
            : 'خودرو حذف شد.';

        return redirect()->route('app.vehicle')->with('success', $msg);
    }

    public function setDefaultVehicle(Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->user_id === $this->user()->id, 403);
        $old = $this->audit->snapshot($vehicle, ['is_default']);
        Vehicle::where('user_id', $this->user()->id)->update(['is_default' => false]);
        $vehicle->update(['is_default' => true]);

        $this->audit->record(
            'vehicle.default_set',
            $vehicle,
            $old,
            $this->audit->snapshot($vehicle->fresh(), ['is_default']),
        );

        return redirect()->route('app.vehicle')->with('success', 'خودروی پیش‌فرض تنظیم شد.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $this->user();
        $name = trim($data['first_name'].' '.($data['last_name'] ?? ''));
        $meta = $user->meta ?? [];
        if (! empty($data['national_id'])) {
            $meta['national_id'] = preg_replace('/\D/', '', $data['national_id']);
        }
        if (! empty($data['city'])) {
            $meta['city'] = $data['city'];
        }

        $user->update([
            'name' => $name,
            'email' => $data['email'] ?? $user->email,
            'meta' => $meta,
        ]);

        return redirect()->route('app.profile')->with('success', 'پروفایل ذخیره شد.');
    }

    public function storeRfid(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_tag' => ['required', 'string', 'max:32'],
            'card_label' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $this->user();
        $tag = strtoupper(preg_replace('/\s+/', '', $data['id_tag']));

        if (RfidCard::where('id_tag', $tag)->exists()) {
            return redirect()->route('app.rfid')->withErrors(['id_tag' => 'این شماره کارت قبلاً ثبت شده است.'])->withInput();
        }

        $card = RfidCard::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'id_tag' => $tag,
            'card_label' => $data['card_label'] ?? 'کارت اصلی',
            'status' => 'active',
            'issued_at' => now(),
            'meta' => ['card_type' => 'personal', 'offline_allowed' => true],
        ]);

        $this->audit->record(
            'rfid.created',
            $card,
            null,
            $this->audit->snapshot($card, self::RFID_AUDIT_KEYS),
        );

        return redirect()->route('app.rfid')->with('success', 'کارت RFID ثبت شد.');
    }

    public function updateRfid(Request $request, RfidCard $card): RedirectResponse
    {
        abort_unless($card->user_id === $this->user()->id, 403);

        $data = $request->validate([
            'card_label' => ['nullable', 'string', 'max:60'],
        ]);

        $old = $this->audit->snapshot($card, self::RFID_AUDIT_KEYS);
        $card->update([
            'card_label' => $data['card_label'] ?? $card->card_label,
        ]);

        $this->audit->record(
            'rfid.updated',
            $card,
            $old,
            $this->audit->snapshot($card->fresh(), self::RFID_AUDIT_KEYS),
        );

        return redirect()->route('app.rfid')->with('success', 'کارت به‌روزرسانی شد.');
    }

    public function destroyRfid(RfidCard $card): RedirectResponse
    {
        abort_unless($card->user_id === $this->user()->id, 403);

        $hasHistory = ChargingSession::where('id_tag', $card->id_tag)->exists();
        $old = $this->audit->snapshot($card, self::RFID_AUDIT_KEYS);

        $card->delete();

        $this->audit->record('rfid.deleted', $card, $old, ['status' => 'deleted']);

        $msg = $hasHistory
            ? 'کارت به‌صورت حذف منطقی ثبت شد. سوابق شارژ حفظ می‌ماند.'
            : 'کارت حذف شد.';

        return redirect()->route('app.rfid')->with('success', $msg);
    }

    public function blockRfid(RfidCard $card): RedirectResponse
    {
        abort_unless($card->user_id === $this->user()->id, 403);
        $old = $this->audit->snapshot($card, ['status']);
        $card->update(['status' => 'blocked']);

        $this->audit->record(
            'rfid.blocked',
            $card,
            $old,
            $this->audit->snapshot($card->fresh(), ['status']),
        );

        return redirect()->route('app.rfid')->with('success', 'کارت مسدود شد.');
    }

    public function activateRfid(RfidCard $card): RedirectResponse
    {
        abort_unless($card->user_id === $this->user()->id, 403);
        $old = $this->audit->snapshot($card, ['status']);
        $card->update(['status' => 'active']);

        $this->audit->record(
            'rfid.activated',
            $card,
            $old,
            $this->audit->snapshot($card->fresh(), ['status']),
        );

        return redirect()->route('app.rfid')->with('success', 'کارت فعال شد.');
    }

    public function updatePlate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plate_two' => ['required', 'string', 'max:2'],
            'plate_letter' => ['required', 'string', 'max:4'],
            'plate_three' => ['required', 'string', 'max:3'],
            'plate_province' => ['required', 'string', 'max:2'],
        ]);

        $user = $this->user();
        $vehicle = Vehicle::where('user_id', $user->id)->orderByDesc('is_default')->first();
        if (! $vehicle) {
            return redirect()->route('app.plate')->withErrors(['plate' => 'ابتدا یک خودرو ثبت کنید.']);
        }

        $p2 = $this->enDigits($data['plate_two']);
        $p3 = $this->enDigits($data['plate_three']);
        $prov = $this->enDigits($data['plate_province']);
        $old = $this->audit->snapshot($vehicle, ['plate_two', 'plate_letter', 'plate_three', 'plate_province']);

        $vehicle->update([
            'plate_two' => $p2,
            'plate_letter' => $data['plate_letter'],
            'plate_three' => $p3,
            'plate_province' => $prov,
            'plate_normalized' => $p2.$data['plate_letter'].$p3.$prov,
        ]);

        $this->audit->record(
            'vehicle.plate_updated',
            $vehicle,
            $old,
            $this->audit->snapshot($vehicle->fresh(), ['plate_two', 'plate_letter', 'plate_three', 'plate_province']),
        );

        return redirect()->route('app.plate')->with('success', 'پلاک ذخیره شد.');
    }

    public function markNotificationsRead(): JsonResponse
    {
        UserNotification::where('user_id', $this->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
