<?php

namespace Tests\Unit;

use App\Models\CelestialObject;
use App\Models\ObservingLocation;
use App\Services\Astronomy\CoordinateConverter;
use App\Services\Astronomy\SolarCalculator;
use App\Services\Astronomy\VisibilityCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class VisibilityCalculatorTest extends TestCase
{
    public function test_it_builds_a_night_timeline_and_applies_the_horizon_mask(): void
    {
        $target = new CelestialObject(['ra_deg' => 37.9546, 'dec_deg' => 89.2641]);
        $location = new ObservingLocation([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
            'horizon_mask' => ['0' => 60, '90' => 60, '180' => 60, '270' => 60],
        ]);
        $calculator = new VisibilityCalculator(new CoordinateConverter, new SolarCalculator);

        $visibility = $calculator->forNight(
            $target,
            $location,
            CarbonImmutable::parse('2026-08-03', 'Europe/Paris'),
        );

        $this->assertNotEmpty($visibility->timeline);
        $this->assertNull($visibility->rise);
        $this->assertNull($visibility->set);
        $this->assertNotNull($visibility->transit);
        $this->assertEqualsWithDelta(47.9, $visibility->maxAltitudeDeg, 1.0);
        $this->assertSame(0, $visibility->minutesAboveAltitude[40]);
    }
}
