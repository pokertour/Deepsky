<?php

namespace Tests\Unit;

use App\Data\Astronomy\MoonData;
use App\Data\Astronomy\SolarNight;
use App\Services\Planning\NightScoreCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class NightScoreCalculatorTest extends TestCase
{
    public function test_a_long_moonless_night_scores_highly(): void
    {
        $start = CarbonImmutable::parse('2026-01-01 20:00', 'UTC');
        $night = new SolarNight(null, null, null, null, null, null, $start, $start->addHours(8));
        $moon = new MoonData(0, 0, -20, 0, 0, 0);

        $score = (new NightScoreCalculator)->calculate($night, $moon);

        $this->assertSame(100, $score->score);
        $this->assertSame(480, $score->darknessMinutes);
    }
}
