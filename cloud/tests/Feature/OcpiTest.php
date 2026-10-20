<?php

namespace Tests\Feature;

use App\Models\Charger;
use App\Models\Connector;
use App\Models\Station;
use App\Models\Tariff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['safircharge.ocpi.tokens' => 'partner-token']);
        $station = Station::create(['code' => 'ST-OC', 'name' => 'OCPI Station', 'status' => 'active', 'latitude' => 35.7, 'longitude' => 51.4, 'city' => 'Tehran']);
        $charger = Charger::create(['station_id' => $station->id, 'charge_point_id' => 'CP-OC', 'max_power_kw' => 120, 'connector_type' => 'CCS2', 'status' => 'active', 'is_online' => true]);
        Connector::create(['charger_id' => $charger->id, 'connector_number' => 1, 'ocpp_status' => 'Available', 'operational_status' => 'active']);
        Tariff::create(['name' => 'T', 'code' => 'T1', 'pricing_model' => 'per_kwh', 'price_per_kwh' => 8500, 'session_fee' => 15000, 'status' => 'active']);
    }

    public function test_requires_token(): void
    {
        $this->getJson('/api/ocpi/versions')->assertStatus(401)->assertJson(['status_code' => 2001]);
    }

    public function test_locations_tariffs_and_versions(): void
    {
        $h = ['Authorization' => 'Token '.base64_encode('partner-token')];

        $this->getJson('/api/ocpi/versions', $h)->assertOk()->assertJsonPath('data.0.version', '2.2.1');

        $loc = $this->getJson('/api/ocpi/2.2.1/locations', $h)->assertOk();
        $loc->assertHeader('X-Total-Count', '1');
        $loc->assertJsonPath('data.0.id', 'ST-OC');
        $loc->assertJsonPath('data.0.evses.0.status', 'AVAILABLE');
        $loc->assertJsonPath('data.0.evses.0.connectors.0.standard', 'IEC_62196_T2_COMBO');
        $loc->assertJsonPath('data.0.evses.0.connectors.0.power_type', 'DC');

        $this->getJson('/api/ocpi/2.2.1/locations/ST-OC', $h)->assertOk()->assertJsonPath('data.name', 'OCPI Station');
        $this->getJson('/api/ocpi/2.2.1/locations/NOPE', $h)->assertStatus(404);

        $this->getJson('/api/ocpi/2.2.1/tariffs', $h)->assertOk()
            ->assertJsonPath('data.0.elements.0.price_components.0.type', 'ENERGY')
            ->assertJsonPath('data.0.elements.0.price_components.1.type', 'FLAT');

        $this->getJson('/api/ocpi/2.2.1/sessions?date_from=2026-01-01', $h)->assertOk();
    }
}
