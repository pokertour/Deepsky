<?php

namespace App\Contracts\Weather;

use App\Data\Weather\WeatherForecast;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;

interface WeatherProvider
{
    public function forecast(ObservingLocation $location, CarbonImmutable $from, int $days): WeatherForecast;
}
