<?php

namespace App\Services\Weather;

use App\Contracts\Weather\WeatherProvider;
use App\Data\Weather\WeatherForecast;
use App\Data\Weather\WeatherPoint;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenMeteoWeatherProvider implements WeatherProvider
{
    private const HOURLY_FIELDS = [
        'cloud_cover',
        'cloud_cover_low',
        'cloud_cover_mid',
        'cloud_cover_high',
        'relative_humidity_2m',
        'temperature_2m',
        'dew_point_2m',
        'wind_speed_10m',
        'wind_gusts_10m',
        'precipitation_probability',
        'visibility',
    ];

    public function forecast(ObservingLocation $location, CarbonImmutable $from, int $days): WeatherForecast
    {
        $days = max(1, min(8, $days));
        $response = $this->client()->get('/v1/forecast', [
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'hourly' => implode(',', self::HOURLY_FIELDS),
            'timezone' => $location->timezone,
            'start_date' => $from->format('Y-m-d'),
            'end_date' => $from->addDays($days - 1)->format('Y-m-d'),
        ])->throw();
        $hourly = $response->json('hourly');

        if (! is_array($hourly) || ! is_array($hourly['time'] ?? null)) {
            throw new RuntimeException('La réponse météo horaire est invalide.');
        }

        $points = [];

        foreach ($hourly['time'] as $index => $time) {
            $points[] = new WeatherPoint(
                at: CarbonImmutable::parse($time, $location->timezone),
                cloudCoverPercent: $this->number($hourly, 'cloud_cover', $index),
                lowCloudCoverPercent: $this->number($hourly, 'cloud_cover_low', $index),
                mediumCloudCoverPercent: $this->number($hourly, 'cloud_cover_mid', $index),
                highCloudCoverPercent: $this->number($hourly, 'cloud_cover_high', $index),
                humidityPercent: $this->number($hourly, 'relative_humidity_2m', $index),
                temperatureCelsius: $this->number($hourly, 'temperature_2m', $index),
                dewPointCelsius: $this->number($hourly, 'dew_point_2m', $index),
                windSpeedKmh: $this->number($hourly, 'wind_speed_10m', $index),
                windGustKmh: $this->number($hourly, 'wind_gusts_10m', $index),
                precipitationProbabilityPercent: $this->number($hourly, 'precipitation_probability', $index),
                visibilityMeters: $this->number($hourly, 'visibility', $index),
            );
        }

        return new WeatherForecast('open-meteo', CarbonImmutable::now($location->timezone), $points);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('nightscope.weather.open_meteo.endpoint'))
            ->acceptJson()
            ->connectTimeout((int) config('nightscope.weather.connect_timeout', 5))
            ->timeout((int) config('nightscope.weather.timeout', 15))
            ->retry(2, 250, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false);
    }

    /** @param array<string, mixed> $hourly */
    private function number(array $hourly, string $key, int $index): ?float
    {
        $value = $hourly[$key][$index] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
