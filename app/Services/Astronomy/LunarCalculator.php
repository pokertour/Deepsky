<?php

namespace App\Services\Astronomy;

use App\Data\Astronomy\MoonData;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;

class LunarCalculator
{
    public function __construct(private CoordinateConverter $coordinates) {}

    public function at(CarbonImmutable $at, ObservingLocation $location): MoonData
    {
        [$rightAscension, $declination, $altitude, $azimuth, $illumination, $phase] = $this->state($at, $location);

        return new MoonData(
            rightAscensionDeg: $rightAscension,
            declinationDeg: $declination,
            altitudeDeg: $altitude,
            azimuthDeg: $azimuth,
            illuminationPercent: $illumination,
            phaseFraction: $phase,
        );
    }

    public function forNight(
        CarbonImmutable $date,
        ObservingLocation $location,
        ?CarbonImmutable $at = null,
    ): MoonData {
        $localDate = $date->setTimezone($location->timezone)->startOfDay();
        $at ??= $localDate->setTime(23, 0);
        [$rightAscension, $declination, $altitude, $azimuth, $illumination, $phase] = $this->state($at, $location);
        [$rise, $set] = $this->riseAndSet($localDate, $location);

        return new MoonData(
            rightAscensionDeg: $rightAscension,
            declinationDeg: $declination,
            altitudeDeg: $altitude,
            azimuthDeg: $azimuth,
            illuminationPercent: $illumination,
            phaseFraction: $phase,
            rise: $rise,
            set: $set,
        );
    }

