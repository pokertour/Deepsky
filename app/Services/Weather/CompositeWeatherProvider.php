<?php

namespace App\Services\Weather;

use App\Contracts\Weather\WeatherProvider;
use App\Data\Weather\AstroConditionSample;
use App\Data\Weather\WeatherForecast;
use App\Data\Weather\WeatherPoint;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;
use Throwable;

class CompositeWeatherProvider implements WeatherProvider
{
    public function __construct(
        private OpenMeteoWeatherProvider $openMeteo,
        private SevenTimerAstroProvider $sevenTimer,
    ) {}

    public function forecast(ObservingLocation $location, CarbonImmutable $from, int $days): WeatherForecast
    {
        $base = $this->openMeteo->forecast($location, $from, $days);

        if (! config('nightscope.weather.seven_timer.enabled', true) || ! $base->available()) {
            return $base;
        }

        try {
            $samples = $this->sevenTimer->samples($location);
        } catch (Throwable $exception) {
            report($exception);

            return $base;
        }

        if ($samples === []) {
            return $base;
        }

        $enriched = array_map(
            fn (WeatherPoint $point): WeatherPoint => $this->enrich($point, $samples),
            $base->points,
        );
        $hasAstro = false;

        foreach ($enriched as $point) {
            if ($point->seeingIndex !== null || $point->transparencyIndex !== null) {
                $hasAstro = true;
                break;
            }
        }

        return new WeatherForecast(
            provider: $hasAstro ? 'open-meteo+7timer' : $base->provider,
            generatedAt: $base->generatedAt,
            points: $enriched,
        );
    }

    /** @param  list<AstroConditionSample>  $samples */
    private function enrich(WeatherPoint $point, array $samples): WeatherPoint
    {
        $nearest = null;
        $nearestDelta = null;

        foreach ($samples as $sample) {
            $delta = abs($point->at->diffInSeconds($sample->at, false));

            if ($nearestDelta === null || $delta < $nearestDelta) {
                $nearest = $sample;
                $nearestDelta = $delta;
            }
        }

        $maxDeltaSeconds = (int) config('nightscope.weather.seven_timer.match_minutes', 120) * 60;

        if ($nearest === null || $nearestDelta === null || $nearestDelta > $maxDeltaSeconds) {
            return $point;
        }

        return new WeatherPoint(
            at: $point->at,
            cloudCoverPercent: $point->cloudCoverPercent,
            lowCloudCoverPercent: $point->lowCloudCoverPercent,
            mediumCloudCoverPercent: $point->mediumCloudCoverPercent,
            highCloudCoverPercent: $point->highCloudCoverPercent,
            humidityPercent: $point->humidityPercent,
            temperatureCelsius: $point->temperatureCelsius,
            dewPointCelsius: $point->dewPointCelsius,
            windSpeedKmh: $point->windSpeedKmh,
            windGustKmh: $point->windGustKmh,
            precipitationProbabilityPercent: $point->precipitationProbabilityPercent,
            visibilityMeters: $point->visibilityMeters,
            seeingIndex: $nearest->seeingIndex,
            transparencyIndex: $nearest->transparencyIndex,
        );
    }
}
