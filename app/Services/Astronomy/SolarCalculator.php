<?php

namespace App\Services\Astronomy;

use App\Data\Astronomy\SolarNight;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;

class SolarCalculator
{
    public function forNight(CarbonImmutable $date, ObservingLocation $location): SolarNight
    {
        $localDate = $date->setTimezone($location->timezone)->startOfDay();
        $nextDate = $localDate->addDay();

        return new SolarNight(
            sunset: $this->event($localDate, $location, 90.833, false),
            sunrise: $this->event($nextDate, $location, 90.833, true),
            civilTwilightEnd: $this->event($localDate, $location, 96, false),
            civilTwilightStart: $this->event($nextDate, $location, 96, true),
            nauticalTwilightEnd: $this->event($localDate, $location, 102, false),
            nauticalTwilightStart: $this->event($nextDate, $location, 102, true),
            astronomicalNightStart: $this->event($localDate, $location, 108, false),
            astronomicalNightEnd: $this->event($nextDate, $location, 108, true),
        );
    }

    private function event(
        CarbonImmutable $date,
        ObservingLocation $location,
        float $zenithDeg,
        bool $sunrise,
    ): ?CarbonImmutable {
        $day = $date->dayOfYear;
        $longitudeHour = $location->longitude / 15;
        $approximateTime = $day + (($sunrise ? 6 : 18) - $longitudeHour) / 24;
        $meanAnomaly = 0.9856 * $approximateTime - 3.289;
        $trueLongitude = $this->normalize(
            $meanAnomaly
            + 1.916 * sin(deg2rad($meanAnomaly))
            + 0.020 * sin(deg2rad(2 * $meanAnomaly))
            + 282.634,
        );
        $rightAscension = $this->normalize(rad2deg(atan(0.91764 * tan(deg2rad($trueLongitude)))));
        $rightAscension += floor($trueLongitude / 90) * 90 - floor($rightAscension / 90) * 90;
        $rightAscension /= 15;
        $sinDeclination = 0.39782 * sin(deg2rad($trueLongitude));
        $cosDeclination = cos(asin($sinDeclination));
        $cosHourAngle = (
            cos(deg2rad($zenithDeg)) - $sinDeclination * sin(deg2rad($location->latitude))
        ) / ($cosDeclination * cos(deg2rad($location->latitude)));

        if ($cosHourAngle > 1 || $cosHourAngle < -1) {
            return null;
        }

        $hourAngle = $sunrise
            ? 360 - rad2deg(acos($cosHourAngle))
            : rad2deg(acos($cosHourAngle));
        $localMeanTime = $hourAngle / 15 + $rightAscension - 0.06571 * $approximateTime - 6.622;
        $utcHours = fmod($localMeanTime - $longitudeHour + 24, 24);
        $utcMidnight = CarbonImmutable::create(
            $date->year,
            $date->month,
            $date->day,
            0,
            0,
            0,
            'UTC',
        );

        return $utcMidnight
            ->addSeconds((int) round($utcHours * 3600))
            ->setTimezone($location->timezone);
    }

    private function normalize(float $degrees): float
    {
        $degrees = fmod($degrees, 360);

        return $degrees < 0 ? $degrees + 360 : $degrees;
    }
}
