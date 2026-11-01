<?php

namespace App\Services\Equipment;

use InvalidArgumentException;

class PixelScaleCalculator
{
    public function calculate(float $pixelSizeUm, float $focalLengthMm): float
    {
        if ($pixelSizeUm <= 0 || $focalLengthMm <= 0) {
            throw new InvalidArgumentException('La taille de pixel et la focale doivent être positives.');
        }

        return 206.265 * $pixelSizeUm / $focalLengthMm;
    }
}
