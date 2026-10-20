<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(PartnerMetricsService $metrics): View
    {
        $partner = auth()->user()->partner;

        return view('partner.dashboard', $metrics->dashboard($partner));
    }
}
