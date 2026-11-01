<?php

namespace App\Services\Observing;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class ElevationLookup
{
    public function meters(float $latitude, float $longitude): float
    {
        $key = 'observing:elevation:v1:'.hash('sha256', json_encode([$latitude, $longitude, config('nightscope.elevation.url')]));

        return Cache::remember($key, now()->addDays((int) config('nightscope.elevation.cache_days', 30)), function () use ($latitude, $longitude): float {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('nightscope.elevation.connect_timeout', 3))
                ->timeout((int) config('nightscope.elevation.timeout', 8))
                ->retry([100, 300], when: fn (Exception $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->get(config('nightscope.elevation.url'), ['latitude' => $latitude, 'longitude' => $longitude])
                ->throw();
            $elevation = $response->json('elevation.0');

            if ((! is_int($elevation) && ! is_float($elevation)) || ! is_finite((float) $elevation)
                || $elevation < -500 || $elevation > 10000) {
                throw new UnexpectedValueException('Invalid elevation response.');
            }

            return (float) $elevation;
        });
    }
}
