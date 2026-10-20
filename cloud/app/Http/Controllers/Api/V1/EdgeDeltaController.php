<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EdgeStation;
use App\Services\Sync\EdgeSyncPayloadBuilder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EdgeDeltaController extends Controller
{
    public function __construct(private EdgeSyncPayloadBuilder $builder) {}

    public function show(Request $request): JsonResponse
    {
        /** @var EdgeStation $edge */
        $edge = $request->attributes->get('edge_station');

        $since = $request->query('since')
            ? Carbon::parse((string) $request->query('since'))
            : now()->subYears(10);

        return response()->json([
            'data' => $this->builder->buildDelta($edge, $since),
        ]);
    }
}
