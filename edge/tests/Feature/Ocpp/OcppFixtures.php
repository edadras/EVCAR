<?php

namespace Tests\Feature\Ocpp;

use App\Models\CachedRfidCard;
use App\Models\CachedTariff;
use App\Models\CachedUser;
use App\Models\CachedWallet;
use App\Models\EdgeCharger;
use App\Models\EdgeConnector;
use App\Models\StationIdentity;
use Illuminate\Support\Str;

trait OcppFixtures
{
    protected EdgeCharger $charger;

    protected EdgeConnector $connector;

    protected CachedUser $user;

    protected CachedWallet $wallet;

    protected function seedStation(float $balance = 1_000_000, bool $online = true): void
    {
        config(['safircharge.ocpp.driver' => 'builtin', 'safircharge.ocpp.progress_sync_seconds' => 0]);

        StationIdentity::create([
            'uuid' => (string) Str::uuid(),
            'edge_code' => 'EDGE-TEST',
            'name' => 'Test station',
            'cloud_api_url' => 'http://127.0.0.1:1',
            'cloud_sync_token' => 'test',
        ]);

        $this->charger = EdgeCharger::create([
            'uuid' => (string) Str::uuid(),
            'cloud_charger_uuid' => (string) Str::uuid(),
            'charge_point_id' => 'CP-T1',
            'max_power_kw' => 22,
            'is_online' => $online,
            'status' => $online ? 'active' : 'inactive',
        ]);

        $this->connector = EdgeConnector::create([
            'uuid' => (string) Str::uuid(),
            'cloud_connector_uuid' => (string) Str::uuid(),
            'charger_id' => $this->charger->id,
            'connector_number' => 1,
            'ocpp_status' => 'Available',
            'operational_status' => 'active',
            'max_power_kw' => 22,
        ]);

        $this->user = CachedUser::create([
            'uuid' => (string) Str::uuid(),
            'cloud_user_uuid' => (string) Str::uuid(),
            'phone' => '09120000000',
            'name' => 'Driver',
            'status' => 'active',
        ]);

        $this->wallet = CachedWallet::create([
            'uuid' => (string) Str::uuid(),
            'cached_user_id' => $this->user->id,
            'balance' => $balance,
            'currency' => 'IRR',
        ]);

        CachedRfidCard::create([
            'uuid' => (string) Str::uuid(),
            'cached_user_uuid' => $this->user->uuid,
            'id_tag' => 'CARD-001',
            'status' => 'active',
        ]);

        CachedTariff::create([
            'uuid' => (string) Str::uuid(),
            'cloud_tariff_uuid' => (string) Str::uuid(),
            'name' => 'Energy',
            'pricing_model' => 'per_kwh',
            'price_per_kwh' => 30000,
            'session_fee' => 0,
            'is_default' => true,
        ]);
    }
}
