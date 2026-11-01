<?php

namespace App\Services\Weather;

use App\Data\Weather\ForecastData;
use App\Data\Weather\ForecastNight;
use App\Models\ObservingLocation;
use App\Services\Astronomy\SolarCalculator;
use Carbon\CarbonImmutable;

class ForecastService
{
    public function __construct(
        private WeatherManager $weather,
        private WeatherScoreCalculator $scores,
        private SolarCalculator $solar,
    ) {}

    public function build(CarbonImmutable $from): ForecastData
    {
        $location = ObservingLocation::query()->where('is_default', true)->first()
            ?? ObservingLocation::query()->oldest('id')->first();

        if (! $location) {
            return new ForecastData(null, [], false);
        }

        $from = $from->setTimezone($location->timezone)->startOfDay();
        $forecast = $this->weather->forecast($location, $from, 8);
        $nights = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $date = $from->addDays($offset);
            $night = $this->solar->forNight($date, $location);
            $nights[] = new ForecastNight($date, $night, $this->scores->calculate($forecast, $night));
        }

        return new ForecastData($location, $nights, $forecast->available());
    }
}
