<?php

namespace Tests\Unit;

use App\Services\Astronomy\PointingDirection;
use PHPUnit\Framework\TestCase;

class PointingDirectionTest extends TestCase
{
    public function test_azimuth_is_converted_to_a_sixteen_point_compass_direction(): void
    {
        $direction = new PointingDirection;

        $this->assertSame('N', $direction->fromAzimuth(359));
        $this->assertSame('NNE', $direction->fromAzimuth(22.5));
        $this->assertSame('E', $direction->fromAzimuth(90));
        $this->assertSame('SSO', $direction->fromAzimuth(202.5));
        $this->assertSame('O', $direction->fromAzimuth(270));
    }
}
