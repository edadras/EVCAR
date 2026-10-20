<?php

namespace App\Services\Ocpp;

use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\EdgeCommand;
use App\Models\Station;
use App\Models\User;
use App\Services\Charging\DirectChargingService;
use Illuminate\Support\Str;

/**
 * اجرای دستورهای مدیریتی روی شارژرهای متصل مستقیم به سرور OCPP ابری.
 * نتیجه در همان جدول edge_commands (بدون edge_station_id) ثبت می‌شود تا تاریخچه یکسان باشد.
 */
class CloudCommandExecutor
{
    private const FAILED_STATUSES = ['Rejected', 'NotSupported', 'UnlockFailed', 'NotImplemented'];

    public function __construct(
        private CloudOcppCommander $commander,
        private DirectChargingService $charging,
    ) {}

    /** @param array<string, mixed> $payload */
    public function run(Station $station, string $type, ?Connector $connector, array $payload, ?User $requester): EdgeCommand
    {
        $connector?->loadMissing('charger');
        $chargePointId = (string) ($connector?->charger?->charge_point_id ?? $payload['charge_point_id'] ?? '');
        $number = (int) ($connector?->connector_number ?? $payload['connector_number'] ?? 1);

        $cmd = EdgeCommand::create([
            'uuid' => (string) Str::uuid(),
            'edge_station_id' => null,
            'station_id' => $station->id,
            'connector_id' => $connector?->id,
            'charge_point_id' => $chargePointId ?: null,
            'connector_number' => $number,
            'command_type' => $type,
            'payload' => $payload,
            'status' => 'dispatched',
            'requested_by' => $requester?->id,
            'requested_at' => now(),
            'dispatched_at' => now(),
        ]);

        try {
            if ($chargePointId === '') {
                throw new \InvalidArgumentException('شارژر برای این دستور مشخص نشده است.');
            }
            $result = $this->execute($type, $chargePointId, $number, $connector, $payload);
            $cmd->update(['status' => 'completed', 'result' => $result, 'completed_at' => now()]);
        } catch (\Throwable $e) {
            $cmd->update(['status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 500), 'completed_at' => now()]);
        }

        return $cmd->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function execute(string $type, string $cp, int $number, ?Connector $connector, array $payload): array
    {
        if ($type === 'remote_stop') {
            $session = $connector ? ChargingSession::where('connector_id', $connector->id)->where('status', 'active')->latest('id')->first() : null;
            if (! $session) {
                throw new \RuntimeException('جلسه شارژ فعالی روی این کانکتور نیست.');
            }
            $stopped = $this->charging->stop($session);

            return ['session_uuid' => $stopped->uuid, 'status' => $stopped->status];
        }

        [$action, $body] = OcppCommandMap::build($type, $number, $payload);
        $response = $this->commander->call($cp, $action, $body);
        $status = (string) ($response['status'] ?? '');
        if (in_array($status, self::FAILED_STATUSES, true)) {
            throw new \RuntimeException("شارژر دستور {$action} را اجرا نکرد (پاسخ: {$status}).");
        }

        return array_merge(['action' => $action, 'direct' => true], $response);
    }
}
