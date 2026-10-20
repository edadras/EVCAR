<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\RfidCard;
use App\Models\User;
use App\Services\App\MobileAppPresenter;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfidCardController extends Controller
{
    private const AUDIT_KEYS = ['id_tag', 'card_label', 'status', 'user_id', 'organization_id'];

    public function __construct(private AuditService $audit) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.tariffs.index', ['tab' => 'rfid']);
    }

    public function create(): View
    {
        return view('admin.rfid.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $card = RfidCard::create(array_merge(
            ['uuid' => (string) Str::uuid()],
            $this->payload($data, $request),
        ));

        $this->audit->record(
            'rfid.created',
            $card,
            null,
            $this->audit->snapshot($card, self::AUDIT_KEYS),
        );

        return redirect()
            ->route('admin.tariffs.index', ['tab' => 'rfid'])
            ->with('success', 'کارت RFID صادر شد.');
    }

    public function edit(RfidCard $rfid): View
    {
        $rfid->load(['user', 'organization']);
        $auditRows = app(MobileAppPresenter::class)
            ->auditRows($this->audit->forSubject($rfid, 20));

        return view('admin.rfid.create', ['card' => $rfid, 'auditRows' => $auditRows]);
    }

    public function update(Request $request, RfidCard $rfid): RedirectResponse
    {
        $data = $this->validated($request, $rfid);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $old = $this->audit->snapshot($rfid, self::AUDIT_KEYS);
        $rfid->update($this->payload($data, $request, $rfid));

        $this->audit->record(
            'rfid.updated',
            $rfid,
            $old,
            $this->audit->snapshot($rfid->fresh(), self::AUDIT_KEYS),
        );

        return redirect()
            ->route('admin.tariffs.index', ['tab' => 'rfid'])
            ->with('success', 'کارت RFID به‌روزرسانی شد.');
    }

    public function destroy(RfidCard $rfid): RedirectResponse
    {
        $hasHistory = ChargingSession::where('id_tag', $rfid->id_tag)->exists();
        $old = $this->audit->snapshot($rfid, self::AUDIT_KEYS);

        $rfid->delete();

        $this->audit->record('rfid.deleted', $rfid, $old, ['status' => 'deleted']);

        $msg = $hasHistory
            ? 'کارت به‌صورت حذف منطقی ثبت شد. سوابق شارژ حفظ می‌ماند.'
            : 'کارت RFID حذف شد.';

        return redirect()
            ->route('admin.tariffs.index', ['tab' => 'rfid'])
            ->with('success', $msg);
    }

    public function updateStatus(Request $request, RfidCard $rfid): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,blocked,expired'],
        ]);

        $old = $this->audit->snapshot($rfid, ['status']);
        $rfid->update(['status' => $data['status']]);

        $action = match ($data['status']) {
            'active' => 'rfid.activated',
            'blocked' => 'rfid.blocked',
            default => 'rfid.updated',
        };
        $this->audit->record(
            $action,
            $rfid,
            $old,
            $this->audit->snapshot($rfid->fresh(), ['status']),
        );

        $label = match ($data['status']) {
            'active' => 'فعال',
            'blocked' => 'مسدود',
            default => 'منقضی',
        };

        return redirect()
            ->route('admin.tariffs.index', ['tab' => 'rfid'])
            ->with('success', "وضعیت کارت به «{$label}» تغییر کرد.");
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function validated(Request $request, ?RfidCard $card = null): array|RedirectResponse
    {
        $data = $request->validate([
            'card_type' => ['required', 'in:personal,org,anon'],
            'id_tag' => [
                'required', 'string', 'max:64',
                Rule::unique('rfid_cards', 'id_tag')->ignore($card?->id),
            ],
            'user_id' => ['nullable', 'exists:users,id'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'card_label' => ['nullable', 'string', 'max:128'],
            'status' => ['required', 'in:active,blocked,expired'],
            'expires_at' => ['nullable', 'date'],
            'offline_allowed' => ['nullable'],
        ]);

        if ($data['card_type'] === 'personal' && empty($data['user_id'])) {
            return back()->withErrors(['user_id' => 'برای کارت شخصی انتخاب کاربر الزامی است.'])->withInput();
        }

        if ($data['card_type'] === 'org' && empty($data['organization_id'])) {
            return back()->withErrors(['organization_id' => 'برای کارت سازمانی انتخاب سازمان الزامی است.'])->withInput();
        }

        if (! empty($data['user_id'])) {
            $user = User::find($data['user_id']);
            if (! $user || $user->role !== UserRole::Customer) {
                return back()->withErrors(['user_id' => 'کاربر انتخاب‌شده معتبر نیست.'])->withInput();
            }
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function payload(array $data, Request $request, ?RfidCard $card = null): array
    {
        $meta = array_merge($card?->meta ?? [], [
            'card_type' => $data['card_type'],
            'offline_allowed' => $request->boolean('offline_allowed'),
        ]);

        $userId = match ($data['card_type']) {
            'anon' => null,
            'personal' => $data['user_id'] ?? null,
            default => $data['user_id'] ?? null,
        };

        return [
            'id_tag' => $data['id_tag'],
            'user_id' => $userId,
            'organization_id' => $data['card_type'] === 'org' ? ($data['organization_id'] ?? null) : null,
            'card_label' => $data['card_label'] ?? null,
            'status' => $data['status'],
            'issued_at' => $card?->issued_at ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'meta' => $meta,
        ];
    }

    public static function generateIdTag(): string
    {
        return 'RFID-'.Str::upper(Str::random(8));
    }
}
