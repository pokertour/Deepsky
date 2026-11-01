<?php

namespace Tests\Unit;

use App\Data\Astronomy\MoonData;
use App\Data\Astronomy\TargetVisibility;
use App\Data\Astronomy\VisibilityPoint;
use App\Data\Equipment\EquipmentOptics;
use App\Data\Planning\TargetHistory;
use App\Data\Weather\WeatherScore;
use App\Models\CelestialObject;
use App\Services\Planning\TargetScoreCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TargetScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_weather_changes_the_target_score_with_configured_weights(): void
    {
        $at = CarbonImmutable::parse('2026-08-03 23:00', 'Europe/Paris');
        $target = new CelestialObject([
            'catalog' => 'NGC',
            'catalog_number' => '7000',
            'ra_deg' => 314.7,
            'dec_deg' => 44.5,
        ]);
        $target->setAttribute('astrophotos_count', 1);
        $visibility = new TargetVisibility(
            [new VisibilityPoint($at, 70, 180, 0, true)],
            $at,
            $at->addHours(4),
            $at,
            70,
            [20 => 240, 30 => 240, 40 => 240],
        );
        $moon = new MoonData(100, -20, -10, 0, 10, 0.1);
        $calculator = $this->app->make(TargetScoreCalculator::class);

        $good = $calculator->calculate($target, $visibility, $moon, null, $this->weather(95));
        $poor = $calculator->calculate($target, $visibility, $moon, null, $this->weather(10));

        $this->assertGreaterThan($poor->score, $good->score);
        $this->assertGreaterThanOrEqual(0, $poor->score);
        $this->assertLessThanOrEqual(100, $good->score);
    }

    public function test_equipment_and_history_components_influence_score(): void
    {
        $at = CarbonImmutable::parse('2026-08-03 23:00', 'Europe/Paris');
        $visibility = new TargetVisibility(
            [new VisibilityPoint($at, 70, 180, 0, true)],
            $at,
            $at->addHours(4),
            $at,
            70,
            [20 => 240, 30 => 240, 40 => 240],
        );
        $moon = new MoonData(100, -20, -10, 0, 10, 0.1);
        $calculator = $this->app->make(TargetScoreCalculator::class);
        $optics = new EquipmentOptics(
            nativeFocalLengthMm: 400,
            effectiveFocalLengthMm: 400,
            apertureMm: 80,
            focalRatio: 5,
            sensorWidthMm: 23.5,
            sensorHeightMm: 15.6,
            pixelSizeUm: 3.9,
            pixelScaleArcsec: 2.0,
            horizontalFieldOfViewDeg: 3.4,
            verticalFieldOfViewDeg: 2.2,
            diagonalFieldOfViewDeg: 4.0,
        );

        $nebula = new CelestialObject([
            'catalog' => 'NGC',
            'catalog_number' => '7000',
            'type' => 'nebula',
            'ra_deg' => 314.7,
            'dec_deg' => 44.5,
            'angular_width_arcmin' => 120,
            'angular_height_arcmin' => 100,
        ]);
        $nebula->setAttribute('astrophotos_count', 0);

        $matched = $calculator->calculate(
            $nebula,
            $visibility,
            $moon,
            $optics,
            null,
            new TargetHistory(0, 0, 0, null),
            ['narrowband'],
        );
        $mismatched = $calculator->calculate(
            $nebula,
            $visibility,
            $moon,
            $optics,
            null,
            new TargetHistory(5, 4, 3600 * 15, $at->subDays(5)),
            ['narrowband'],
            ['nebula' => 40],
            40,
            $at,
        );

        $this->assertGreaterThan($mismatched->score, $matched->score);
        $this->assertTrue($matched->neverPhotographed);
        $this->assertFalse($mismatched->neverPhotographed);
    }

    private function weather(int $score): WeatherScore
    {
        return new WeatherScore($score, 'Test', 0, 0, 0, 0, 0, 10, 0, 30);
    }
}
