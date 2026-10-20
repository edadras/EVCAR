<?php

namespace App\Services;

use App\Models\AnprDetection;
use App\Models\StationIdentity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CloudSyncClient
{
    public function identity(): ?StationIdentity
    {
        return StationIdentity::query()->first();
    }

    /**
     * @return array{accepted: int, duplicates: int, errors: array<int, array<string, string>>}|null
     */
    public function pushEvents(array $events): ?array
    {
        $identity = $this->identity();
        if (! $identity?->cloud_api_url || ! $identity?->cloud_sync_token) {
            return null;
        }

        $base = rtrim($identity->cloud_api_url, '/');
        $url = $base.'/api/v1/sync/push';

        try {
            $response = Http::timeout(15)
                ->withToken($identity->cloud_sync_token)
                ->acceptJson()
                ->post($url, ['events' => $events]);

            if (! $response->successful()) {
                return null;
            }

            $identity->update(['last_cloud_sync_at' => now()]);

            return $response->json();
        } catch (ConnectionException) {
            return null;
        }
    }

    public function pushAnprDetection(AnprDetection $detection): bool
    {
        $detection->loadMissing('connector.charger');
        $outcome = $this->resolveOutcome($detection);

        $result = $this->pushEvents([[
            'event_uuid' => (string) Str::uuid(),
            'event_type' => 'anpr.detected',
            'occurred_at' => $detection->detected_at?->toIso8601String() ?? now()->toIso8601String(),
            'payload' => [
                'detection_uuid' => $detection->uuid,
                'plate_two' => $detection->plate_two,
                'plate_letter' => $detection->plate_letter,
                'plate_three' => $detection->plate_three,
                'plate_province' => $detection->plate_province,
                'plate_normalized' => $detection->plate_normalized,
                'confidence' => $detection->confidence,
                'detected_at' => $detection->detected_at?->toIso8601String(),
                'camera_source' => $detection->camera_source ?? 'CAM-01',
                'connector_id' => $detection->connector_id,
                'cloud_connector_uuid' => $detection->connector?->cloud_connector_uuid,
                'connector_number' => $detection->connector?->connector_number,
                'charge_point_id' => $detection->connector?->charger?->charge_point_id,
                'auto_start_status' => $detection->auto_start_status,
                'auto_start_message' => $detection->auto_start_message,
                'image_path' => $detection->image_path,
                'outcome' => $outcome,
                'charging_session_uuid' => $detection->charging_session_uuid,
                'cached_user_uuid' => $detection->cached_user_uuid,
                'cached_vehicle_uuid' => $detection->cached_vehicle_uuid,
                'raw_result' => $detection->raw_result,
            ],
        ]]);

        if ($result === null) {
            return false;
        }

        $detection->update([
            'pending_sync' => false,
            'synced_at' => now(),
        ]);

        return ($result['accepted'] ?? 0) > 0 || ($result['duplicates'] ?? 0) > 0;
    }

    private function resolveOutcome(AnprDetection $detection): string
    {
        if ($detection->cached_user_uuid) {
            return ((float) $detection->confidence) >= 0.92 ? 'auto_start' : 'low_confidence';
        }

        return 'unknown_plate';
    }
}
