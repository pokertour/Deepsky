<?php

namespace App\Data\Weather;

use App\Models\ObservingLocation;

final readonly class ForecastData
{
    /** @param list<ForecastNight> $nights */
    public function __construct(
        public ?ObservingLocation $location,
        public array $nights,
        public bool $weatherAvailable,
    ) {}
}
