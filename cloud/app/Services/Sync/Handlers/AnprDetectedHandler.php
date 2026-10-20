<?php

namespace App\Services\Sync\Handlers;

use App\Models\AnprEvent;
use App\Models\ChargingSession;
use App\Models\EdgeStation;
use App\Models\Vehicle;
use Illuminate\Support\Str;

class AnprDetectedHandler
{
    /** @param  array<string, mixed>  $payload */
    public function handle(EdgeStation $edge, array $payload): AnprEvent
    {
        $detectionUuid = (string) ($payload['detection_uuid'] ?? $payload['uuid'] ?? '');
        if ($detectionUuid === '') {
            throw new \InvalidArgumentException('detection_uuid الزامی است.');
        }

        $plateNormalized = $this->normalizePlate($payload);
        $vehicle = $this->findVehicle($payload, $plateNormalized);

        $sessionId = null;
        $sessionUuid = $payload['charging_session_uuid'] ?? null;
        if ($sessionUuid) {
            $sessionId = ChargingSession::where('uuid', $sessionUuid)->value('id');
        }

        $confidence = isset($payload['confidence']) ? (float) $payload['confidence'] : null;
        $outcome = $this->resolveOutcome($payload, $vehicle, $confidence);
        $meta = $this->buildMeta($payload, $edge, $outcome);

        $existing = AnprEvent::where('edge_detection_uuid', $detectionUuid)->first();
        if ($existing) {
            $existing->update([
                'confidence' => $confidence ?? $existing->confidence,
                'vehicle_id' => $vehicle?->id ?? $existing->vehicle_id,
                'user_id' => $vehicle?->user_id ?? $existing->user_id,
                'charging_session_id' => $sessionId ?? $existing->charging_session_id,
                'meta' => array_merge($existing->meta ?? [], $meta),
            ]);

            return $existing->fresh();
        }

        return AnprEvent::create([
            'uuid' => (string) Str::uuid(),
            'station_id' => $edge->station_id,
            'edge_detection_uuid' => $detectionUuid,
            'plate_normalized' => $plateNormalized,
            'plate_two' => $payload['plate_two'] ?? null,
            'plate_letter' => $payload['plate_letter'] ?? null,
            'plate_three' => $payload['plate_three'] ?? null,
            'plate_province' => $payload['plate_province'] ?? null,
            'confidence' => $confidence,
            'vehicle_id' => $vehicle?->id,
            'user_id' => $vehicle?->user_id,
            'charging_session_id' => $sessionId,
            'image_path' => $payload['image_path'] ?? null,
            'detected_at' => $payload['detected_at'] ?? now(),
            'meta' => $meta,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function buildMeta(array $payload, EdgeStation $edge, string $outcome): array
    {
        $connectorLabel = null;
        if (! empty($payload['cloud_connector_uuid'])) {
            $connectorLabel = 'CP · کانکتور';
            if (! empty($payload['connector_number'])) {
                $connectorLabel = ($payload['charge_point_id'] ?? 'CP').' · کانکتور '.$payload['connector_number'];
            }
        }

        return [
            'outcome' => $outcome,
            'camera_source' => $payload['camera_source'] ?? 'CAM-01',
            'edge_code' => $edge->edge_code,
            'auto_start_status' => $payload['auto_start_status'] ?? 'none',
            'auto_start_message' => $payload['auto_start_message'] ?? null,
            'cloud_connector_uuid' => $payload['cloud_connector_uuid'] ?? null,
            'connector_number' => $payload['connector_number'] ?? null,
            'charge_point_id' => $payload['charge_point_id'] ?? null,
            'connector_label' => $connectorLabel,
            'charging_session_uuid' => $payload['charging_session_uuid'] ?? null,
            'raw' => $payload['raw_result'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function findVehicle(array $payload, ?string $plateNormalized): ?Vehicle
    {
        $parts = array_filter([
            'plate_two' => $payload['plate_two'] ?? null,
            'plate_letter' => $payload['plate_letter'] ?? null,
            'plate_three' => $payload['plate_three'] ?? null,
            'plate_province' => $payload['plate_province'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (count($parts) === 4) {
            $vehicle = Vehicle::query()->where($parts)->first();
            if ($vehicle) {
                return $vehicle;
            }
        }

        if ($plateNormalized) {
            return Vehicle::where('plate_normalized', $plateNormalized)->first();
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    private function normalizePlate(array $payload): ?string
    {
        if (! empty($payload['plate_normalized'])) {
            return (string) $payload['plate_normalized'];
        }

        $parts = [
            $payload['plate_two'] ?? '',
            $payload['plate_letter'] ?? '',
            $payload['plate_three'] ?? '',
            $payload['plate_province'] ?? '',
        ];

        $joined = implode('', array_filter($parts, fn ($p) => $p !== ''));
        if ($joined === '') {
            return null;
        }

        return $joined;
    }

    /** @param  array<string, mixed>  $payload */
    private function resolveOutcome(array $payload, ?Vehicle $vehicle, ?float $confidence): string
    {
        if ($vehicle) {
            if ($confidence !== null && $confidence < 0.92) {
                return 'low_confidence';
            }

            return 'auto_start';
        }

        if (! empty($payload['outcome'])) {
            return (string) $payload['outcome'];
        }

        return 'unknown_plate';
    }
}
