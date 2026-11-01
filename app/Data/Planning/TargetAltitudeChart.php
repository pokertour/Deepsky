<?php

namespace App\Data\Planning;

use Carbon\CarbonImmutable;

final readonly class TargetAltitudeChart
{
    public function __construct(
        public string $targetPolyline,
        public string $moonPolyline,
        public ?float $recommendedWindowStartX,
        public ?float $recommendedWindowWidth,
        public ?float $transitX,
        public string $startLabel,
        public string $endLabel,
        public ?string $transitLabel,
        public ?CarbonImmutable $recommendedWindowStart,
        public ?CarbonImmutable $recommendedWindowEnd,
    ) {}
}
