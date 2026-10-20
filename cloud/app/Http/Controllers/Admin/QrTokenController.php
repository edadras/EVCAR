<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charger;
use App\Models\Connector;
use App\Models\QrToken;
use App\Models\Station;
use App\Services\Charging\ConnectorAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QrTokenController extends Controller
{
    public function __construct(
        private ConnectorAvailabilityService $availability,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.tariffs.index', ['tab' => 'qr']);
    }

    public function create(): View
    {
        $stations = Station::with('chargers.connectors')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.qr.create', compact('stations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $token = QrToken::create([
            'station_id' => $data['station_id'],
            'charger_id' => $data['charger_id'],
            'connector_id' => $data['connector_id'],
            'offline_capable' => $request->boolean('offline_capable', true),
            'status' => 'active',
        ]);

        Connector::where('id', $data['connector_id'])->update(['qr_token_uuid' => $token->uuid]);

        return redirect()
            ->route('admin.tariffs.index', ['tab' => 'qr'])
            ->with('success', 'کد QR برای کانکتور ایجاد شد.');
    }

    public function destroy(QrToken $qr): RedirectResponse
    {
        if ($qr->connector_id) {
            Connector::where('id', $qr->connector_id)
                ->where('qr_token_uuid', $qr->uuid)
                ->update(['qr_token_uuid' => null]);
        }

        $qr->delete();

        return redirect()
            ->route('admin.tariffs.index', ['tab' => 'qr'])
            ->with('success', 'کد QR حذف شد.');
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function validated(Request $request): array|RedirectResponse
    {
        $data = $request->validate([
            'station_id' => ['required', 'exists:stations,id'],
            'charger_id' => ['required', 'exists:chargers,id'],
            'connector_id' => ['required', 'exists:connectors,id'],
            'offline_capable' => ['nullable'],
        ]);

        $charger = Charger::find($data['charger_id']);
        if (! $charger || (int) $charger->station_id !== (int) $data['station_id']) {
            return back()->withErrors(['charger_id' => 'شارژر انتخاب‌شده به این ایستگاه تعلق ندارد.'])->withInput();
        }

        $connector = Connector::find($data['connector_id']);
        if (! $connector || (int) $connector->charger_id !== (int) $data['charger_id']) {
            return back()->withErrors(['connector_id' => 'کانکتور انتخاب‌شده به این شارژر تعلق ندارد.'])->withInput();
        }

        $duplicate = QrToken::where('connector_id', $data['connector_id'])
            ->where('status', 'active')
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['connector_id' => 'برای این کانکتور قبلاً کد QR فعال ثبت شده است.'])->withInput();
        }

        if ($reason = $this->availability->qrIssueBlockReason($connector)) {
            return back()->withErrors(['connector_id' => $reason])->withInput();
        }

        return $data;
    }
}
