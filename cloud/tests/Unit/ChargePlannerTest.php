<?php

namespace Tests\Unit;

use App\Services\Navigator\ChargePlanner;
use PHPUnit\Framework\TestCase;

class ChargePlannerTest extends TestCase
{
    /** @return list<array<string, float>> 400 km at 0.15 kWh/km */
    private function segments(float $km = 400, float $kwhPerKm = 0.15): array
    {
        $segments = [];
        for ($from = 0; $from < $km; $from += 20) {
            $segments[] = ['from_km' => $from, 'to_km' => $from + 20, 'consumption_kwh' => 20 * $kwhPerKm];
        }

        return $segments;
    }

    /** @return array<string, mixed> */
    private function charger(string $id, float $km, int $kw = 120, float $offset = 0): array
    {
        return ['uuid' => $id, 'name' => $id, 'route_km' => $km, 'offset_km' => $offset, 'max_power_kw' => $kw, 'free_ports' => 1];
    }

    public function test_no_stop_when_destination_reachable(): void
    {
        $plan = (new ChargePlanner)->plan($this->segments(200), [$this->charger('A', 100)], 60, 90, 20);

        $this->assertFalse($plan['needs_charge']);
        $this->assertSame([], $plan['stops']);
        $this->assertEqualsWithDelta(40, $plan['arrival_soc'], 0.1);
    }

    public function test_picks_farthest_reachable_station_and_charges_only_what_is_needed(): void
    {
        $chargers = [$this->charger('A', 100), $this->charger('B', 250), $this->charger('C', 350)];
        $plan = (new ChargePlanner)->plan($this->segments(), $chargers, 60, 80, 20, 80, 100, 10000, 15000);

        $this->assertTrue($plan['reachable']);
        $this->assertCount(1, $plan['stops']);
        $stop = $plan['stops'][0];
        $this->assertSame('B', $stop['station_uuid']);
        $this->assertEquals(18, $stop['arrive_soc']);
        $this->assertSame(65, $stop['depart_soc']);
        $this->assertGreaterThanOrEqual(20, $plan['arrival_soc']);
        $this->assertGreaterThan(0, $stop['duration_min']);
        $this->assertEqualsWithDelta($stop['energy_kwh'] * 10000 + 15000, $stop['cost_rial'], 1000);
    }

    public function test_multiple_stops_for_long_trip(): void
    {
        $chargers = [];
        for ($km = 80; $km < 900; $km += 80) {
            $chargers[] = $this->charger('S'.$km, $km);
        }
        $plan = (new ChargePlanner)->plan($this->segments(900), $chargers, 60, 90, 15);

        $this->assertTrue($plan['reachable']);
        $this->assertGreaterThanOrEqual(2, count($plan['stops']));
        foreach ($plan['stops'] as $stop) {
            $this->assertGreaterThanOrEqual(ChargePlanner::ARRIVAL_BUFFER_SOC, $stop['arrive_soc']);
            $this->assertLessThanOrEqual(80, $stop['depart_soc']);
        }
    }

    public function test_unreachable_station_is_not_suggested(): void
    {
        $plan = (new ChargePlanner)->plan($this->segments(), [$this->charger('FAR', 380)], 60, 80, 20);

        $this->assertFalse($plan['reachable']);
        $this->assertSame([], $plan['stops']);
        $this->assertEqualsWithDelta(288, $plan['stranded_km'], 2);
    }

    public function test_detour_energy_is_counted(): void
    {
        $near = (new ChargePlanner)->plan($this->segments(), [$this->charger('X', 240, 120, 0)], 60, 80, 20);
        $off = (new ChargePlanner)->plan($this->segments(), [$this->charger('X', 240, 120, 20)], 60, 80, 20);

        $this->assertGreaterThan($off['stops'][0]['arrive_soc'], $near['stops'][0]['arrive_soc']);
    }

    public function test_charge_time_slows_above_80_percent(): void
    {
        $planner = new ChargePlanner;
        $fast = $planner->chargeMinutes(20, 80, 60, 100);
        $slow = $planner->chargeMinutes(80, 100, 60, 100);

        $this->assertGreaterThan($fast / 3, $slow);
    }
}
