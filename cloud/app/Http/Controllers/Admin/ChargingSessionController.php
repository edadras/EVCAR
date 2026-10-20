<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminMetricsService;
use Illuminate\View\View;

class ChargingSessionController extends Controller
{
    public function index(AdminMetricsService $metrics): View
    {
        return view('admin.sessions.index', $metrics->sessionsIndex());
    }
}
