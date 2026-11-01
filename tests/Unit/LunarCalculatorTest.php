<?php

namespace Tests\Unit;

use App\Models\ObservingLocation;
use App\Services\Astronomy\CoordinateConverter;
use App\Services\Astronomy\LunarCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LunarCalculatorTest extends TestCase
{
    public function test_known_new_and_full_moon_dates_have_expected_illumination(): void
    {
        $location = new ObservingLocation([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $calculator = new LunarCalculator(new CoordinateConverter);

        $newMoon = $calculator->at(CarbonImmutable::parse('2026-08-12 17:37', 'UTC'), $location);
        $fullMoon = $calculator->at(CarbonImmutable::parse('2026-08-28 04:18', 'UTC'), $location);

        $this->assertLessThan(12, $newMoon->illuminationPercent);
        $this->assertGreaterThan(88, $fullMoon->illuminationPercent);
        $this->assertGreaterThanOrEqual(0, $fullMoon->azimuthDeg);
        $this->assertLessThan(360, $fullMoon->azimuthDeg);
    }

    public function test_for_night_includes_moonrise_and_moonset(): void
    {
        $location = new ObservingLocation([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $calculator = new LunarCalculator(new CoordinateConverter);
        $moon = $calculator->forNight(CarbonImmutable::parse('2026-08-03', 'Europe/Paris'), $location);

        $this->assertNotNull($moon->rise);
        $this->assertNotNull($moon->set);
        $this->assertSame('Europe/Paris', $moon->rise->timezoneName);
        $this->assertNotSame($moon->rise->format('H:i'), $moon->set->format('H:i'));
    }
}
