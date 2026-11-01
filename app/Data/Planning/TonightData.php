<?php

namespace App\Data\Planning;

use App\Data\Astronomy\MoonData;
use App\Data\Astronomy\SolarNight;
use App\Data\Equipment\EquipmentOptics;
use App\Data\Weather\WeatherScore;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;

final readonly class TonightData
{
    /**
     * @param  list<TargetScore>  $targets
     * @param  list<string>  $objectTypes
     */
    public function __construct(
        public CarbonImmutable $date,
        public ?ObservingLocation $location,
        public ?EquipmentSetup $equipmentSetup,
        public ?EquipmentOptics $optics,
        public ?SolarNight $night,
        public ?MoonData $moon,
        public ?WeatherScore $weather,
        public ?NightScore $nightScore,
        public array $targets,
        public array $objectTypes = [],
        public int $recommendationLimit = 12,
        public bool $hasMoreRecommendations = false,
        public bool $neverPhotographedOnly = false,
        public string $search = '',
        public bool $favoritesOnly = false,
    ) {}

    public function nextRecommendationLimit(): int
    {
        $step = (int) config('nightscope.tonight.recommendation_step', 12);
        $max = (int) config('nightscope.tonight.recommendation_max', 48);

        return min($max, $this->recommendationLimit + $step);
    }
}
