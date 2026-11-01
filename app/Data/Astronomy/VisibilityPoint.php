<?php

namespace App\Data\Astronomy;

use Carbon\CarbonImmutable;

final readonly class VisibilityPoint
{
    public function __construct(
        public CarbonImmutable $at,
        public float $altitudeDeg,
        public float $azimuthDeg,
        public float $horizonAltitudeDeg,
        public bool $visible,
    ) {}
}
