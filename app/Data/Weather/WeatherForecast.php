<?php

namespace App\Data\Weather;

use Carbon\CarbonImmutable;

final readonly class WeatherForecast
{
    /** @param list<WeatherPoint> $points */
    public function __construct(
        public string $provider,
        public CarbonImmutable $generatedAt,
        public array $points,
    ) {}

    public function available(): bool
    {
        return $this->points !== [];
    }
}
