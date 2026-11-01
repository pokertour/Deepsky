<?php

namespace App\Data\Astronomy;

final readonly class HorizontalCoordinates
{
    public function __construct(
        public float $altitudeDeg,
        public float $azimuthDeg,
    ) {}
}
