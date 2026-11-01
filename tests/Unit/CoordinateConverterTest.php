<?php

namespace Tests\Unit;

use App\Services\Astronomy\CoordinateConverter;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CoordinateConverterTest extends TestCase
{
    public function test_an_equatorial_target_on_the_meridian_is_at_the_zenith(): void
    {
        $converter = new CoordinateConverter;
        $at = CarbonImmutable::parse('2026-08-03 00:00:00', 'UTC');
        $rightAscension = $converter->localSiderealTimeDeg($at, 0);

        $horizontal = $converter->equatorialToHorizontal($rightAscension, 0, 0, 0, $at);

        $this->assertEqualsWithDelta(90, $horizontal->altitudeDeg, 0.0001);
        $this->assertGreaterThanOrEqual(0, $horizontal->azimuthDeg);
        $this->assertLessThan(360, $horizontal->azimuthDeg);
    }

    public function test_angular_separation_handles_right_ascension_wrap(): void
    {
        $separation = (new CoordinateConverter)->angularSeparationDeg(359, 0, 1, 0);

        $this->assertEqualsWithDelta(2, $separation, 0.0001);
    }
}
