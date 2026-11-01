<?php

namespace App\Services\Planning;

use App\Data\Astronomy\AltitudeSample;
use App\Data\Astronomy\TargetVisibility;
use App\Data\Astronomy\VisibilityPoint;
use App\Data\Planning\TargetAltitudeChart;
use Carbon\CarbonImmutable;

class TargetAltitudeChartBuilder
{
    private const CHART_WIDTH = 1000;

    private const BASELINE_Y = 220;

    private const PLOT_HEIGHT = 200;

    private const ALTITUDE_OFFSET = 10;

    private const ALTITUDE_SPAN = 100;

    /**
     * @param  list<AltitudeSample>  $moonTimeline
     */
    public function build(TargetVisibility $visibility, array $moonTimeline, float $recommendedAltitudeDeg = 30): TargetAltitudeChart
    {
        $points = array_values($visibility->timeline);
        $count = max(1, count($points) - 1);
        $start = $points[0]->at ?? null;
        $end = $points === [] ? null : $points[array_key_last($points)]->at;

        $targetPolyline = collect($points)
            ->map(fn (VisibilityPoint $point, int $index): string => $this->point($index / $count, $point->altitudeDeg))
            ->implode(' ');

        $moonCount = max(1, count($moonTimeline) - 1);
        $moonPolyline = collect($moonTimeline)
            ->map(fn (AltitudeSample $sample, int $index): string => $this->point($index / $moonCount, $sample->altitudeDeg))
            ->implode(' ');

        $recommended = array_values(array_filter(
            $points,
            fn (VisibilityPoint $point): bool => $point->visible && $point->altitudeDeg >= $recommendedAltitudeDeg,
        ));
        $windowStart = $recommended[0]->at ?? null;
        $windowEnd = $recommended === [] ? null : $recommended[array_key_last($recommended)]->at;

        return new TargetAltitudeChart(
            targetPolyline: $targetPolyline,
            moonPolyline: $moonPolyline,
            recommendedWindowStartX: $this->xForTime($windowStart, $start, $end),
            recommendedWindowWidth: $this->widthForRange($windowStart, $windowEnd, $start, $end),
            transitX: $this->xForTime($visibility->transit, $start, $end),
            startLabel: $start?->format('H:i') ?? '—',
            endLabel: $end?->format('H:i') ?? '—',
            transitLabel: $visibility->transit?->format('H:i'),
            recommendedWindowStart: $windowStart,
            recommendedWindowEnd: $windowEnd,
        );
    }

    private function point(float $ratio, float $altitudeDeg): string
    {
        $x = round($ratio * self::CHART_WIDTH, 1);
        $y = round(self::BASELINE_Y - (($altitudeDeg + self::ALTITUDE_OFFSET) / self::ALTITUDE_SPAN * self::PLOT_HEIGHT), 1);

        return "{$x},{$y}";
    }

    private function xForTime(?CarbonImmutable $at, ?CarbonImmutable $start, ?CarbonImmutable $end): ?float
    {
        if ($at === null || $start === null || $end === null || $start->equalTo($end)) {
            return null;
        }

        $ratio = $start->diffInSeconds($at, false) / max(1, $start->diffInSeconds($end, false));

        return round(max(0, min(1, $ratio)) * self::CHART_WIDTH, 1);
    }

    private function widthForRange(
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        ?CarbonImmutable $start,
        ?CarbonImmutable $end,
    ): ?float {
        $fromX = $this->xForTime($from, $start, $end);
        $toX = $this->xForTime($to, $start, $end);

        if ($fromX === null || $toX === null) {
            return null;
        }

        return max(0, round($toX - $fromX, 1));
    }
}
