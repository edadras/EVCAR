<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\Partner;
use App\Models\Station;
use App\Services\Finance\SettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $settlement = app(SettlementService::class)->generate(
            Partner::findOrFail($data['partner_id']),
            $data['period_start'],
            $data['period_end'],
            (float) ($data['maintenance_fee'] ?? 0) * 10,
            $data['payment_reference'] ?? null,
        );

        return redirect()
            ->route('admin.settlement')
            ->with('success', 'تسویه با شماره '.$settlement->settlement_number.' ایجاد شد.');
    }
}
