<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EdgeStation;
use App\Services\Edge\EdgeCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EdgeCommandsController extends Controller
{
    public function __construct(private EdgeCommandService $commands) {}

    public function index(Request $request): JsonResponse
    {
        /** @var EdgeStation $edge */
        $edge = $request->attributes->get('edge_station');
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        $pending = $this->commands->pullPending($edge, $limit);

        return response()->json([
            'data' => $pending->map(fn ($c) => $this->commands->serializeForEdge($c))->values()->all(),
        ]);
    }

    public function acknowledge(Request $request, string $uuid): JsonResponse
    {
        /** @var EdgeStation $edge */
        $edge = $request->attributes->get('edge_station');

        $data = $request->validate([
            'ok' => ['required', 'boolean'],
            'result' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        $cmd = $this->commands->acknowledge(
            $edge,
            $uuid,
            (bool) $data['ok'],
            $data['result'] ?? [],
            $data['error'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'uuid' => $cmd->uuid,
            'status' => $cmd->status,
        ]);
    }
}
