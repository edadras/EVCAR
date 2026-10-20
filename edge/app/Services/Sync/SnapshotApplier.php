<?php

namespace App\Services\Sync;

use App\Models\CachedQrToken;
use App\Models\CachedRfidCard;
use App\Models\CachedTariff;
use App\Models\CachedUser;
use App\Models\CachedVehicle;
use App\Models\CachedWallet;
use App\Models\EdgeCharger;
use App\Models\EdgeConnector;
use App\Models\StationIdentity;
use App\Services\Ocpp\ChargePointCommander;
use Illuminate\Support\Str;

class SnapshotApplier
{
    public function __construct(private ChargePointCommander $commander) {}

    /** @param  array<string, mixed>  $data */
    public function apply(array $data): array
    {
        $stats = [
            'users' => 0,
            'vehicles' => 0,
            'rfid' => 0,
            'qr' => 0,
            'chargers' => 0,
            'tariffs' => 0,
            'removed_vehicles' => 0,
            'removed_rfid' => 0,
        ];

        $station = StationIdentity::first();
        if ($station && ! empty($data['station'])) {
            $st = $data['station'];
            $updates = ['last_cloud_sync_at' => now()];
            if (! empty($st['uuid'])) {
                $updates['cloud_station_uuid'] = $st['uuid'];
            }
            if (! empty($st['name'])) {
                $updates['name'] = $st['name'];
            }
            if (array_key_exists('wifi_ssid', $st)) {
                $updates['wifi_ssid'] = $st['wifi_ssid'];
            }
            if (! empty($st['local_hostname'])) {
                $updates['local_hostname'] = $st['local_hostname'];
            }
            $station->update($updates);
        }

        if ($station && ! empty($data['edge_config']) && is_array($data['edge_config'])) {
            $this->applyEdgeConfig($station, $data['edge_config']);
        }

        foreach ($data['removed']['vehicles'] ?? [] as $uuid) {
            CachedVehicle::where('cloud_vehicle_uuid', $uuid)->delete();
            $stats['removed_vehicles']++;
        }

        foreach ($data['removed']['tariffs'] ?? [] as $uuid) {
            CachedTariff::where('cloud_tariff_uuid', $uuid)->delete();
        }

        if (($data['mode'] ?? null) === 'snapshot' && isset($data['tariffs']) && is_array($data['tariffs'])) {
            CachedTariff::whereNotIn('cloud_tariff_uuid', array_column($data['tariffs'], 'uuid'))->delete();
        }

        foreach ($data['removed']['rfid_cards'] ?? [] as $uuid) {
            CachedRfidCard::where('cloud_rfid_uuid', $uuid)->delete();
            $stats['removed_rfid']++;
        }

        foreach ($data['users'] ?? [] as $u) {
            $cached = CachedUser::updateOrCreate(
                ['cloud_user_uuid' => $u['uuid']],
                [
                    'uuid' => CachedUser::where('cloud_user_uuid', $u['uuid'])->value('uuid') ?? (string) Str::uuid(),
                    'phone' => $u['phone'] ?? ('09'.random_int(100000000, 999999999)),
                    'name' => $u['name'],
                    'status' => $u['status'] ?? 'active',
                    'is_local_only' => false,
                    'synced_at' => now(),
                    'pending_sync' => false,
                ]
            );
            $stats['users']++;

            if (! empty($u['wallet'])) {
                CachedWallet::updateOrCreate(
                    ['cached_user_id' => $cached->id],
                    [
                        'uuid' => CachedWallet::where('cached_user_id', $cached->id)->value('uuid') ?? (string) Str::uuid(),
                        'cloud_wallet_uuid' => $u['wallet']['uuid'],
                        'balance' => $u['wallet']['balance'],
                        'credit_limit' => $u['wallet']['credit_limit'] ?? 0,
                        'bonus_balance' => $u['wallet']['bonus_balance'] ?? 0,
                        'currency' => $u['wallet']['currency'] ?? 'IRR',
                        'synced_at' => now(),
                    ]
                );
            }

            foreach ($u['vehicles'] ?? [] as $v) {
                if (! empty($v['deleted_at'])) {
                    CachedVehicle::where('cloud_vehicle_uuid', $v['uuid'])->delete();
                    $stats['removed_vehicles']++;

                    continue;
                }
                CachedVehicle::updateOrCreate(
                    ['cloud_vehicle_uuid' => $v['uuid']],
                    [
                        'uuid' => CachedVehicle::where('cloud_vehicle_uuid', $v['uuid'])->value('uuid') ?? (string) Str::uuid(),
                        'cached_user_uuid' => $cached->uuid,
                        'plate_normalized' => $v['plate_normalized'] ?? null,
                        'plate_two' => $v['plate_two'] ?? null,
                        'plate_letter' => $v['plate_letter'] ?? null,
                        'plate_three' => $v['plate_three'] ?? null,
                        'plate_province' => $v['plate_province'] ?? null,
                        'brand' => $v['brand'] ?? null,
                        'model' => $v['model'] ?? null,
                        'battery_kwh' => $v['battery_kwh'] ?? null,
                        'is_default' => (bool) ($v['is_default'] ?? false),
                        'synced_at' => now(),
                    ]
                );
                $stats['vehicles']++;
            }

            foreach ($u['rfid_cards'] ?? [] as $c) {
                if (! empty($c['deleted_at'])) {
                    CachedRfidCard::where('cloud_rfid_uuid', $c['uuid'])->delete();
                    $stats['removed_rfid']++;

                    continue;
                }
                CachedRfidCard::updateOrCreate(
                    ['cloud_rfid_uuid' => $c['uuid']],
                    [
                        'uuid' => CachedRfidCard::where('cloud_rfid_uuid', $c['uuid'])->value('uuid') ?? (string) Str::uuid(),
                        'cached_user_uuid' => $cached->uuid,
                        'id_tag' => $c['id_tag'],
                        'status' => $c['status'] ?? 'active',
                        'synced_at' => now(),
                    ]
                );
                $stats['rfid']++;
            }
        }

        foreach ($data['tariffs'] ?? [] as $t) {
            CachedTariff::updateOrCreate(
                ['cloud_tariff_uuid' => $t['uuid']],
                [
                    'uuid' => CachedTariff::where('cloud_tariff_uuid', $t['uuid'])->value('uuid') ?? (string) Str::uuid(),
                    'name' => $t['name'],
                    'pricing_model' => $t['pricing_model'] ?? 'kwh',
                    'price_per_kwh' => $t['price_per_kwh'] ?? 32000,
                    'price_per_minute' => $t['price_per_minute'] ?? null,
                    'session_fee' => $t['session_fee'] ?? 0,
                    'rules' => $t['rules'] ?? [],
                    'is_default' => (bool) ($t['is_default'] ?? false),
                    'station_assigned' => (bool) ($t['station_assigned'] ?? false),
                    'synced_at' => now(),
                ]
            );
            $stats['tariffs']++;
        }

        // با OCPP داخلی، وضعیت زندهٔ شارژر/کانکتور را خود Edge از شارژر می‌گیرد و
        // نباید با دادهٔ (قدیمی‌تر) ابر بازنویسی شود.
        $liveStatus = $this->commander->isMetered();

        foreach ($data['chargers'] ?? [] as $ch) {
            $charger = EdgeCharger::firstOrNew(['charge_point_id' => $ch['charge_point_id']]);
            if (! $charger->exists) {
                $charger->uuid = (string) Str::uuid();
                $charger->is_online = $liveStatus ? false : (bool) ($ch['is_online'] ?? false);
                $charger->status = $charger->is_online ? 'active' : 'inactive';
            } elseif (! $liveStatus) {
                $charger->is_online = (bool) ($ch['is_online'] ?? false);
                $charger->status = $charger->is_online ? 'active' : 'inactive';
            }
            $charger->cloud_charger_uuid = $ch['uuid'];
            $charger->max_power_kw = $ch['max_power_kw'] ?? 0;
            $charger->save();
            $stats['chargers']++;

            foreach ($ch['connectors'] ?? [] as $cn) {
                $connector = EdgeConnector::firstOrNew(['charger_id' => $charger->id, 'connector_number' => $cn['connector_number']]);
                if (! $connector->exists) {
                    $connector->uuid = (string) Str::uuid();
                    $connector->ocpp_status = $liveStatus ? 'Unavailable' : ($cn['ocpp_status'] ?? 'Unavailable');
                } elseif (! $liveStatus) {
                    $connector->ocpp_status = $cn['ocpp_status'] ?? 'Unavailable';
                }
                $connector->cloud_connector_uuid = $cn['uuid'];
                $connector->max_power_kw = $cn['max_power_kw'] ?? $charger->max_power_kw;
                $connector->operational_status = 'active';
                $connector->save();
            }
        }

        foreach ($data['qr_tokens'] ?? [] as $qr) {
            CachedQrToken::updateOrCreate(
                ['cloud_qr_uuid' => $qr['uuid']],
                [
                    'uuid' => CachedQrToken::where('cloud_qr_uuid', $qr['uuid'])->value('uuid') ?? (string) Str::uuid(),
                    'token' => $qr['token'],
                    'cloud_charger_uuid' => $qr['cloud_charger_uuid'] ?? null,
                    'cloud_connector_uuid' => $qr['cloud_connector_uuid'] ?? null,
                    'connector_number' => $qr['connector_number'] ?? null,
                    'status' => $qr['status'] ?? 'active',
                    'offline_capable' => (bool) ($qr['offline_capable'] ?? true),
                    'expires_at' => $qr['expires_at'] ?? null,
                    'synced_at' => now(),
                ]
            );
            $stats['qr']++;
        }

        return $stats;
    }

    /** @param array<string, mixed> $config */
    private function applyEdgeConfig(StationIdentity $station, array $config): void
    {
        $meta = $station->meta ?? [];

        if (! empty($config['anpr']) && is_array($config['anpr'])) {
            $meta['anpr'] = array_merge($meta['anpr'] ?? [], $config['anpr']);
        }

        if (! empty($config['anpr_cameras']) && is_array($config['anpr_cameras'])) {
            $meta['anpr_cameras'] = $config['anpr_cameras'];
        }

        $station->update(['meta' => $meta]);
    }
}
