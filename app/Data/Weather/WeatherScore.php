<?php

namespace App\Data\Weather;

final readonly class WeatherScore
{
    public function __construct(
        public int $score,
        public string $label,
        public float $averageCloudCoverPercent,
        public float $averageHumidityPercent,
        public float $averageWindSpeedKmh,
        public float $maximumWindGustKmh,
        public float $precipitationRiskPercent,
        public float $averageTemperatureCelsius,
        public float $averageDewPointCelsius,
        public float $averageVisibilityKm,
        public ?float $averageSeeingIndex = null,
        public ?float $averageTransparencyIndex = null,
    ) {}
}
