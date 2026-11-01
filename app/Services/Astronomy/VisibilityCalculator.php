<?php

namespace App\Services\Astronomy;

use App\Data\Astronomy\SolarNight;
use App\Data\Astronomy\TargetVisibility;
use App\Data\Astronomy\VisibilityPoint;
use App\Models\CelestialObject;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;

class VisibilityCalculator
{
    public function __construct(
        private CoordinateConverter $coordinates,
        private SolarCalculator $solar,
        private HorizonMask $horizon = new HorizonMask,
    ) {}

    public function forNight(
        CelestialObject $target,
        ObservingLocation $location,
        CarbonImmutable $date,
        int $stepMinutes = 10,
        float $minimumAltitudeDeg = 20,
        ?SolarNight $night = null,
    ): TargetVisibility {
        $window = ($night ?? $this->solar->forNight($date, $location))->observingWindow();

        if ($window === null) {
            return new TargetVisibility([], null, null, null, null, [20 => 0, 30 => 0, 40 => 0]);
        }

        $timeline = [];
        $durations = [20 => 0, 30 => 0, 40 => 0];
        [$cursor, $end] = $window;

        while ($cursor <= $end) {
            $horizontal = $this->coordinates->equatorialToHorizontal(
                $target->ra_deg,
                $target->dec_deg,
                $location->latitude,
                $location->longitude,
                $cursor,
            );
            $horizon = $this->horizon->altitude($location->horizon_mask ?? [], $horizontal->azimuthDeg);
            $timeline[] = new VisibilityPoint(
                at: $cursor,
                altitudeDeg: $horizontal->altitudeDeg,
                azimuthDeg: $horizontal->azimuthDeg,
                horizonAltitudeDeg: $horizon,
                visible: $horizontal->altitudeDeg >= max($minimumAltitudeDeg, $horizon),
            );
            $cursor = $cursor->addMinutes($stepMinutes);
        }

        foreach (array_slice($timeline, 0, -1) as $point) {
            foreach (array_keys($durations) as $altitude) {
                if ($point->altitudeDeg >= max($altitude, $point->horizonAltitudeDeg)) {
                    $durations[$altitude] += $stepMinutes;
                }
            }
        }

        $visible = array_values(array_filter($timeline, fn (VisibilityPoint $point): bool => $point->visible));
        $transit = $timeline === [] ? null : $this->highestPoint($timeline);

        return new TargetVisibility(
            timeline: $timeline,
            rise: $visible[0]->at ?? null,
            set: $visible === [] ? null : $visible[array_key_last($visible)]->at,
            transit: $transit?->at,
            maxAltitudeDeg: $transit?->altitudeDeg,
            minutesAboveAltitude: $durations,
        );
    }

    /** @param list<VisibilityPoint> $points */
    private function highestPoint(array $points): VisibilityPoint
    {
        return array_reduce(
            $points,
            fn (?VisibilityPoint $highest, VisibilityPoint $point): VisibilityPoint => $highest === null || $point->altitudeDeg > $highest->altitudeDeg ? $point : $highest,
        );
    }
}
