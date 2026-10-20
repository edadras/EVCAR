<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\Partner;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettlementController extends Controller
{
    public function create(): View
    {
        $partners = Partner::where('status', 'active')->orderBy('name')->get();
        $preview = [];

        foreach ($partners as $partner) {
            $stationIds = Station::where('partner_id', $partner->id)->pluck('id');
            $monthStart = now()->startOfMonth();
            $monthEnd = now()->endOfMonth();
            $gross = ChargingSession::whereIn('station_id', $stationIds)
                ->whereBetween('started_at', [$monthStart, $monthEnd])
                ->sum('cost_amount');
            $preview[$partner->id] = [
                'gross' => $gross,
                'share' => round($gross * ($partner->revenue_share_percent / 100), 2),
            ];
        }

        return view('admin.settlement.create', compact('partners', 'preview'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'partner_id' => ['required', 'exists:partners,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'maintenance_fee' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:128'],
        ]);

        $partner = Partner::findOrFail($data['partner_id']);
        $stationIds = Station::where('partner_id', $partner->id)->pluck('id');
        $periodStart = $data['period_start'];
        $periodEnd = $data['period_end'];

        $sessions = ChargingSession::whereIn('station_id', $stationIds)
            ->whereBetween('started_at', [$periodStart, $periodEnd.' 23:59:59']);

        $gross = (float) $sessions->sum('cost_amount');
        $energy = (float) $sessions->sum('energy_kwh');
        $sessionCount = (int) $sessions->count();
        $partnerShare = round($gross * ($partner->revenue_share_percent / 100), 2);
        $platformFee = round($gross - $partnerShare, 2);
        $maintenance = (float) ($data['maintenance_fee'] ?? 0) * 10;
        $netShare = max(0, $partnerShare - $maintenance);

        $settlement = Settlement::create([
            'uuid' => (string) Str::uuid(),
            'settlement_number' => 'STL-'.now()->format('ym').'-'.Str::upper(Str::random(4)),
            'partner_id' => $partner->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'pending',
            'gross_revenue' => $gross,
            'platform_fee' => $platformFee,
            'partner_share' => $netShare,
            'energy_kwh' => $energy,
            'session_count' => $sessionCount,
            'payment_reference' => $data['payment_reference'] ?? null,
            'meta' => ['maintenance_fee' => $maintenance],
        ]);

        foreach (Station::whereIn('id', $stationIds)->get() as $station) {
            $stGross = (float) ChargingSession::where('station_id', $station->id)
                ->whereBetween('started_at', [$periodStart, $periodEnd.' 23:59:59'])
                ->sum('cost_amount');
            if ($stGross <= 0) {
                continue;
            }
            SettlementItem::create([
                'settlement_id' => $settlement->id,
                'station_id' => $station->id,
                'gross_revenue' => $stGross,
                'partner_share' => round($stGross * ($partner->revenue_share_percent / 100), 2),
                'energy_kwh' => ChargingSession::where('station_id', $station->id)
                    ->whereBetween('started_at', [$periodStart, $periodEnd.' 23:59:59'])
                    ->sum('energy_kwh'),
                'session_count' => ChargingSession::where('station_id', $station->id)
                    ->whereBetween('started_at', [$periodStart, $periodEnd.' 23:59:59'])
                    ->count(),
            ]);
        }

        return redirect()
            ->route('admin.settlement')
            ->with('success', 'تسویه با شماره '.$settlement->settlement_number.' ایجاد شد.');
    }
}
