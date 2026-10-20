<?php

namespace App\Services\Anpr;

use App\Models\AnprDetection;
use App\Services\CloudSyncClient;
use App\Services\Station\StationConfigResolver;
use Illuminate\Support\Str;

class AnprIngestService
{
    public function __construct(
        private CloudSyncClient $cloud,
        private StationConfigResolver $stationConfig,
        private PlprResponseNormalizer $normalizer,
        private AnprCameraMapper $cameras,
        private AnprAutoStartService $autoStart,
    ) {}

    /**
     * @param  array<string, mixed>  $raw  پاسخ PLPR Lite
     */
    public function recordFromPlpr(array $raw, ?string $cameraSource = null): AnprDetection
    {
        $normalized = $this->normalizer->normalize($raw);
        if (! $normalized) {
            throw new \RuntimeException('پلاکی در تصویر شناسایی نشد.');
        }

        $plate = $this->parsePlate($normalized);
        $confidence = (float) ($normalized['confidence'] ?? $raw['confidence'] ?? $raw['score'] ?? 0);
        $cameraId = $cameraSource ?? ($this->stationConfig->anpr()['default_camera'] ?? 'CAM-01');
        $connector = $this->cameras->connectorForCamera($cameraId);

        $detection = AnprDetection::create([
            'uuid' => (string) Str::uuid(),
            'plate_two' => $plate['two'],
            'plate_letter' => $plate['letter'],
            'plate_three' => $plate['three'],
            'plate_province' => $plate['province'],
            'plate_normalized' => $plate['normalized'],
            'confidence' => $confidence > 0 ? $confidence : null,
            'camera_source' => $cameraId,
            'connector_id' => $connector?->id,
            'detected_at' => now(),
            'pending_sync' => true,
            'raw_result' => $raw,
            'auto_start_status' => 'none',
        ]);

        $detection = $this->autoStart->processDetection($detection);

        if ($this->stationConfig->anpr()['auto_push_cloud'] ?? true) {
            $this->cloud->pushAnprDetection($detection->fresh());
        }

        return $detection->fresh();
    }

    /**
     * @return array{two:?string,letter:?string,three:?string,province:?string,normalized:?string}
     */
    private function parsePlate(array $raw): array
    {
        if (! empty($raw['parts']) && is_array($raw['parts'])) {
            $parts = $raw['parts'];

            return [
                'two' => $parts['two'] ?? null,
                'letter' => $parts['letter'] ?? null,
                'three' => $parts['three'] ?? null,
                'province' => $parts['province'] ?? null,
                'normalized' => implode('', array_filter($parts)),
            ];
        }

        $text = (string) ($raw['plate'] ?? $raw['text'] ?? $raw['plate_text'] ?? '');
        if (preg_match('/(\d{2})([^\d])(\d{3}).*?(\d{2})/u', $text, $m)) {
            return [
                'two' => $m[1],
                'letter' => $m[2],
                'three' => $m[3],
                'province' => $m[4],
                'normalized' => $m[1].$m[2].$m[3].$m[4],
            ];
        }

        return ['two' => null, 'letter' => null, 'three' => null, 'province' => null, 'normalized' => $text ?: null];
    }
}
