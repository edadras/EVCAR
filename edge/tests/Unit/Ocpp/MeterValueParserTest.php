<?php

namespace Tests\Unit\Ocpp;

use App\Services\Ocpp\MeterValueParser;
use PHPUnit\Framework\TestCase;

class MeterValueParserTest extends TestCase
{
    public function test_defaults_to_energy_register_in_wh(): void
    {
        $result = MeterValueParser::parse(['sampledValue' => [['value' => '12345']]]);

        $this->assertSame(['energy_wh' => 12345], $result);
    }

    public function test_converts_kwh_and_kw_and_reads_soc(): void
    {
        $result = MeterValueParser::parse(['sampledValue' => [
            ['value' => '12.5', 'measurand' => 'Energy.Active.Import.Register', 'unit' => 'kWh'],
            ['value' => '7.4', 'measurand' => 'Power.Active.Import', 'unit' => 'kW'],
            ['value' => '63', 'measurand' => 'SoC', 'location' => 'EV', 'unit' => 'Percent'],
        ]]);

        $this->assertSame(12500, $result['energy_wh']);
        $this->assertEqualsWithDelta(7400, $result['power_w'], 0.001);
        $this->assertSame(63, $result['soc']);
    }

    public function test_sums_per_phase_values_when_no_total(): void
    {
        $result = MeterValueParser::parse(['sampledValue' => [
            ['value' => '1000', 'measurand' => 'Power.Active.Import', 'phase' => 'L1', 'unit' => 'W'],
            ['value' => '1100', 'measurand' => 'Power.Active.Import', 'phase' => 'L2', 'unit' => 'W'],
            ['value' => '900', 'measurand' => 'Power.Active.Import', 'phase' => 'L3', 'unit' => 'W'],
            ['value' => '230', 'measurand' => 'Voltage', 'phase' => 'L1-N'],
            ['value' => '400', 'measurand' => 'Voltage', 'phase' => 'L1-L2'],
            ['value' => '16', 'measurand' => 'Current.Import', 'phase' => 'L1'],
            ['value' => '15', 'measurand' => 'Current.Import', 'phase' => 'L2'],
        ]]);

        $this->assertEqualsWithDelta(3000, $result['power_w'], 0.001);
        $this->assertEqualsWithDelta(230, $result['voltage_v'], 0.001);
        $this->assertEqualsWithDelta(16, $result['current_a'], 0.001);
        $this->assertArrayNotHasKey('energy_wh', $result);
    }

    public function test_ignores_signed_and_non_numeric_samples(): void
    {
        $this->assertNull(MeterValueParser::parse(['sampledValue' => [
            ['value' => 'abc'],
            ['value' => 'MEUCIQ...', 'format' => 'SignedData'],
        ]]));
    }
}
