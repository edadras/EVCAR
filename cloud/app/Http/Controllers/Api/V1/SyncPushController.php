<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EdgeStation;
use App\Services\Sync\SyncIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncPushController extends Controller
{
    public function __construct(private SyncIngestService $ingest) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.event_uuid' => ['required', 'uuid'],
            'events.*.event_type' => ['required', 'string', 'max:64'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.payload' => ['required', 'array'],
        ]);

        /** @var EdgeStation $edge */
        $edge = $request->attributes->get('edge_station');

        $result = $this->ingest->ingest($edge, $validated['events']);

        return response()->json($result);
    }
}
