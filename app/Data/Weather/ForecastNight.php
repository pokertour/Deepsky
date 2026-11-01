<?php

namespace App\Data\Weather;

use App\Data\Astronomy\SolarNight;
use Carbon\CarbonImmutable;

final readonly class ForecastNight
{
    public function __construct(
        public CarbonImmutable $date,
        public SolarNight $night,
        public ?WeatherScore $weather,
    ) {}
}
