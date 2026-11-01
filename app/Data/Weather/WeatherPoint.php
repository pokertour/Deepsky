<?php

namespace App\Data\Weather;

use Carbon\CarbonImmutable;

final readonly class WeatherPoint
{
    public function __construct(
        public CarbonImmutable $at,
        public ?float $cloudCoverPercent,
        public ?float $lowCloudCoverPercent,
        public ?float $mediumCloudCoverPercent,
        public ?float $highCloudCoverPercent,
        public ?float $humidityPercent,
        public ?float $temperatureCelsius,
        public ?float $dewPointCelsius,
        public ?float $windSpeedKmh,
        public ?float $windGustKmh,
        public ?float $precipitationProbabilityPercent,
        public ?float $visibilityMeters,
        public ?float $seeingIndex = null,
        public ?float $transparencyIndex = null,
    ) {}
}
