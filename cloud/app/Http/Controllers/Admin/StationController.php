<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Admin\AdminMetricsService;
use App\Services\Stations\StationAnprService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StationController extends Controller
{
    public function __construct(
        private AdminMetricsService $metrics,
        private StationAnprService $anpr,
    ) {}

    public function index(): View
    {
        return view('admin.stations.index', array_merge(
            $this->metrics->stationsIndex(),
            [
                'kpis' => $this->metrics->networkKpis(),
                'stationsJs' => app(\App\Services\Admin\AdminViewDataService::class)->stationsPageJs(),
            ]
        ));
    }

    public function create(): View
    {
        return view('admin.stations.create', [
            'partners' => \App\Models\Partner::orderBy('name')->get(),
            'tariffs' => \App\Models\Tariff::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', 'unique:stations,code'],
            'city' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:public,private,semi_public'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'wifi_ssid' => ['nullable', 'string', 'max:64'],
            'local_hostname' => ['nullable', 'string', 'max:128'],
            'partner_id' => ['nullable', 'exists:partners,id'],
        ]);

        if (empty($data['code'])) {
            $data['code'] = 'ST-'.Str::upper(Str::random(6));
        }

        $station = Station::create($data);

        return redirect()
            ->route('admin.stations.show', $station)
            ->with('success', 'ایستگاه با موفقیت ایجاد شد.');
    }

    public function show(Station $station): View
    {
        $station->load(['chargers.connectors', 'edgeStation', 'partner']);

        return view('admin.stations.show', compact('station'));
    }

    public function edit(Station $station): View
    {
        $station->load('edgeStation');

        return view('admin.stations.create', [
            'station' => $station,
            'partners' => \App\Models\Partner::orderBy('name')->get(),
            'tariffs' => \App\Models\Tariff::where('status', 'active')->orderBy('name')->get(),
            'anprCameras' => $station->edgeStation
                ? $this->anpr->camerasFor($station->edgeStation)
                : $this->anpr->defaultCameras($station->name),
            'anprEnabled' => $this->anpr->isEnabled($station->edgeStation),
        ]);
    }

    public function update(Request $request, Station $station): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'unique:stations,code,'.$station->id],
            'city' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:public,private,semi_public'],
            'status' => ['required', 'in:active,inactive,maintenance'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'wifi_ssid' => ['nullable', 'string', 'max:64'],
            'local_hostname' => ['nullable', 'string', 'max:128'],
            'partner_id' => ['nullable', 'exists:partners,id'],
        ]);

        $station->update($data);

        if ($request->has('anpr_enabled')) {
            $cameras = $request->input('anpr_cameras', []);
            $this->anpr->saveConfig(
                $station->fresh(),
                $request->boolean('anpr_enabled'),
                is_array($cameras) ? $cameras : [],
            );
        }

        return redirect()
            ->route('admin.stations.show', $station)
            ->with('success', 'ایستگاه به‌روزرسانی شد.');
    }
}
