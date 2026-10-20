<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EdgeStation;
use App\Services\Sync\EdgeSyncPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EdgeSnapshotController extends Controller
{
    public function __construct(private EdgeSyncPayloadBuilder $builder) {}

    public function show(Request $request): JsonResponse
    {
        /** @var EdgeStation $edge */
        $edge = $request->attributes->get('edge_station');

        return response()->json([
            'data' => $this->builder->buildSnapshot($edge),
        ]);
    }
}
