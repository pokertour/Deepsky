<?php

namespace Tests\Unit;

use App\Models\ObservingLocation;
use App\Services\Astronomy\SolarCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SolarCalculatorTest extends TestCase
{
    public function test_greenwich_equinox_events_are_chronological_and_close_to_reference_times(): void
    {
        $location = new ObservingLocation([
            'latitude' => 51.4779,
            'longitude' => 0,
            'timezone' => 'Europe/London',
        ]);
        $night = (new SolarCalculator)->forNight(
            CarbonImmutable::parse('2026-03-20', 'Europe/London'),
            $location,
        );

        $this->assertNotNull($night->sunset);
        $this->assertNotNull($night->sunrise);
        $this->assertLessThanOrEqual(20, abs($night->sunset->diffInMinutes(
            CarbonImmutable::parse('2026-03-20 18:14', 'Europe/London'),
            false,
        )));
        $this->assertTrue($night->astronomicalNightStart->isBefore($night->astronomicalNightEnd));
        $this->assertTrue($night->astronomicalNightEnd->isBefore($night->sunrise));
    }

    public function test_polar_day_has_no_astronomical_night(): void
    {
        $location = new ObservingLocation([
            'latitude' => 69.6492,
            'longitude' => 18.9553,
            'timezone' => 'Europe/Oslo',
        ]);

        $night = (new SolarCalculator)->forNight(CarbonImmutable::parse('2026-06-21'), $location);

        $this->assertFalse($night->hasAstronomicalNight());
        $this->assertNull($night->sunset);
    }
}
