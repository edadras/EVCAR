<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StationIdentity;
use Illuminate\Http\JsonResponse;

class StationController extends Controller
{
    public function config(): JsonResponse
    {
        $station = StationIdentity::firstOrFail();

        return response()->json([
            'data' => [
                'uuid' => $station->uuid,
                'edge_code' => $station->edge_code,
                'name' => $station->name,
                'cloud_station_uuid' => $station->cloud_station_uuid,
                'local_hostname' => $station->local_hostname,
                'wifi_ssid' => $station->wifi_ssid,
                'offline_mode_enabled' => $station->offline_mode_enabled,
                'cloud_api_url' => $station->cloud_api_url,
                'last_cloud_sync_at' => $station->last_cloud_sync_at?->toIso8601String(),
            ],
        ]);
    }
}
