<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\StationIdentity;
use App\Services\Panel\PanelLiveService;
use App\Services\Panel\PanelSyncService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private PanelLiveService $live,
        private PanelSyncService $sync,
    ) {}

    public function dashboard(): View
    {
        $station = StationIdentity::first();
        $live = $this->live->liveConnectors();
        $syncStatus = $this->sync->status();
        $activeCount = collect($live['connectors'])->filter(fn ($c) => ($c['ocppStatus'] ?? '') === 'Charging')->count();

        return view('panel.dashboard', [
            'station' => $station,
            'syncStatus' => $syncStatus,
            'connectorCount' => count($live['connectors']),
            'activeSessions' => $activeCount,
            'recentSessions' => $this->live->recentSessions(8),
        ]);
    }

    public function remote(): View
    {
        return view('panel.remote', [
            'remoteJs' => $this->live->jsPayload(),
        ]);
    }

    public function sync(): View
    {
        return view('panel.sync', [
            'syncStatus' => $this->sync->status(),
        ]);
    }

    public function sessions(): View
    {
        return view('panel.sessions', [
            'sessions' => $this->live->recentSessions(50),
        ]);
    }

    public function setup(): View
    {
        return view('panel.setup');
    }
}
