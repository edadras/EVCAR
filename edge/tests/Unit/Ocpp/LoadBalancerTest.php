<?php

namespace Tests\Unit\Ocpp;

use App\Services\Ocpp\LoadBalancer;
use PHPUnit\Framework\TestCase;

class LoadBalancerTest extends TestCase
{
    public function test_equal_share_when_all_connectors_can_take_more(): void
    {
        $this->assertSame([1 => 30.0, 2 => 30.0], LoadBalancer::share(60, [1 => 50.0, 2 => 50.0]));
    }

    public function test_spare_power_from_small_connector_goes_to_others(): void
    {
        // AC 11kW + دو DC 120kW با سقف 100kW → 11 + 44.5 + 44.5
        $this->assertSame([1 => 11.0, 2 => 44.5, 3 => 44.5], LoadBalancer::share(100, [1 => 11.0, 2 => 120.0, 3 => 120.0]));
    }

    public function test_never_below_minimum(): void
    {
        $share = LoadBalancer::share(10, [1 => 22.0, 2 => 22.0, 3 => 22.0, 4 => 22.0]);
        foreach ($share as $kw) {
            $this->assertSame(LoadBalancer::MIN_KW, $kw);
        }
    }
}
