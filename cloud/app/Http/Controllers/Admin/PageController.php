<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Admin\AdminMetricsService;
use App\Services\Admin\AdminViewDataService;
use App\Services\Stations\StationAnprService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private AdminMetricsService $metrics,
        private AdminViewDataService $viewData,
    ) {}

    public function monitoring(): View
    {
        return view('admin.monitoring', $this->metrics->monitoring());
    }

    public function reservations(): View
    {
        $bundle = $this->viewData->reservationsJs(null, request()->only([
            'station_id', 'day', 'date_from', 'date_to',
        ]));

        return view('admin.reservations', [
            'station' => $bundle['station'] ?? null,
            'stations' => Station::orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('name')->get(['id', 'name', 'status']),
            'reservableStations' => Station::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'filters' => $bundle['filters'] ?? [],
            'kpis' => $bundle['kpis'] ?? [],
            'reservationsJs' => $bundle,
        ]);
    }

    public function anpr(): View
    {
        $stationId = request()->integer('station_id') ?: null;
        $bundle = $this->viewData->anprBundle($stationId);

        return view('admin.anpr', [
            'stations' => $bundle['stations'],
            'filters' => $bundle['filters'],
            'kpis' => $bundle['kpis'],
            'anprJs' => $bundle,
        ]);
    }

    public function wallet(): View
    {
        $bundle = $this->viewData->walletBundle();

        return view('admin.wallet', [
            'kpis' => $bundle['kpis'],
            'paymentGateways' => $bundle['paymentGateways'],
            'walletJs' => $bundle,
        ]);
    }

    public function finance(): View
    {
        return view('admin.finance', array_merge($this->metrics->financeIndex(), [
            'financeJs' => $this->viewData->financeJs(),
        ]));
    }

    public function reports(): View
    {
        $bundle = $this->viewData->reportsBundle(request()->only([
            'range', 'city', 'connector', 'date_from', 'date_to',
        ]));

        return view('admin.reports', [
            'reportsJs' => $bundle,
            'filters' => $bundle['filters'] ?? [],
        ]);
    }

    public function tariffs(): View
    {
        return view('admin.tariffs.index', $this->metrics->tariffsIndex());
    }

    public function users(): View
    {
        return view('admin.users.index', array_merge($this->metrics->usersIndex(), [
            'usersJs' => $this->viewData->usersJs(),
        ]));
    }

    public function partners(): View
    {
        return view('admin.partners.index', array_merge($this->metrics->partnersIndex(), [
            'partnersJs' => $this->viewData->partnersJs(),
        ]));
    }

    public function support(): View
    {
        return view('admin.support', array_merge($this->metrics->supportIndex(), [
            'supportJs' => $this->viewData->supportJs(),
        ]));
    }

    public function inventory(): View
    {
        return view('admin.inventory', array_merge($this->metrics->inventoryIndex(), [
            'inventoryJs' => $this->viewData->inventoryJs(),
        ]));
    }

    public function repairs(): View
    {
        return view('admin.repairs', array_merge($this->metrics->repairsIndex(), [
            'repairsJs' => $this->viewData->repairsJs(),
        ]));
    }

    public function sync(): View
    {
        return view('admin.sync.index', $this->metrics->syncIndex());
    }

    public function syncSettings(): View
    {
        return view('admin.sync.settings', $this->metrics->syncSettingsIndex());
    }

    public function notifications(): View
    {
        return view('admin.notifications.index', $this->metrics->notificationsIndex());
    }

    public function campaigns(): View
    {
        return view('admin.campaigns', $this->metrics->campaignsIndex());
    }

    public function team(): View
    {
        return view('admin.team', $this->metrics->teamIndex());
    }

    public function settings(): View
    {
        return view('admin.settings', $this->metrics->settingsIndex());
    }

    public function settlement(): View
    {
        return view('admin.settlement.show', $this->metrics->settlementShow());
    }

    public function remote(): View
    {
        $stations = Station::orderBy('name')->get(['id', 'name', 'code']);
        $stationId = request('station');
        $station = $stationId
            ? Station::with(['chargers.connectors', 'edgeStation'])->find($stationId)
            : Station::with(['chargers.connectors', 'edgeStation'])->first();
        $remoteJs = $station
            ? $this->viewData->remoteStationJs($station)
            : ['devs' => [], 'stationName' => '—', 'stationCode' => '—', 'online' => false];

        $anprPanel = $station
            ? app(StationAnprService::class)->panelData($station)
            : ['enabled' => false];

        return view('admin.remote', compact('station', 'stations', 'remoteJs', 'anprPanel'));
    }
}
