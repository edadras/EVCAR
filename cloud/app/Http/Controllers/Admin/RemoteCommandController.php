<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Connector;
use App\Models\Station;
use App\Services\Edge\EdgeCommandService;
use App\Services\Ocpp\CloudCommandExecutor;
use App\Services\Stations\RemoteStationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteCommandController extends Controller
{
    public function __construct(
        private EdgeCommandService $commands,
        private RemoteStationService $remote,
    ) {}

    public function store(Request $request, Station $station): JsonResponse
    {
        $data = $request->validate([
            'command_type' => ['required', 'string', 'max:40'],
            'connector_id' => ['nullable', 'integer'],
            'payload' => ['nullable', 'array'],
        ]);

        $connector = null;
        if (! empty($data['connector_id'])) {
            $connector = Connector::query()
                ->with('charger')
                ->where('id', $data['connector_id'])
                ->whereHas('charger', fn ($q) => $q->where('station_id', $station->id))
                ->firstOrFail();
        }

        // ایستگاه بدون Edge: دستور مستقیم روی سرور OCPP ابری اجرا می‌شود.
        $directCharger = $connector?->charger ?? $station->chargers()->first();
        if (! $station->edgeStation && $directCharger?->connectsDirectly()) {
            $cmd = app(CloudCommandExecutor::class)->run(
                $station,
                $data['command_type'],
                $connector,
                ($data['payload'] ?? []) + ['charge_point_id' => $directCharger->charge_point_id],
                $request->user(),
            );

            return response()->json([
                'ok' => $cmd->status === 'completed',
                'uuid' => $cmd->uuid,
                'status' => $cmd->status,
                'error' => $cmd->error_message,
                'message' => $cmd->status === 'completed' ? 'دستور مستقیم روی شارژر اجرا شد.' : ($cmd->error_message ?? 'اجرای دستور ناموفق بود.'),
            ], $cmd->status === 'completed' ? 200 : 422);
        }

        try {
            $cmd = $this->commands->queue(
                $station,
                $data['command_type'],
                $connector,
                $data['payload'] ?? [],
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'uuid' => $cmd->uuid,
            'status' => $cmd->status,
            'message' => 'دستور در صف ایستگاه قرار گرفت؛ Edge آن را محلی روی OCPP اجرا می‌کند.',
        ]);
    }

    public function recent(Station $station): JsonResponse
    {
        return response()->json([
            'commands' => $this->commands->recentForStation($station),
        ]);
    }

    public function live(Station $station): JsonResponse
    {
        $live = $this->remote->liveConnectors($station);

        return response()->json([
            'online' => $live['online'],
            'edge_online' => $live['edge_online'],
            'connectors' => $live['connectors'],
        ]);
    }
}
