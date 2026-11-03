<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminLiveService;
use App\Services\Admin\AdminMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminMetricsService $metrics, AdminLiveService $live): View
    {
        $snapshot = $live->dashboard();

        return view('admin.dashboard', [
            'live' => $snapshot,
            'kpis' => $snapshot['kpis'],
            'mapMarkers' => $metrics->stationMapMarkers(),
        ]);
    }
}
