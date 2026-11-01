<?php

namespace App\Data\Astronomy;

use Carbon\CarbonImmutable;

final readonly class MoonData
{
    public function __construct(
        public float $rightAscensionDeg,
        public float $declinationDeg,
        public float $altitudeDeg,
        public float $azimuthDeg,
        public float $illuminationPercent,
        public float $phaseFraction,
        public ?CarbonImmutable $rise = null,
        public ?CarbonImmutable $set = null,
    ) {}
}
