<?php

namespace App\Services\App;

use App\Models\AnprEvent;
use App\Models\Connector;
use App\Models\User;
use App\Support\Persian;
use Illuminate\Support\Facades\Http;

class ArrivalService
{
    private const TTL_MINUTES = 20;

    /** @return array<string, mixed> */
    public function statusForUser(User $user): array
    {
        $event = $this->pendingEvent($user);

        if (! $event) {
            return ['pending' => false];
        }

        $event->loadMissing('station');
        $connector = $this->resolveConnector($event);
        $expiresAt = $event->detected_at?->copy()->addMinutes(self::TTL_MINUTES);
        $meta = $event->meta ?? [];
        $autoStatus = (string) ($meta['auto_start_status'] ?? 'pending');

        return [
            'pending' => true,
            'source' => 'cloud',
            'detection_uuid' => $event->edge_detection_uuid,
            'auto_start_status' => $autoStatus,
            'auto_start_message' => $meta['auto_start_message'] ?? 'کابل را به کانکتور وصل کنید و تأیید کنید.',
            'plate' => $this->formatPlate($event),
            'confidence' => $event->confidence,
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_in_sec' => $expiresAt ? (int) max(0, now()->diffInSeconds($expiresAt, false)) : 0,
            'station' => [
                'name' => $event->station?->name,
                'code' => $event->station?->code,
            ],
            'connector' => $connector ? [
                'uuid' => $connector->uuid,
                'number' => $connector->connector_number,
                'charge_point_id' => $connector->charger?->charge_point_id,
                'label' => ($connector->charger?->charge_point_id ?? 'CP').' · کانکتور '.$connector->connector_number,
                'ocpp_status' => $connector->ocpp_status,
            ] : ($meta['connector_label'] ?? null ? [
                'label' => $meta['connector_label'],
                'uuid' => $meta['cloud_connector_uuid'] ?? null,
            ] : null),
            'camera' => $meta['camera_source'] ?? 'CAM-01',
            'charging_session_uuid' => $meta['charging_session_uuid'] ?? null,
            'can_confirm' => $autoStatus === 'pending' && ($connector !== null || ! empty($meta['cloud_connector_uuid'])),
            'can_cancel' => $autoStatus === 'pending',
        ];
    }

    private function pendingEvent(User $user): ?AnprEvent
    {
        return AnprEvent::with(['station'])
            ->where('user_id', $user->id)
            ->where('detected_at', '>=', now()->subMinutes(self::TTL_MINUTES))
            ->latest('detected_at')
            ->get()
            ->first(function (AnprEvent $event) {
                $status = (string) (($event->meta ?? [])['auto_start_status'] ?? '');

                return in_array($status, ['pending', 'started'], true);
            });
    }

    private function resolveConnector(AnprEvent $event): ?Connector
    {
        $uuid = ($event->meta ?? [])['cloud_connector_uuid'] ?? null;
        if (! $uuid) {
            return null;
        }

        return Connector::with('charger')
            ->where('uuid', $uuid)
            ->whereHas('charger', fn ($q) => $q->where('station_id', $event->station_id))
            ->first();
    }

    /** @return array<string, mixed> */
    public function confirmForUser(User $user): array
    {
        return $this->postToEdge($user, '/api/v1/app/arrival/confirm');
    }

    /** @return array<string, mixed> */
    public function cancelForUser(User $user): array
    {
        try {
            $result = $this->postToEdge($user, '/api/v1/app/arrival/cancel');
            $this->cancelLocalEvent($user);

            return $result;
        } catch (\RuntimeException $e) {
            if ($this->cancelLocalEvent($user)) {
                return [
                    'ok' => true,
                    'message' => 'ورود لغو شد. برای کانکتور دیگر QR را اسکن کنید.',
                ];
            }

            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function postToEdge(User $user, string $path): array
    {
        $connectivity = app(ConnectivityService::class);
        $headers = [
            'Accept' => 'application/json',
            'X-SC-User-UUID' => $user->uuid,
            'X-SC-Edge-Token' => $connectivity->edgeUserToken($user),
            'X-SC-Cloud-Sync' => config('safircharge.edge.sync_token'),
        ];

        $lastError = 'ایستگاه محلی در دسترس نیست.';

        foreach ($this->edgeBases() as $base) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders($headers)
                    ->post(rtrim($base, '/').$path, []);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                continue;
            }

            if (in_array($response->status(), [401, 403], true)) {
                throw new \RuntimeException(
                    $response->json('message') ?: 'احراز هویت با ایستگاه محلی ناموفق بود.'
                );
            }

            if ($response->successful() || in_array($response->status(), [202, 422], true)) {
                return $response->json() ?? [];
            }

            $lastError = $response->json('message') ?? $response->body();
        }

        throw new \RuntimeException($lastError);
    }

    private function cancelLocalEvent(User $user): bool
    {
        $event = $this->pendingEvent($user);
        if (! $event) {
            return false;
        }

        $event->update([
            'meta' => array_merge($event->meta ?? [], [
                'auto_start_status' => 'cancelled',
                'auto_start_message' => 'لغو توسط کاربر',
            ]),
        ]);

        return true;
    }

    /** @return list<string> */
    private function edgeBases(): array
    {
        $bases = array_values(array_filter([
            config('safircharge.connectivity.edge_internal_url'),
            ...config('safircharge.connectivity.edge_probe_urls', []),
        ]));

        return array_values(array_unique($bases));
    }

    private function formatPlate(AnprEvent $event): string
    {
        if ($event->plate_two && $event->plate_letter && $event->plate_three) {
            return Persian::digits($event->plate_two).$event->plate_letter.Persian::digits($event->plate_three)
                .($event->plate_province ? ' · '.Persian::digits($event->plate_province) : '');
        }

        return $event->plate_normalized ?? '—';
    }
}
