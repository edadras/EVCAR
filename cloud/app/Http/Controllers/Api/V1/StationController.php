<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;

class StationController extends Controller
{
    public function index(): JsonResponse
    {
        $stations = Station::with(['chargers.connectors'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (Station $s) => [
                'uuid' => $s->uuid,
                'code' => $s->code,
                'name' => $s->name,
                'city' => $s->city,
                'province' => $s->province,
                'address' => $s->address,
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
                'chargers_count' => $s->chargers->count(),
                'chargers' => $s->chargers->map(fn ($c) => [
                    'uuid' => $c->uuid,
                    'charge_point_id' => $c->charge_point_id,
                    'is_online' => $c->is_online,
                    'max_power_kw' => $c->max_power_kw,
                    'connectors' => $c->connectors->map(fn ($cn) => [
                        'uuid' => $cn->uuid,
                        'number' => $cn->connector_number,
                        'status' => $cn->ocpp_status,
                    ]),
                ]),
            ]);

        return response()->json(['data' => $stations]);
    }

    public function show(Station $station): JsonResponse
    {
        if ($station->status !== 'active') {
            return response()->json(['message' => 'ایستگاه در دسترس نیست.'], 404);
        }

        $station->load(['chargers.connectors']);

        return response()->json([
            'data' => [
                'uuid' => $station->uuid,
                'code' => $station->code,
                'name' => $station->name,
                'city' => $station->city,
                'address' => $station->address,
                'latitude' => $station->latitude,
                'longitude' => $station->longitude,
                'chargers' => $station->chargers,
            ],
        ]);
    }
}
