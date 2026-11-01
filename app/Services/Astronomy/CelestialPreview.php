<?php

namespace App\Services\Astronomy;

use App\Models\CelestialObject;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class CelestialPreview
{
    /** Après une panne du CDS, on passe directement au secours pendant ce délai. */
    private const string CDS_DOWN_CACHE_KEY = 'nightscope:preview:cds-down';

    public function __construct(private DssColorComposer $composer) {}

    public function url(CelestialObject $target, ?float $fieldOfViewDeg = null): string
    {
        $fieldOfViewDeg ??= $this->defaultFieldOfView($target);

        return route('admin.nightscope.preview', [
            'ra' => round((float) $target->ra_deg, 6),
            'dec' => round((float) $target->dec_deg, 6),
            'fov' => round($fieldOfViewDeg, 3),
        ]);
    }

    public function defaultFieldOfView(CelestialObject $target): float
    {
        return $this->boundedFieldOfView(max(
            (float) ($target->angular_width_arcmin ?? 0),
            (float) ($target->angular_height_arcmin ?? 0),
        ) / 60 * 1.5);
    }

    public function boundedFieldOfView(float $fieldOfViewDeg): float
    {
        return min(
            (float) config('nightscope.preview.maximum_fov_deg', 4),
            max((float) config('nightscope.preview.minimum_fov_deg', 0.5), $fieldOfViewDeg),
        );
    }

    /**
     * @return list<string>
     */
    public function upstreamUrls(float $ra, float $dec, float $fov): array
    {
        $query = $this->queryString($ra, $dec, $fov);
        $endpoints = (array) config('nightscope.preview.endpoints', [
            config('nightscope.preview.endpoint'),
        ]);

        return array_values(array_filter(array_map(
            fn (mixed $endpoint): string => rtrim((string) $endpoint, '?').'?'.$query,
            $endpoints,
        )));
    }

    public function fetch(float $ra, float $dec, float $fov): string
    {
        $fov = $this->boundedFieldOfView($fov);
        $cacheKey = implode(':', [
            'nightscope', 'preview', 'v1',
            config('nightscope.preview.survey'),
            round($ra, 5),
            round($dec, 5),
            round($fov, 3),
        ]);

        return Cache::remember($cacheKey, now()->addDays((int) config('nightscope.preview.cache_days', 14)), function () use ($ra, $dec, $fov): string {
            $errors = [];

            if (! Cache::has(self::CDS_DOWN_CACHE_KEY)) {
                foreach ($this->upstreamUrls($ra, $dec, $fov) as $url) {
                    try {
                        return $this->download($url);
                    } catch (Throwable $exception) {
                        $errors[] = $url.' → '.$exception->getMessage();
                    }
                }

                Cache::put(self::CDS_DOWN_CACHE_KEY, true, now()->addMinutes((int) config('nightscope.preview.cds_retry_minutes', 10)));
            }

            try {
                return $this->fetchFromSkyView($ra, $dec, $fov);
            } catch (Throwable $exception) {
                $errors[] = 'SkyView → '.$exception->getMessage();
            }

            throw new RuntimeException('Aperçu du ciel indisponible. '.implode(' | ', $errors));
        });
    }

    /**
     * Secours NASA SkyView : plaques DSS2 bleue et rouge du même champ,
     * recomposées en couleur (repli sur la plaque rouge en niveaux de gris).
     */
    private function fetchFromSkyView(float $ra, float $dec, float $fov): string
    {
        $width = (int) config('nightscope.preview.width', 640);
        $height = (int) config('nightscope.preview.height', 360);
        $query = http_build_query([
            'Position' => round($ra, 6).','.round($dec, 6),
            'Size' => round($fov, 4).','.round($fov * $height / $width, 4),
            'Pixels' => $width.','.$height,
            'Return' => 'JPEG',
            'Scaling' => 'Log',
        ], '', '&', PHP_QUERY_RFC3986);
        $endpoint = (string) config('nightscope.preview.skyview_endpoint', 'https://skyview.gsfc.nasa.gov/current/cgi/runquery.pl');

        $plates = Http::pool(fn (Pool $pool): array => [
            $this->configure($pool->as('red'))->get($endpoint.'?Survey=DSS2%20Red&'.$query),
            $this->configure($pool->as('blue'))->get($endpoint.'?Survey=DSS2%20Blue&'.$query),
        ]);
        $red = $this->imageBody($plates['red']);

        try {
            return $this->composer->compose($red, $this->imageBody($plates['blue']), $width, $height);
        } catch (Throwable) {
            // Plaque bleue manquante : la rouge seule reste un bon aperçu du champ.
            if ((@getimagesizefromstring($red)[0] ?? null) !== $width) {
                throw new RuntimeException('aucune donnée DSS2 pour ce champ');
            }

            return $red;
        }
    }

    private function download(string $url): string
    {
        return $this->imageBody($this->configure(Http::createPendingRequest())->get($url));
    }

    private function configure(PendingRequest $request): PendingRequest
    {
        return $request
            ->timeout((int) config('nightscope.preview.timeout', 12))
            ->connectTimeout((int) config('nightscope.preview.connect_timeout', 4))
            ->withHeaders([
                'Accept' => 'image/jpeg,image/*;q=0.8,*/*;q=0.5',
                'User-Agent' => (string) config('nightscope.preview.user_agent', 'DeepskyNightScope/1.0'),
            ]);
    }

    private function imageBody(mixed $response): string
    {
        if ($response instanceof Throwable) {
            throw $response;
        }

        if (! $response instanceof Response || ! $response->successful()) {
            throw new RuntimeException('HTTP '.($response instanceof Response ? $response->status() : '?'));
        }

        $body = $response->body();
        $type = strtolower((string) $response->header('Content-Type'));

        if ($body === '' || ($type !== '' && ! str_contains($type, 'image') && ! str_contains($type, 'octet-stream'))) {
            throw new RuntimeException('contenu invalide');
        }

        return $body;
    }

    private function queryString(float $ra, float $dec, float $fov): string
    {
        $params = [
            'width' => (int) config('nightscope.preview.width', 640),
            'height' => (int) config('nightscope.preview.height', 360),
            'projection' => 'TAN',
            'fov' => round($fov, 3),
            'ra' => $ra,
            'dec' => $dec,
            'coordsys' => 'icrs',
            'format' => 'jpg',
            'stretch' => 'asinh',
        ];

        // Laisser les / du survey HiPS non encodés (CDS est sensible à hips=CDS%2FP%2F...).
        return 'hips='.config('nightscope.preview.survey').'&'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
