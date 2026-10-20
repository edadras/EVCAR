<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charger;
use App\Models\Connector;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChargerController extends Controller
{
    public function create(Station $station): View
    {
        return view('admin.chargers.create', compact('station'));
    }

    public function store(Request $request, Station $station): RedirectResponse
    {
        $data = $request->validate([
            'charge_point_id' => ['required', 'string', 'max:64', 'unique:chargers,charge_point_id'],
            'vendor' => ['nullable', 'string', 'max:128'],
            'model' => ['nullable', 'string', 'max:128'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'max_power_kw' => ['nullable', 'integer', 'min:1', 'max:500'],
            'connector_type' => ['nullable', 'string', 'max:32'],
            'connector_count' => ['required', 'integer', 'min:1', 'max:4'],
            'connection_mode' => ['nullable', 'in:auto,edge,direct'],
            'ocpp_password' => ['nullable', 'string', 'min:8', 'max:40'],
        ]);

        $charger = $station->chargers()->create([
            'charge_point_id' => $data['charge_point_id'],
            'vendor' => $data['vendor'] ?? null,
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'max_power_kw' => $data['max_power_kw'] ?? null,
            'connector_type' => $data['connector_type'] ?? null,
            // وضعیت اداری فعال؛ آنلاین بودن را خود اتصال OCPP تعیین می‌کند.
            'status' => 'active',
            'is_online' => false,
            'connection_mode' => $data['connection_mode'] ?? 'auto',
            'meta' => array_filter(['ocpp_password' => $data['ocpp_password'] ?? null]),
        ]);

        for ($i = 1; $i <= $data['connector_count']; $i++) {
            Connector::create([
                'charger_id' => $charger->id,
                'connector_number' => $i,
                'ocpp_status' => 'Unavailable',
                'operational_status' => 'active',
            ]);
        }

        return redirect()
            ->route('admin.stations.show', $station)
            ->with('success', 'شارژر و کانکتورها ثبت شدند.');
    }

    public function show(Charger $charger): View
    {
        $charger->load(['station', 'connectors']);

        return view('admin.chargers.show', compact('charger'));
    }
}