    public function separationFromTargetDeg(
        float $targetRaDeg,
        float $targetDecDeg,
        CarbonImmutable $at,
        ObservingLocation $location,
    ): float {
        $moon = $this->at($at, $location);

        return $this->coordinates->angularSeparationDeg(
            $targetRaDeg,
            $targetDecDeg,
            $moon->rightAscensionDeg,
            $moon->declinationDeg,
        );
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function riseAndSet(CarbonImmutable $localDate, ObservingLocation $location): array
    {
        $start = $localDate->setTimezone($location->timezone)->setTime(12, 0);
        $end = $start->addDay();
        $stepMinutes = 10;
        $previousAt = $start;
        $previousAltitude = $this->state($start, $location)[2];
        $rise = null;
        $set = null;
        $cursor = $start->addMinutes($stepMinutes);

        while ($cursor <= $end) {
            $altitude = $this->state($cursor, $location)[2];

            if ($rise === null && $previousAltitude < 0 && $altitude >= 0) {
                $rise = $this->crossingTime($previousAt, $previousAltitude, $cursor, $altitude);
            }

            if ($set === null && $previousAltitude >= 0 && $altitude < 0) {
                $set = $this->crossingTime($previousAt, $previousAltitude, $cursor, $altitude);
            }

            if ($rise !== null && $set !== null) {
                break;
            }

            $previousAt = $cursor;
            $previousAltitude = $altitude;
            $cursor = $cursor->addMinutes($stepMinutes);
        }

        return [$rise, $set];
    }

    private function crossingTime(
        CarbonImmutable $before,
        float $altitudeBefore,
        CarbonImmutable $after,
        float $altitudeAfter,
    ): CarbonImmutable {
        $span = max(0.001, abs($altitudeAfter - $altitudeBefore));
        $fraction = abs($altitudeBefore) / $span;
        $seconds = (int) round(($after->getTimestamp() - $before->getTimestamp()) * min(1, max(0, $fraction)));

        return $before->addSeconds($seconds);
    }

    /**
     * @return array{float, float, float, float, float, float}
     */
    private function state(CarbonImmutable $at, ObservingLocation $location): array
    {
        $days = ($at->getTimestamp() / 86400) - 10957.5;
        $ascendingNode = $this->normalize(125.1228 - 0.0529538083 * $days);
        $inclination = 5.1454;
        $periapsis = $this->normalize(318.0634 + 0.1643573223 * $days);
        $eccentricity = 0.0549;
        $meanAnomaly = $this->normalize(115.3654 + 13.0649929509 * $days);
        $eccentricAnomaly = $meanAnomaly
            + rad2deg($eccentricity * sin(deg2rad($meanAnomaly)) * (1 + $eccentricity * cos(deg2rad($meanAnomaly))));
        $x = 60.2666 * (cos(deg2rad($eccentricAnomaly)) - $eccentricity);
        $y = 60.2666 * sqrt(1 - $eccentricity ** 2) * sin(deg2rad($eccentricAnomaly));
        $trueAnomaly = rad2deg(atan2($y, $x));
        $distance = hypot($x, $y);
        $orbitalLongitude = $trueAnomaly + $periapsis;
        $eclipticX = $distance * (
            cos(deg2rad($ascendingNode)) * cos(deg2rad($orbitalLongitude))
            - sin(deg2rad($ascendingNode)) * sin(deg2rad($orbitalLongitude)) * cos(deg2rad($inclination))
        );
        $eclipticY = $distance * (
            sin(deg2rad($ascendingNode)) * cos(deg2rad($orbitalLongitude))
            + cos(deg2rad($ascendingNode)) * sin(deg2rad($orbitalLongitude)) * cos(deg2rad($inclination))
        );
        $eclipticZ = $distance * sin(deg2rad($orbitalLongitude)) * sin(deg2rad($inclination));
        $obliquity = 23.4393 - 3.563e-7 * $days;
        $equatorialX = $eclipticX;
        $equatorialY = $eclipticY * cos(deg2rad($obliquity)) - $eclipticZ * sin(deg2rad($obliquity));
        $equatorialZ = $eclipticY * sin(deg2rad($obliquity)) + $eclipticZ * cos(deg2rad($obliquity));
        $rightAscension = $this->normalize(rad2deg(atan2($equatorialY, $equatorialX)));
        $declination = rad2deg(atan2($equatorialZ, hypot($equatorialX, $equatorialY)));
        $horizontal = $this->coordinates->equatorialToHorizontal(
            $rightAscension,
            $declination,
            $location->latitude,
            $location->longitude,
            $at,
        );
        [$sunRa, $sunDec, $sunLongitude] = $this->sunPosition($days);
        $elongation = $this->coordinates->angularSeparationDeg($rightAscension, $declination, $sunRa, $sunDec);
        $moonLongitude = $this->normalize(rad2deg(atan2($eclipticY, $eclipticX)));

        return [
            $rightAscension,
            $declination,
            $horizontal->altitudeDeg,
            $horizontal->azimuthDeg,
            (1 - cos(deg2rad($elongation))) * 50,
            $this->normalize($moonLongitude - $sunLongitude) / 360,
        ];
    }

    /** @return array{float, float, float} */
    private function sunPosition(float $days): array
    {
        $periapsis = 282.9404 + 4.70935e-5 * $days;
        $eccentricity = 0.016709 - 1.151e-9 * $days;
        $meanAnomaly = $this->normalize(356.0470 + 0.9856002585 * $days);
        $eccentricAnomaly = $meanAnomaly
            + rad2deg($eccentricity * sin(deg2rad($meanAnomaly)) * (1 + $eccentricity * cos(deg2rad($meanAnomaly))));
        $x = cos(deg2rad($eccentricAnomaly)) - $eccentricity;
        $y = sqrt(1 - $eccentricity ** 2) * sin(deg2rad($eccentricAnomaly));
        $longitude = $this->normalize(rad2deg(atan2($y, $x)) + $periapsis);
        $obliquity = 23.4393 - 3.563e-7 * $days;
        $rightAscension = $this->normalize(rad2deg(atan2(
            sin(deg2rad($longitude)) * cos(deg2rad($obliquity)),
            cos(deg2rad($longitude)),
        )));
        $declination = rad2deg(asin(sin(deg2rad($longitude)) * sin(deg2rad($obliquity))));

        return [$rightAscension, $declination, $longitude];
    }

    private function normalize(float $degrees): float
    {
        $degrees = fmod($degrees, 360);

        return $degrees < 0 ? $degrees + 360 : $degrees;
    }
}
