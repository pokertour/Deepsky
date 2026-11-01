<?php

namespace App\Services\Weather;

use App\Contracts\Weather\WeatherProvider;
use App\Data\Weather\WeatherForecast;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WeatherManager
{
    public function __construct(private WeatherProvider $provider) {}

    public function forecast(ObservingLocation $location, CarbonImmutable $from, int $days = 7): WeatherForecast
    {
        $cacheKey = implode(':', [
            'nightscope', 'weather', 'v2', config('nightscope.weather.driver', 'none'),
            config('nightscope.weather.seven_timer.enabled') ? '7t' : 'no7t',
            $location->id,
            $location->updated_at?->getTimestamp() ?? 0,
            $from->format('Y-m-d'), $days,
        ]);

        return Cache::remember($cacheKey, now()->addMinutes((int) config('nightscope.weather.cache_minutes', 45)), function () use ($location, $from, $days): WeatherForecast {
            try {
                return $this->provider->forecast($location, $from, $days);
            } catch (Throwable $exception) {
                report($exception);

                return new WeatherForecast('unavailable', CarbonImmutable::now(), []);
            }
        });
    }
}
