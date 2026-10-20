<?php

namespace App\Services\Panel;

use App\Models\AnprDetection;
use App\Models\EdgeCharger;
use App\Models\EdgeConnector;
use App\Models\GsmMessageLog;
use App\Models\GsmModemConfig;
use App\Models\StationIdentity;
use App\Services\Anpr\AnprIngestService;
use App\Services\Anpr\PlprLiteClient;
use App\Services\Auth\OtpService;
use App\Services\Ocpp\OcppGatewayClient;
use App\Services\Station\StationConfigResolver;
use App\Services\Sync\CloudConnectivity;
use App\Services\Sync\CloudPullService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PanelSetupService
{
    public function __construct(
        private StationConfigResolver $config,
        private CloudConnectivity $cloud,
        private CloudPullService $pull,
        private PlprLiteClient $plpr,
        private AnprIngestService $anprIngest,
        private OcppGatewayClient $ocpp,
        private OtpService $otp,
    ) {}

    /** @return array<string, mixed> */
    public function fullState(): array
    {
        $station = StationIdentity::first();
        $gsm = GsmModemConfig::current();
        $anpr = $this->config->anpr();
        $ocpp = $this->config->ocpp();

        return [
            'station' => [
                'name' => $station?->name,
                'edge_code' => $station?->edge_code,
                'local_hostname' => $station?->local_hostname,
                'wifi_ssid' => $station?->wifi_ssid,
                'timezone' => $station?->timezone,
                'offline_mode_enabled' => (bool) ($station?->offline_mode_enabled ?? true),
                'software_version' => $station?->software_version,
            ],
            'cloud' => [
                'api_url' => $station?->cloud_api_url,
                'sync_token_set' => (bool) $station?->cloud_sync_token,
                'sync_token_hint' => $this->maskSecret($station?->cloud_sync_token),
                'reachable' => $this->cloud->isReachable(),
                'cloud_station_uuid' => $station?->cloud_station_uuid,
            ],
            'ocpp' => [
                'api_url' => $ocpp['api_url'] ?? '',
                'email' => $ocpp['email'] ?? '',
                'password_set' => ! empty($ocpp['password']),
                'configured' => $this->ocpp->configured(),
            ],
            'anpr' => [
                'plpr_url' => $anpr['plpr_url'] ?? '',
                'min_confidence' => (float) ($anpr['min_confidence'] ?? 0.92),
                'default_camera' => $anpr['default_camera'] ?? 'CAM-01',
                'auto_push_cloud' => (bool) ($anpr['auto_push_cloud'] ?? true),
                'healthy' => $this->plpr->isHealthy(),
                'stream_url' => $this->plpr->streamUrl(),
                'cameras' => $this->enrichCameras($this->config->anprCameras()),
                'connector_options' => $this->connectorOptions(),
                'recent_detections' => $this->recentAnpr(6),
            ],
            'gsm' => [
                'driver' => config('services.gsm.driver', 'log'),
                'modem' => [
                    'port' => $gsm->port,
                    'baud_rate' => $gsm->baud_rate,
                    'pin_code_set' => (bool) $gsm->pin_code,
                    'sms_center' => $gsm->sms_center,
                    'sender_id' => $gsm->sender_id,
                    'is_enabled' => $gsm->is_enabled,
                    'status' => $gsm->status,
                    'last_check_at' => $gsm->last_check_at?->toIso8601String(),
                ],
                'recent_messages' => $this->recentGsmLogs(8),
            ],
            'devices' => $this->listDevices(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function updateStation(array $data): StationIdentity
    {
        $station = StationIdentity::firstOrFail();

        $station->update([
            'name' => $data['name'] ?? $station->name,
            'local_hostname' => $data['local_hostname'] ?? $station->local_hostname,
            'wifi_ssid' => $data['wifi_ssid'] ?? $station->wifi_ssid,
            'timezone' => $data['timezone'] ?? $station->timezone,
            'offline_mode_enabled' => array_key_exists('offline_mode_enabled', $data)
                ? (bool) $data['offline_mode_enabled']
                : $station->offline_mode_enabled,
        ]);

        return $station->fresh();
    }

    /** @param array<string, mixed> $data */
    public function updateCloud(array $data): array
    {
        $station = StationIdentity::firstOrFail();

        $updates = [];
        if (! empty($data['api_url'])) {
            $updates['cloud_api_url'] = rtrim((string) $data['api_url'], '/');
        }
        if (! empty($data['sync_token'])) {
            $updates['cloud_sync_token'] = (string) $data['sync_token'];
        }

        if ($updates !== []) {
            $station->update($updates);
        }

        return [
            'reachable' => $this->cloud->isReachable(),
            'message' => $this->cloud->isReachable() ? 'اتصال به مرکز برقرار است.' : 'مرکز در دسترس نیست.',
        ];
    }

    /** @param array<string, mixed> $data */
    public function updateOcpp(array $data): void
    {
        $station = StationIdentity::firstOrFail();
        $meta = $station->meta ?? [];
        $ocpp = is_array($meta['ocpp'] ?? null) ? $meta['ocpp'] : [];

        if (! empty($data['api_url'])) {
            $ocpp['api_url'] = rtrim((string) $data['api_url'], '/');
        }
        if (! empty($data['email'])) {
            $ocpp['email'] = (string) $data['email'];
        }
        if (! empty($data['password'])) {
            $ocpp['password'] = (string) $data['password'];
        }

        $meta['ocpp'] = $ocpp;
        $station->update(['meta' => $meta]);
    }

    /** @param array<string, mixed> $data */
    public function updateAnpr(array $data): void
    {
        $station = StationIdentity::firstOrFail();
        $meta = $station->meta ?? [];
        $anpr = is_array($meta['anpr'] ?? null) ? $meta['anpr'] : [];

        if (isset($data['plpr_url'])) {
            $anpr['plpr_url'] = rtrim((string) $data['plpr_url'], '/');
        }
        if (isset($data['min_confidence'])) {
            $anpr['min_confidence'] = (float) $data['min_confidence'];
        }
        if (isset($data['default_camera'])) {
            $anpr['default_camera'] = (string) $data['default_camera'];
        }
        if (isset($data['auto_push_cloud'])) {
            $anpr['auto_push_cloud'] = (bool) $data['auto_push_cloud'];
        }

        if (isset($data['cameras']) && is_array($data['cameras'])) {
            $meta['anpr_cameras'] = $this->normalizeCameras($data['cameras'], $station->name);
        }

        $meta['anpr'] = $anpr;
        $station->update(['meta' => $meta]);
    }

    /** @param array<string, mixed> $data */
    public function updateGsm(array $data): GsmModemConfig
    {
        $modem = GsmModemConfig::current();

        $modem->update([
            'port' => $data['port'] ?? $modem->port,
            'baud_rate' => (int) ($data['baud_rate'] ?? $modem->baud_rate),
            'pin_code' => ! empty($data['pin_code']) ? (string) $data['pin_code'] : $modem->pin_code,
            'sms_center' => $data['sms_center'] ?? $modem->sms_center,
            'sender_id' => $data['sender_id'] ?? $modem->sender_id,
            'is_enabled' => array_key_exists('is_enabled', $data) ? (bool) $data['is_enabled'] : $modem->is_enabled,
        ]);

        return $modem->fresh();
    }

    /** @param array<string, mixed> $data */
    public function updateCharger(EdgeCharger $charger, array $data): EdgeCharger
    {
        $charger->update([
            'charge_point_id' => $data['charge_point_id'] ?? $charger->charge_point_id,
            'max_power_kw' => isset($data['max_power_kw']) ? (int) $data['max_power_kw'] : $charger->max_power_kw,
            'status' => $data['status'] ?? $charger->status,
            'meta' => array_merge($charger->meta ?? [], is_array($data['meta'] ?? null) ? $data['meta'] : []),
        ]);

        return $charger->fresh(['connectors']);
    }

    /** @param array<string, mixed> $data */
    public function updateConnector(EdgeConnector $connector, array $data): EdgeConnector
    {
        $connector->update([
            'max_power_kw' => isset($data['max_power_kw']) ? (int) $data['max_power_kw'] : $connector->max_power_kw,
            'operational_status' => $data['operational_status'] ?? $connector->operational_status,
            'ocpp_status' => $data['ocpp_status'] ?? $connector->ocpp_status,
        ]);

        return $connector->fresh();
    }

    /** @return array<string, mixed> */
    public function testOcpp(): array
    {
        if (! $this->ocpp->configured()) {
            return ['ok' => false, 'error' => 'OCPP Gateway پیکربندی نشده است.'];
        }

        $ocpp = $this->config->ocpp();
        $base = rtrim((string) ($ocpp['api_url'] ?? ''), '/');

        try {
            $response = Http::timeout(5)->get($base.'/up');

            return [
                'ok' => $response->successful(),
                'message' => $response->successful() ? 'OCPP Gateway در دسترس است.' : 'HTTP '.$response->status(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array<string, mixed> */
    public function testAnprHealth(): array
    {
        $ok = $this->plpr->isHealthy();

        return [
            'ok' => $ok,
            'message' => $ok ? 'سرویس پلاک‌خوان (PLPR) فعال است.' : 'سرویس PLPR در دسترس نیست — آدرس و Docker را بررسی کنید.',
            'url' => $this->config->plprUrl(),
        ];
    }

    public function startAnprCamera(string $source): array
    {
        $ok = $this->plpr->startCamera($source);

        return [
            'ok' => $ok,
            'message' => $ok ? 'دوربین به PLPR متصل شد.' : 'اتصال دوربین ناموفق بود.',
        ];
    }

    /** @return array<string, mixed> */
    public function testAnprRecognize(UploadedFile $file): array
    {
        $result = $this->plpr->recognizeImage($file);
        if (! ($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => $result['error'] ?? 'تشخیص پلاک ناموفق بود.',
                'plpr_url' => $this->config->plprUrl(),
            ];
        }

        try {
            $detection = $this->anprIngest->recordFromPlpr($result['data'] ?? []);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'plpr_url' => $this->config->plprUrl(),
                'hint' => 'تصویر باید پلاک خودروی ایرانی واضح داشته باشد.',
            ];
        }

        return [
            'ok' => true,
            'detection_uuid' => $detection->uuid,
            'plate' => $detection->plate_normalized,
            'confidence' => $detection->confidence,
            'connector_id' => $detection->connector_id,
            'auto_start_status' => $detection->auto_start_status,
            'auto_start_message' => $detection->auto_start_message,
            'charging_session_uuid' => $detection->charging_session_uuid,
            'raw' => $result['data'],
        ];
    }

    public function testGsmOtp(string $phone): array
    {
        if (! GsmModemConfig::current()->is_enabled) {
            return ['ok' => false, 'error' => 'مودم GSM غیرفعال است.'];
        }

        $result = $this->otp->request($phone, 'setup_test');

        return ['ok' => true, 'result' => $result];
    }

    /** @return array<string, mixed> */
    public function syncDevices(): array
    {
        $result = $this->pull->pull();

        return [
            'ok' => true,
            'mode' => $result['mode'],
            'stats' => $result['stats'],
            'devices' => $this->listDevices(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listDevices(): array
    {
        return EdgeCharger::with('connectors')
            ->orderBy('id')
            ->get()
            ->map(fn (EdgeCharger $ch) => [
                'id' => $ch->id,
                'uuid' => $ch->uuid,
                'cloud_charger_uuid' => $ch->cloud_charger_uuid,
                'charge_point_id' => $ch->charge_point_id,
                'max_power_kw' => (int) $ch->max_power_kw,
                'is_online' => $ch->is_online,
                'status' => $ch->status,
                'connectors' => $ch->connectors->map(fn (EdgeConnector $cn) => [
                    'id' => $cn->id,
                    'uuid' => $cn->uuid,
                    'cloud_connector_uuid' => $cn->cloud_connector_uuid,
                    'connector_number' => $cn->connector_number,
                    'ocpp_status' => $cn->ocpp_status,
                    'max_power_kw' => (int) $cn->max_power_kw,
                    'operational_status' => $cn->operational_status,
                ])->values()->all(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentAnpr(int $limit): array
    {
        return AnprDetection::with('connector.charger')
            ->orderByDesc('detected_at')
            ->limit($limit)
            ->get()
            ->map(fn (AnprDetection $d) => [
                'uuid' => $d->uuid,
                'plate' => $d->plate_normalized,
                'camera' => $d->camera_source,
                'connector' => $d->connector
                    ? ($d->connector->charger?->charge_point_id ?? '—').' #'.$d->connector->connector_number
                    : '—',
                'auto_start_status' => $d->auto_start_status,
                'confidence' => $d->confidence,
                'detected_at' => $d->detected_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function connectorOptions(): array
    {
        return EdgeCharger::with('connectors')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (EdgeCharger $ch) => $ch->connectors->map(fn (EdgeConnector $cn) => [
                'id' => $cn->id,
                'label' => ($ch->charge_point_id ?? 'CP').' · کانکتور '.$cn->connector_number,
            ]))
            ->values()
            ->all();
    }

    /** @param list<array<string, mixed>> $cameras */
    /** @return list<array<string, mixed>> */
    private function enrichCameras(array $cameras): array
    {
        return collect($cameras)->map(function (array $cam) {
            $connectorId = $cam['connector_id'] ?? null;
            if ($connectorId) {
                $cn = EdgeConnector::with('charger')->find($connectorId);
                $cam['connector_label'] = $cn
                    ? ($cn->charger?->charge_point_id ?? 'CP').' · کانکتور '.$cn->connector_number
                    : null;
            }

            return $cam;
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentGsmLogs(int $limit): array
    {
        return GsmMessageLog::orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (GsmMessageLog $log) => [
                'phone' => $log->phone,
                'status' => $log->status,
                'body' => Str::limit($log->message_body ?? '', 40),
                'at' => ($log->sent_at ?? $log->queued_at)?->toIso8601String(),
            ])
            ->all();
    }

    /** @param list<array<string, mixed>> $cameras */
    /** @return list<array<string, mixed>> */
    private function normalizeCameras(array $cameras, string $stationName): array
    {
        $out = [];
        foreach ($cameras as $i => $cam) {
            if (! is_array($cam)) {
                continue;
            }
            $id = trim((string) ($cam['id'] ?? ''));
            if ($id === '') {
                $id = 'CAM-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            }
            $connectorId = ! empty($cam['connector_id']) ? (int) $cam['connector_id'] : null;
            $out[] = [
                'id' => $id,
                'name' => trim((string) ($cam['name'] ?? 'دوربین '.($i + 1))) ?: 'دوربین '.($i + 1),
                'rtsp_url' => trim((string) ($cam['rtsp_url'] ?? '')),
                'role' => $cam['role'] ?? 'entry',
                'enabled' => (bool) ($cam['enabled'] ?? true),
                'plpr_profile' => $cam['plpr_profile'] ?? 'default',
                'connector_id' => $connectorId,
                'cloud_connector_uuid' => $cam['cloud_connector_uuid'] ?? null,
            ];
        }

        return $out !== [] ? $out : [[
            'id' => 'CAM-01',
            'name' => 'دوربین ورودی — '.$stationName,
            'rtsp_url' => '',
            'role' => 'entry',
            'enabled' => true,
            'plpr_profile' => 'default',
        ]];
    }

    private function maskSecret(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return '••••'.substr($value, -4);
    }
}
