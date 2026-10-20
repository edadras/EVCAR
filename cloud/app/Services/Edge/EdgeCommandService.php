<?php

namespace App\Services\Edge;

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeCommand;
use App\Models\EdgeStation;
use App\Models\Station;
use App\Models\User;
use App\Services\Charging\CloudChargingSessionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EdgeCommandService
{
    /** @var list<string> */
    public const TYPES = [
        'reset', 'unlock', 'remote_stop', 'change_availability',
        'set_charging_profile', 'get_diagnostics', 'update_firmware',
        'remote_start', 'trigger_status', 'get_configuration', 'change_configuration',
        'reserve_now', 'cancel_reservation', 'clear_charging_profile',
    ];

    public function queue(
        Station $station,
        string $commandType,
        ?Connector $connector = null,
        array $payload = [],
        ?User $requester = null,
    ): EdgeCommand {
        $edge = $station->edgeStation;
        if (! $edge) {
            throw new \RuntimeException('این ایستگاه کنترلر Edge ندارد.');
        }

        if (! in_array($commandType, self::TYPES, true)) {
            throw new \InvalidArgumentException('نوع دستور نامعتبر است.');
        }

        if ($connector) {
            $connector->loadMissing('charger');
        }

        if ($commandType === 'remote_stop' && $connector) {
            $txId = ChargingSession::query()
                ->where('connector_id', $connector->id)
                ->where('status', 'active')
                ->whereNull('stopped_at')
                ->value('ocpp_transaction_id');
            if ($txId) {
                $payload['transaction_id'] = (int) $txId;
            }
        }

        return EdgeCommand::create([
            'uuid' => (string) Str::uuid(),
            'edge_station_id' => $edge->id,
            'station_id' => $station->id,
            'connector_id' => $connector?->id,
            'charge_point_id' => $connector?->charger?->charge_point_id ?? $payload['charge_point_id'] ?? null,
            'connector_number' => $connector?->connector_number ?? $payload['connector_number'] ?? null,
            'command_type' => $commandType,
            'payload' => $payload,
            'status' => 'pending',
            'requested_by' => $requester?->id,
            'requested_at' => now(),
        ]);
    }

    /** @return Collection<int, EdgeCommand> */
    public function pullPending(EdgeStation $edge, int $limit = 20): Collection
    {
        return EdgeCommand::query()
            ->where('edge_station_id', $edge->id)
            ->where('status', 'pending')
            ->orderBy('requested_at')
            ->limit($limit)
            ->get()
            ->each(function (EdgeCommand $cmd) {
                $cmd->update([
                    'status' => 'dispatched',
                    'dispatched_at' => now(),
                ]);
            });
    }

    public function acknowledge(EdgeStation $edge, string $commandUuid, bool $ok, array $result = [], ?string $error = null): EdgeCommand
    {
        $cmd = EdgeCommand::where('edge_station_id', $edge->id)
            ->where('uuid', $commandUuid)
            ->firstOrFail();

        if ($cmd->status === 'cancelled') {
            return $cmd;
        }

        $cmd->update([
            'status' => $ok ? 'completed' : 'failed',
            'result' => $result ?: null,
            'error_message' => $error,
            'completed_at' => now(),
        ]);

        if (! $ok && $cmd->command_type === 'remote_start' && ! empty($cmd->payload['cloud_session_uuid'])) {
            app(CloudChargingSessionService::class)
                ->startFailed((string) $cmd->payload['cloud_session_uuid'], $error);
        }

        return $cmd->fresh();
    }

    /** @return list<array<string, mixed>> */
    public function serializeForEdge(EdgeCommand $cmd): array
    {
        return [
            'uuid' => $cmd->uuid,
            'command_type' => $cmd->command_type,
            'charge_point_id' => $cmd->charge_point_id,
            'connector_number' => $cmd->connector_number,
            'payload' => $cmd->payload ?? [],
            'requested_at' => $cmd->requested_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function recentForStation(Station $station, int $limit = 15): array
    {
        return EdgeCommand::with('requester')
            ->where('station_id', $station->id)
            ->latest('requested_at')
            ->limit($limit)
            ->get()
            ->map(fn (EdgeCommand $c) => [
                'uuid' => $c->uuid,
                'type' => $c->command_type,
                'status' => $c->status,
                'charge_point_id' => $c->charge_point_id,
                'connector_number' => $c->connector_number,
                'requested_at' => $c->requested_at?->format('H:i:s'),
                'completed_at' => $c->completed_at?->format('H:i:s'),
                'error' => $c->error_message,
                'requester' => $c->requester?->name,
            ])
            ->all();
    }
}
