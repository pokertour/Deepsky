<?php

namespace App\Data\Astronomy;

use Carbon\CarbonImmutable;

final readonly class AltitudeSample
{
    public function __construct(
        public CarbonImmutable $at,
        public float $altitudeDeg,
    ) {}
}
