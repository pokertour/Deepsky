<?php

namespace App\Services\Planning;

use App\Data\Astronomy\VisibilityPoint;
use App\Data\Planning\PlannerData;
use App\Data\Planning\PlanSlot;
use App\Data\Planning\TargetScore;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Services\Astronomy\CoordinateConverter;
use App\Services\Astronomy\LunarCalculator;
use App\Services\Astronomy\SolarCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class NightPlanner
{
    public function __construct(
        private TonightService $tonight,
        private LunarCalculator $lunar,
        private CoordinateConverter $coordinates,
        private SolarCalculator $solar,
    ) {}

    public function generate(CarbonImmutable $date): PlannerData
    {
        $tonight = $this->tonight->build($date);

        if (! $tonight->night?->hasAstronomicalNight() || ! $tonight->location) {
            return new PlannerData($tonight, []);
        }

        $minSlot = (int) config('nightscope.planner.minimum_slot_minutes', 45);
        $maxSlot = (int) config('nightscope.planner.maximum_slot_minutes', 120);
        $transition = (int) config('nightscope.planner.transition_minutes', 10);
        $maxTargets = (int) config('nightscope.planner.maximum_targets', 4);
        $minAltitude = (float) config('nightscope.planner.minimum_altitude_deg', 30);
        $minMoonSeparation = (float) config('nightscope.planner.minimum_moon_separation_deg', 30);
        $location = $tonight->location;

        $cursor = $tonight->night->astronomicalNightStart;
        $nightEnd = $tonight->night->astronomicalNightEnd;
        $remaining = collect($tonight->targets);
        $slots = [];

        while (count($slots) < $maxTargets && $cursor->addMinutes($minSlot) <= $nightEnd) {
            $best = null;

            foreach ($remaining as $result) {
                $window = $this->bestWindow(
                    $result,
                    $location,
                    $cursor,
                    $nightEnd,
                    $minAltitude,
                    $minSlot,
                    $maxSlot,
                    $minMoonSeparation,
                );

                if ($window === null) {
                    continue;
                }

                $duration = $window['start']->diffInMinutes($window['end']);
                $density = $result->score * ($duration / max(1, $maxSlot));

                if ($best === null || $density > $best['density']) {
                    $best = [
                        'result' => $result,
                        'start' => $window['start'],
                        'end' => $window['end'],
                        'density' => $density,
                    ];
                }
            }

            if ($best === null) {
                break;
            }

            $slots[] = new PlanSlot(
                target: $best['result']->target,
                startsAt: $best['start'],
                endsAt: $best['end'],
                score: $best['result']->score,
                acquisition: $best['result']->acquisition,
            );
            $remaining = $remaining
                ->reject(fn (TargetScore $result): bool => $result->target->id === $best['result']->target->id)
                ->values();
            $cursor = $best['end']->addMinutes($transition);
        }

        return new PlannerData($tonight, $slots);
    }

    public function suggestSlot(
        CelestialObject $target,
        ObservingLocation $location,
        ?EquipmentSetup $setup,
        CarbonImmutable $date,
        CarbonImmutable $notBefore,
        bool $strict = true,
    ): ?PlanSlot {
        $date = $date->setTimezone($location->timezone);
        $result = $this->tonight->target($target, $date, $location, $setup, setupSpecified: true);
        $night = $this->solar->forNight($date, $location);

        if (! $night->hasAstronomicalNight()) {
            return null;
        }

        $minSlot = (int) config('nightscope.planner.minimum_slot_minutes', 45);
        $maxSlot = (int) config('nightscope.planner.maximum_slot_minutes', 120);
        $minAltitude = $strict
            ? (float) config('nightscope.planner.minimum_altitude_deg', 30)
            : 20.0;
        $minMoonSeparation = $strict
            ? (float) config('nightscope.planner.minimum_moon_separation_deg', 30)
            : 0.0;
        $cursor = $notBefore->greaterThan($night->astronomicalNightStart)
            ? $notBefore
            : $night->astronomicalNightStart;

        $window = $this->bestWindow(
            $result,
            $location,
            $cursor,
            $night->astronomicalNightEnd,
            $minAltitude,
            $minSlot,
            $maxSlot,
            $minMoonSeparation,
        );

        if ($window === null) {
            return null;
        }

        return new PlanSlot(
            target: $result->target,
            startsAt: $window['start'],
            endsAt: $window['end'],
            score: $result->score,
            acquisition: $result->acquisition,
        );
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function bestWindow(
        TargetScore $result,
        ObservingLocation $location,
        CarbonImmutable $cursor,
        CarbonImmutable $nightEnd,
        float $minAltitude,
        int $minSlot,
        int $maxSlot,
        float $minMoonSeparation,
    ): ?array {
        $usable = collect($result->visibility->timeline)
            ->filter(fn (VisibilityPoint $point): bool => $point->at->greaterThanOrEqualTo($cursor)
                && $point->at->lessThanOrEqualTo($nightEnd)
                && $point->visible
                && $point->altitudeDeg >= $minAltitude)
            ->values();

        if ($usable->isEmpty()) {
            return null;
        }

        $best = null;

        foreach ($this->contiguousSegments($usable) as $segment) {
            $segmentStart = $segment[0]->at;
            $segmentEnd = $segment[array_key_last($segment)]->at;
            $available = $segmentStart->diffInMinutes($segmentEnd);

            if ($available < $minSlot) {
                continue;
            }

            $duration = min($maxSlot, $available);
            [$start, $end] = $this->placeSlot(
                $segmentStart,
                $segmentEnd,
                $duration,
                $result->visibility->transit,
            );

            if ($start->diffInMinutes($end) < $minSlot) {
                continue;
            }

            if (! $this->slotClearsMoon($result, $location, $start, $end, $minMoonSeparation)) {
                continue;
            }

            $quality = $this->segmentQuality($segment, $start, $end);

            if ($best === null || $quality > $best['quality']) {
                $best = ['start' => $start, 'end' => $end, 'quality' => $quality];
            }
        }

        return $best === null ? null : ['start' => $best['start'], 'end' => $best['end']];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function placeSlot(
        CarbonImmutable $segmentStart,
        CarbonImmutable $segmentEnd,
        int $duration,
        ?CarbonImmutable $transit,
    ): array {
        if ($transit !== null && $transit->betweenIncluded($segmentStart, $segmentEnd)) {
            $half = intdiv($duration, 2);
            $start = $transit->subMinutes($half);
            $start = $start->lessThan($segmentStart) ? $segmentStart : $start;
            $end = $start->addMinutes($duration);

            if ($end->greaterThan($segmentEnd)) {
                $end = $segmentEnd;
                $start = $end->subMinutes($duration);
                $start = $start->lessThan($segmentStart) ? $segmentStart : $start;
            }

            return [$start, $end];
        }

        $start = $segmentStart;
        $end = $start->addMinutes($duration);

        return [$start, $end->greaterThan($segmentEnd) ? $segmentEnd : $end];
    }

    /**
     * @param  Collection<int, VisibilityPoint>  $points
     * @return list<list<VisibilityPoint>>
     */
    private function contiguousSegments($points): array
    {
        $sampleMinutes = (int) config('nightscope.planner.visibility_sample_minutes', 10);
        $segments = [];
        $current = [];

        foreach ($points as $point) {
            if ($current === []) {
                $current[] = $point;

                continue;
            }

            $previous = $current[array_key_last($current)];

            if ($previous->at->diffInMinutes($point->at) <= $sampleMinutes + 1) {
                $current[] = $point;

                continue;
            }

            $segments[] = $current;
            $current = [$point];
        }

        if ($current !== []) {
            $segments[] = $current;
        }

        return $segments;
    }

    /** @param  list<VisibilityPoint>  $segment */
    private function segmentQuality(array $segment, CarbonImmutable $start, CarbonImmutable $end): float
    {
        $inSlot = array_values(array_filter(
            $segment,
            fn (VisibilityPoint $point): bool => $point->at->betweenIncluded($start, $end),
        ));

        if ($inSlot === []) {
            return 0.0;
        }

        $avgAltitude = array_sum(array_map(
            fn (VisibilityPoint $point): float => $point->altitudeDeg,
            $inSlot,
        )) / count($inSlot);

        return $avgAltitude * ($start->diffInMinutes($end) / 60);
    }

    private function slotClearsMoon(
        TargetScore $result,
        ObservingLocation $location,
        CarbonImmutable $start,
        CarbonImmutable $end,
        float $minMoonSeparation,
    ): bool {
        $span = max(1, $start->diffInSeconds($end));

        foreach ([0.0, 0.5, 1.0] as $ratio) {
            $at = $start->addSeconds((int) ($span * $ratio));
            $moon = $this->lunar->at($at, $location);
            $separation = $this->coordinates->angularSeparationDeg(
                $result->target->ra_deg,
                $result->target->dec_deg,
                $moon->rightAscensionDeg,
                $moon->declinationDeg,
            );

            if ($separation < $minMoonSeparation) {
                return false;
            }
        }

        return true;
    }
}
