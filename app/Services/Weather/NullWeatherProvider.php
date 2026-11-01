<?php

namespace App\Services\Weather;

use App\Contracts\Weather\WeatherProvider;
use App\Data\Weather\WeatherForecast;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;

class NullWeatherProvider implements WeatherProvider
{
    public function forecast(ObservingLocation $location, CarbonImmutable $from, int $days): WeatherForecast
    {
        return new WeatherForecast('none', CarbonImmutable::now(), []);
    }
}
