<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminMetricsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminMetricsService $metrics): View
    {
        $viewData = app(\App\Services\Admin\AdminViewDataService::class);

        return view('admin.dashboard', [
            'kpis' => $metrics->networkKpis(),
            'chart' => $metrics->hourlyChart(),
            'breakdown' => $metrics->connectorBreakdown(),
            'activeSessions' => $metrics->activeSessionsTable(),
            'mapMarkers' => $metrics->stationMapMarkers(),
            'connectorCounts' => $viewData->connectorDcAcCounts(),
            'alerts' => $viewData->dashboardAlerts(),
        ]);
    }
}
