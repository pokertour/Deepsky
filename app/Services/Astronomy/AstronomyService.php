<?php

namespace App\Services\Astronomy;

use App\Data\Astronomy\MoonData;
use App\Data\Astronomy\SolarNight;
use App\Data\Astronomy\TargetVisibility;
use App\Models\CelestialObject;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;

class AstronomyService
{
    public function __construct(
        private SolarCalculator $solar,
        private LunarCalculator $lunar,
        private VisibilityCalculator $visibility,
    ) {}

    public function night(CarbonImmutable $date, ObservingLocation $location): SolarNight
    {
        return $this->solar->forNight($date, $location);
    }

    public function moon(CarbonImmutable $at, ObservingLocation $location): MoonData
    {
        return $this->lunar->at($at, $location);
    }

    public function targetVisibility(
        CelestialObject $target,
        ObservingLocation $location,
        CarbonImmutable $date,
    ): TargetVisibility {
        return $this->visibility->forNight($target, $location, $date);
    }
}
