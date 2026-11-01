<?php

namespace App\Data\Equipment;

final readonly class EquipmentOptics
{
    public function __construct(
        public float $nativeFocalLengthMm,
        public float $effectiveFocalLengthMm,
        public float $apertureMm,
        public float $focalRatio,
        public float $sensorWidthMm,
        public float $sensorHeightMm,
        public float $pixelSizeUm,
        public float $pixelScaleArcsec,
        public float $horizontalFieldOfViewDeg,
        public float $verticalFieldOfViewDeg,
        public float $diagonalFieldOfViewDeg,
    ) {}
}
