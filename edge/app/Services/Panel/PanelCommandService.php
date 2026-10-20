<?php

namespace App\Services\Panel;

use App\Models\EdgeConnector;
use App\Models\PanelCommandLog;
use App\Models\User;
use App\Services\Ocpp\EdgeCommandExecutor;
use Illuminate\Support\Str;

class PanelCommandService
{
    public function __construct(private EdgeCommandExecutor $executor) {}

    /** @param array<string, mixed> $payload */
    public function execute(User $operator, string $commandType, ?int $connectorId, array $payload = []): PanelCommandLog
    {
        $connector = $connectorId
            ? EdgeConnector::with('charger')->findOrFail($connectorId)
            : null;

        $charger = $connector?->charger;
        if (! $charger && $commandType !== 'reset') {
            throw new \InvalidArgumentException('کانکتور برای این دستور الزامی است.');
        }

        $command = [
            'command_type' => $commandType,
            'charge_point_id' => (string) ($charger?->charge_point_id ?? $payload['charge_point_id'] ?? ''),
            'connector_number' => (int) ($connector?->connector_number ?? $payload['connector_number'] ?? 1),
            'payload' => $payload,
        ];

        $log = PanelCommandLog::create([
            'uuid' => (string) Str::uuid(),
            'source' => 'panel',
            'user_id' => $operator->id,
            'connector_id' => $connector?->id,
            'command_type' => $commandType,
            'payload' => $payload,
            'status' => 'running',
        ]);

        try {
            $result = $this->executor->execute($command);
            $simulated = (bool) ($result['simulated'] ?? false);

            if ($commandType === 'change_availability' && $connector) {
                $type = $payload['type'] ?? 'Inoperative';
                $connector->update([
                    'ocpp_status' => $type === 'Operative' ? 'Available' : 'Unavailable',
                ]);
            }

            $log->update([
                'status' => 'completed',
                'result' => $result,
                'simulated' => $simulated,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $log->fresh();
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 25, ?string $source = null): array
    {
        $q = PanelCommandLog::with('user')->latest('id')->limit($limit);
        if ($source) {
            $q->where('source', $source);
        }

        return $q->get()
            ->map(fn (PanelCommandLog $log) => [
                'uuid' => $log->uuid,
                'source' => $log->source ?? 'panel',
                'source_label' => ($log->source ?? 'panel') === 'cloud' ? 'مرکز ابری' : 'پنل محلی',
                'cloud_command_uuid' => $log->cloud_command_uuid,
                'type' => $log->command_type,
                'status' => $log->status,
                'error' => $log->error,
                'simulated' => $log->simulated,
                'connector_id' => $log->connector_id,
                'operator' => $log->user?->name,
                'at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
