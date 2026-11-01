<?php

namespace App\Services\Equipment;

use InvalidArgumentException;

class FieldOfViewCalculator
{
    public function calculateDegrees(float $sensorSizeMm, float $focalLengthMm): float
    {
        if ($sensorSizeMm <= 0 || $focalLengthMm <= 0) {
            throw new InvalidArgumentException('La taille du capteur et la focale doivent être positives.');
        }

        return rad2deg(2 * atan($sensorSizeMm / (2 * $focalLengthMm)));
    }
}
