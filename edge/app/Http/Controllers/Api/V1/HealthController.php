<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StationIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['app' => true];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['database'] = false;
        }

        $station = StationIdentity::first();

        return response()->json([
            'status' => ($checks['database'] ?? false) ? 'healthy' : 'degraded',
            'service' => 'safircharge-edge',
            'version' => 'v1',
            'checks' => $checks,
            'station' => $station ? [
                'uuid' => $station->uuid,
                'edge_code' => $station->edge_code,
                'name' => $station->name,
            ] : null,
            'time' => now()->toIso8601String(),
        ], ($checks['database'] ?? false) ? 200 : 503);
    }
}
