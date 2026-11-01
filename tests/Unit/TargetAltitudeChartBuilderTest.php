<?php

namespace Tests\Unit;

use App\Data\Astronomy\AltitudeSample;
use App\Data\Astronomy\TargetVisibility;
use App\Data\Astronomy\VisibilityPoint;
use App\Services\Planning\TargetAltitudeChartBuilder;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class TargetAltitudeChartBuilderTest extends TestCase
{
    public function test_build_marks_recommended_window_and_transit(): void
    {
        $start = CarbonImmutable::parse('2026-08-03 22:00:00', 'Europe/Paris');
        $timeline = [];

        for ($minute = 0; $minute <= 240; $minute += 30) {
            $at = $start->addMinutes($minute);
            $altitude = 10 + ($minute <= 120 ? $minute / 4 : (240 - $minute) / 4);
            $timeline[] = new VisibilityPoint(
                at: $at,
                altitudeDeg: $altitude,
                azimuthDeg: 180,
                horizonAltitudeDeg: 0,
                visible: $altitude >= 20,
            );
        }

        $moon = array_map(
            fn (VisibilityPoint $point): AltitudeSample => new AltitudeSample($point->at, $point->altitudeDeg - 15),
            $timeline,
        );

        $visibility = new TargetVisibility(
            timeline: $timeline,
            rise: $timeline[2]->at,
            set: $timeline[6]->at,
            transit: $timeline[4]->at,
            maxAltitudeDeg: $timeline[4]->altitudeDeg,
            minutesAboveAltitude: [20 => 180, 30 => 120, 40 => 60],
        );

        $chart = (new TargetAltitudeChartBuilder)->build($visibility, $moon);

        $this->assertNotSame('', $chart->targetPolyline);
        $this->assertNotSame('', $chart->moonPolyline);
        $this->assertNotNull($chart->transitX);
        $this->assertSame(500.0, $chart->transitX);
        $this->assertNotNull($chart->recommendedWindowStartX);
        $this->assertNotNull($chart->recommendedWindowWidth);
        $this->assertGreaterThan(0, $chart->recommendedWindowWidth);
        $this->assertSame('22:00', $chart->startLabel);
        $this->assertSame('02:00', $chart->endLabel);
        $this->assertSame('00:00', $chart->transitLabel);
    }
}
