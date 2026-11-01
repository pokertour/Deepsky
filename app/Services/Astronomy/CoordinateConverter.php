<?php

namespace App\Services\Astronomy;

use App\Data\Astronomy\HorizontalCoordinates;
use Carbon\CarbonImmutable;

class CoordinateConverter
{
    public function equatorialToHorizontal(
        float $rightAscensionDeg,
        float $declinationDeg,
        float $latitudeDeg,
        float $longitudeDeg,
        CarbonImmutable $at,
    ): HorizontalCoordinates {
        $hourAngle = $this->normalizeSigned(
            $this->localSiderealTimeDeg($at, $longitudeDeg) - $rightAscensionDeg,
        );
        $hourAngleRad = deg2rad($hourAngle);
        $declinationRad = deg2rad($declinationDeg);
        $latitudeRad = deg2rad($latitudeDeg);
        $altitude = asin(
            sin($declinationRad) * sin($latitudeRad)
            + cos($declinationRad) * cos($latitudeRad) * cos($hourAngleRad),
        );
        $azimuth = atan2(
            sin($hourAngleRad),
            cos($hourAngleRad) * sin($latitudeRad) - tan($declinationRad) * cos($latitudeRad),
        );

        return new HorizontalCoordinates(
            altitudeDeg: rad2deg($altitude),
            azimuthDeg: $this->normalizeDegrees(rad2deg($azimuth) + 180),
        );
    }

    public function localSiderealTimeDeg(CarbonImmutable $at, float $longitudeDeg): float
    {
        $julianDay = ($at->getTimestamp() / 86400) + 2440587.5;
        $daysSinceJ2000 = $julianDay - 2451545.0;
        $greenwich = 280.46061837
            + 360.98564736629 * $daysSinceJ2000
            + 0.000387933 * ($daysSinceJ2000 / 36525) ** 2;

        return $this->normalizeDegrees($greenwich + $longitudeDeg);
    }

    public function angularSeparationDeg(float $ra1Deg, float $dec1Deg, float $ra2Deg, float $dec2Deg): float
    {
        $cosine = sin(deg2rad($dec1Deg)) * sin(deg2rad($dec2Deg))
            + cos(deg2rad($dec1Deg)) * cos(deg2rad($dec2Deg)) * cos(deg2rad($ra1Deg - $ra2Deg));

        return rad2deg(acos(max(-1, min(1, $cosine))));
    }

    public function normalizeDegrees(float $degrees): float
    {
        $normalized = fmod($degrees, 360);

        return $normalized < 0 ? $normalized + 360 : $normalized;
    }

    private function normalizeSigned(float $degrees): float
    {
        $normalized = $this->normalizeDegrees($degrees);

        return $normalized > 180 ? $normalized - 360 : $normalized;
    }
}
