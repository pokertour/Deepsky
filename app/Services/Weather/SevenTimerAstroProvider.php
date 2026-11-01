<?php

namespace App\Services\Weather;

use App\Data\Weather\AstroConditionSample;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SevenTimerAstroProvider
{
    /**
     * @return list<AstroConditionSample>
     */
    public function samples(ObservingLocation $location): array
    {
        $response = $this->client()->get('/bin/api.pl', [
            'lon' => $location->longitude,
            'lat' => $location->latitude,
            'product' => 'astro',
            'output' => 'json',
        ])->throw();

        $init = $response->json('init');
        $series = $response->json('dataseries');

        if (! is_string($init) || ! is_array($series)) {
            throw new RuntimeException('La réponse 7Timer! astro est invalide.');
        }

        $origin = CarbonImmutable::createFromFormat('YmdH', $init, 'UTC');

        if ($origin === false) {
            throw new RuntimeException('L’horodatage d’initialisation 7Timer! est invalide.');
        }

        $samples = [];

        foreach ($series as $row) {
            if (! is_array($row) || ! isset($row['timepoint']) || ! is_numeric($row['timepoint'])) {
                continue;
            }

            $samples[] = new AstroConditionSample(
                at: $origin->addHours((int) $row['timepoint'])->setTimezone($location->timezone),
                seeingIndex: $this->index($row['seeing'] ?? null),
                transparencyIndex: $this->index($row['transparency'] ?? null),
            );
        }

        return $samples;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('nightscope.weather.seven_timer.endpoint'))
            ->acceptJson()
            ->connectTimeout((int) config('nightscope.weather.connect_timeout', 5))
            ->timeout((int) config('nightscope.weather.timeout', 15))
            ->retry(2, 250, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false);
    }

    private function index(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $index = (float) $value;

        return $index >= 1 && $index <= 8 ? $index : null;
    }
}
