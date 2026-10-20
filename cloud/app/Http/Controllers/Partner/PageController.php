<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Partner\PartnerMetricsService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private PartnerMetricsService $metrics) {}

    private function partner()
    {
        return auth()->user()->partner;
    }

    public function stations(): View
    {
        $data = $this->metrics->stations($this->partner());
        $partnerId = $this->partner()->id;
        $data['mapMarkers'] = collect($data['mapMarkers'])->filter(
            fn ($m) => Station::find($m['id'])?->partner_id === $partnerId
        )->values()->all();

        return view('partner.stations', $data);
    }

    public function payments(): View
    {
        return view('partner.payments', $this->metrics->payments($this->partner()));
    }

    public function reports(): View
    {
        return view('partner.reports', $this->metrics->reports($this->partner()));
    }

    public function support(): View
    {
        return view('partner.support', [
            'partner' => $this->partner(),
            'tickets' => $this->metrics->support($this->partner()),
        ]);
    }

    public function profile(): View
    {
        return view('partner.profile', [
            'partner' => $this->partner(),
            'user' => auth()->user(),
        ]);
    }
}
