<?php

namespace Tests\Unit;

use App\Data\Astronomy\SolarNight;
use App\Data\Weather\WeatherForecast;
use App\Data\Weather\WeatherPoint;
use App\Services\Weather\WeatherScoreCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class WeatherScoreCalculatorTest extends TestCase
{
    public function test_clear_dry_weather_scores_better_than_cloudy_humid_weather(): void
    {
        $start = CarbonImmutable::parse('2026-08-03 22:00', 'Europe/Paris');
        $night = new SolarNight(null, null, null, null, null, null, $start, $start->addHours(6));
        $calculator = new WeatherScoreCalculator;

        $clear = $calculator->calculate($this->forecast($start, 5, 55, 5, 0, 12, 5, 30000), $night);
        $cloudy = $calculator->calculate($this->forecast($start, 95, 98, 30, 90, 10, 10, 3000), $night);

        $this->assertNotNull($clear);
        $this->assertNotNull($cloudy);
        $this->assertGreaterThan($cloudy->score, $clear->score);
        $this->assertSame('Excellente', $clear->label);
    }

    public function test_seeing_and_transparency_improve_clear_nights_and_are_ignored_when_absent(): void
    {
        $start = CarbonImmutable::parse('2026-08-03 22:00', 'Europe/Paris');
        $night = new SolarNight(null, null, null, null, null, null, $start, $start->addHours(6));
        $calculator = new WeatherScoreCalculator;

        $withoutAstro = $calculator->calculate($this->forecast($start, 5, 55, 5, 0, 12, 5, 30000), $night);
        $withGoodAstro = $calculator->calculate($this->forecast($start, 5, 55, 5, 0, 12, 5, 30000, 1, 1), $night);
        $withPoorAstro = $calculator->calculate($this->forecast($start, 5, 55, 5, 0, 12, 5, 30000, 8, 8), $night);

        $this->assertNotNull($withoutAstro);
        $this->assertNotNull($withGoodAstro);
        $this->assertNotNull($withPoorAstro);
        $this->assertNull($withoutAstro->averageSeeingIndex);
        $this->assertSame(1.0, $withGoodAstro->averageSeeingIndex);
        $this->assertTrue($withGoodAstro->score > $withPoorAstro->score);
    }

    private function forecast(
        CarbonImmutable $start,
        float $clouds,
        float $humidity,
        float $wind,
        float $precipitation,
        float $temperature,
        float $dewPoint,
        float $visibility,
        ?float $seeing = null,
        ?float $transparency = null,
    ): WeatherForecast {
        $points = [];

        for ($hour = 0; $hour <= 6; $hour++) {
            $points[] = new WeatherPoint(
                $start->addHours($hour),
                $clouds,
                $clouds,
                $clouds,
                $clouds,
                $humidity,
                $temperature,
                $dewPoint,
                $wind,
                $wind,
                $precipitation,
                $visibility,
                $seeing,
                $transparency,
            );
        }

        return new WeatherForecast('test', $start, $points);
    }
}
