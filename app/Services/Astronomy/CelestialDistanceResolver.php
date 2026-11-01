<?php

namespace App\Services\Astronomy;

use App\Models\CelestialObject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CelestialDistanceResolver
{
    private const PC_TO_LY = 3.26156;

    private const string PRIMARY_DOWN_CACHE_KEY = 'nightscope:simbad:primary-down';

    private bool $unreachable = false;

    public function __construct(
        private ?string $endpoint = null,
    ) {
        $this->endpoint ??= (string) config(
            'nightscope.distances.simbad_tap_url',
            'https://simbad.cds.unistra.fr/simbad/sim-tap/sync',
        );
    }

    public function resolveFor(CelestialObject $object, bool $refresh = false): ?float
    {
        $distances = $this->lookupMany($this->identifiersFor($object), $refresh);

        foreach ($this->identifiersFor($object) as $identifier) {
            if ($distances[$identifier] !== null) {
                return $distances[$identifier];
            }
        }

        return null;
    }

    public function fillMissing(CelestialObject $object, bool $force = false): ?float
    {
        if (! $force && $object->distance_ly !== null) {
            return $object->distance_ly;
        }

        $distance = $this->resolveFor($object, refresh: $force);

        $object->update([
            'distance_ly' => $distance ?? $object->distance_ly,
            'distance_checked_at' => now(),
        ]);

        return $object->distance_ly;
    }

    public function lookupLightYears(string $identifier): ?float
    {
        $identifier = Str::squish($identifier);

        return $identifier === '' ? null : $this->lookupMany([$identifier])[$identifier];
    }

    /**
     * Distances médianes (al) de plusieurs identifiants en une seule requête
     * SIMBAD au lieu d'une par identifiant ; résultats (même vides) mis en cache.
     * Avec $refresh, le cache est ignoré et réécrit si SIMBAD répond.
     *
     * @param  list<string>  $identifiers
     * @return array<string, ?float>
     */
    public function lookupMany(array $identifiers, bool $refresh = false): array
    {
        $this->unreachable = false;
        $identifiers = array_values(array_unique(array_filter(array_map(fn (string $value): string => Str::squish($value), $identifiers))));
        $results = [];
        $missing = [];

        foreach ($identifiers as $identifier) {
            // Enveloppe tableau : une distance inconnue (null) est aussi mise en cache.
            $cached = $refresh ? null : Cache::get($this->cacheKey($identifier));
            match (true) {
                is_array($cached) => $results[$identifier] = $cached['light_years'],
                is_numeric($cached) => $results[$identifier] = (float) $cached,
                default => $missing[] = $identifier,
            };
        }

        foreach (array_chunk($missing, 20) as $chunk) {
            $fetched = $this->fetchMedianLightYears($chunk);

            foreach ($chunk as $identifier) {
                $results[$identifier] = $fetched[$identifier] ?? null;

                if ($fetched !== null) {
                    // Une absence de mesure est retentée dès le lendemain, une distance trouvée est gardée longtemps.
                    Cache::put($this->cacheKey($identifier), ['light_years' => $results[$identifier]], $results[$identifier] === null
                        ? now()->addDay()
                        : now()->addDays((int) config('nightscope.distances.cache_days', 30)));
                }
            }
        }

        return array_replace(array_fill_keys($identifiers, null), $results);
    }

    /**
     * Écritures possibles d'un identifiant dans la table ident de SIMBAD, qui aligne
     * les numéros par des espaces (« M  31 », « NGC  224 ») et préfixe les noms
     * d'usage (« NAME Deneb ») et les étoiles (« * alf Cyg »).
     *
     * @return list<string>
     */
    public static function simbadVariants(string $identifier): array
    {
        $identifier = Str::squish($identifier);
        $variants = [$identifier];

        if (preg_match('/^(M|NGC|IC)\s*0*(\d+)\s*([A-Z]?)$/i', $identifier, $match)) {
            $catalog = strtoupper($match[1]);
            $number = $match[2].strtoupper($match[3]);
            $width = $catalog === 'M' ? 3 : 4;
            $variants[] = "{$catalog} {$number}";
            $variants[] = $catalog.' '.str_pad($number, $width, ' ', STR_PAD_LEFT);
        } elseif (preg_match('/^([\x{03B1}-\x{03C9}]|\d{1,3})\s+([A-Za-z]{3})$/u', $identifier, $match)) {
            $letter = self::GREEK_LETTERS[$match[1]] ?? $match[1];
            $variants[] = "* {$letter} ".ucfirst(strtolower($match[2]));
        } elseif (preg_match('/^[A-Za-z][A-Za-z\s\'-]+$/', $identifier)) {
            $variants[] = "NAME {$identifier}";
        }

        return array_values(array_unique($variants));
    }

    private const array GREEK_LETTERS = [
        'α' => 'alf', 'β' => 'bet', 'γ' => 'gam', 'δ' => 'del', 'ε' => 'eps', 'ζ' => 'zet', 'η' => 'eta', 'θ' => 'tet',
        'ι' => 'iot', 'κ' => 'kap', 'λ' => 'lam', 'μ' => 'mu.', 'ν' => 'nu.', 'ξ' => 'ksi', 'ο' => 'omi', 'π' => 'pi.',
        'ρ' => 'rho', 'σ' => 'sig', 'τ' => 'tau', 'υ' => 'ups', 'φ' => 'phi', 'χ' => 'chi', 'ψ' => 'psi', 'ω' => 'ome',
    ];

    private function cacheKey(string $identifier): string
    {
        return 'simbad:distance-ly:'.Str::lower($identifier);
    }

    /** @return list<string> */
    private function identifiersFor(CelestialObject $object): array
    {
        $identifiers = [
            $object->designation(),
            $object->catalog.$object->catalog_number,
            $object->name,
        ];

        foreach ($object->alternative_names ?? [] as $alias) {
            $identifiers[] = is_string($alias) ? $alias : null;
        }

        return array_values(array_unique(array_filter(
            array_map(fn (?string $value): string => Str::squish((string) $value), $identifiers),
            fn (string $value): bool => $value !== '',
        )));
    }

    /**
     * Médiane des distances publiées (mesDistance) ; à défaut, distance déduite
     * de la parallaxe de l'objet quand elle est fiable (erreur < 20 %).
     *
     * @param  list<string>  $identifiers
     * @return array<string, ?float>|null null si SIMBAD est injoignable (rien n'est mis en cache)
     */
    private function fetchMedianLightYears(array $identifiers): ?array
    {
        $variantOwners = [];
        foreach ($identifiers as $identifier) {
            foreach (self::simbadVariants($identifier) as $variant) {
                $variantOwners[Str::lower(Str::squish($variant))] ??= $identifier;
            }
        }

        $rows = $this->query('SELECT i.id, m.dist, m.unit FROM mesDistance AS m JOIN ident AS i ON i.oidref = m.oidref WHERE i.id IN ('.$this->variantList($identifiers).')');

        if ($rows === null) {
            return null;
        }

        $distancesByIdentifier = [];
        foreach ($rows as $row) {
            $owner = $this->ownerOf($row, $variantOwners, $identifiers);
            $lightYears = $this->toLightYears($row['dist'] ?? null, $row['unit'] ?? null);

            if ($owner !== null && $lightYears !== null) {
                $distancesByIdentifier[$owner][] = $lightYears;
            }
        }

        $results = array_map(function (array $distances): float {
            sort($distances);

            return round($distances[(int) floor((count($distances) - 1) / 2)], 3);
        }, $distancesByIdentifier);

        $withoutMeasurement = array_values(array_diff($identifiers, array_keys($results)));

        if ($withoutMeasurement !== []) {
            $parallaxRows = $this->query('SELECT i.id, b.plx_value, b.plx_err FROM basic AS b JOIN ident AS i ON i.oidref = b.oid WHERE b.plx_value > 0 AND i.id IN ('.$this->variantList($withoutMeasurement).')') ?? [];

            foreach ($parallaxRows as $row) {
                $owner = $this->ownerOf($row, $variantOwners, $withoutMeasurement);
                $parallax = is_numeric($row['plx_value'] ?? null) ? (float) $row['plx_value'] : 0.0;
                $error = is_numeric($row['plx_err'] ?? null) ? (float) $row['plx_err'] : null;

                if ($owner !== null && ! isset($results[$owner]) && $parallax > 0 && ($error === null || $error / $parallax < .2)) {
                    $results[$owner] = round(1000 / $parallax * self::PC_TO_LY, 3);
                }
            }
        }

        return $results;
    }

    /** @param  list<string>  $identifiers */
    private function variantList(array $identifiers): string
    {
        return collect($identifiers)
            ->flatMap(fn (string $identifier): array => self::simbadVariants($identifier))
            ->unique()
            ->map(fn (string $variant): string => "'".str_replace("'", "''", $variant)."'")
            ->implode(', ');
    }

    /**
     * @param  array<string, ?string>  $row
     * @param  array<string, string>  $variantOwners
     * @param  list<string>  $identifiers
     */
    private function ownerOf(array $row, array $variantOwners, array $identifiers): ?string
    {
        return isset($row['id'])
            ? ($variantOwners[Str::lower(Str::squish((string) $row['id']))] ?? null)
            : (count($identifiers) === 1 ? $identifiers[0] : null);
    }

    /**
     * Requête ADQL sur SIMBAD (Strasbourg), puis sur ses miroirs si le serveur
     * principal ne répond pas.
     *
     * @return list<array<string, ?string>>|null null si aucun serveur ne répond
     */
    private function query(string $adql): ?array
    {
        $mirrors = (array) config('nightscope.distances.simbad_tap_mirrors', []);
        // Après une panne du serveur principal, on interroge directement les miroirs pendant 10 min.
        $primaryDown = $mirrors !== [] && Cache::has(self::PRIMARY_DOWN_CACHE_KEY);
        $endpoints = array_values(array_unique(array_filter([
            $primaryDown ? null : $this->endpoint,
            ...$mirrors,
        ])));

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::connectTimeout((int) config('nightscope.distances.connect_timeout', 5))
                    ->timeout((int) config('nightscope.distances.timeout', 20))
                    ->retry(2, 250, throw: false)
                    ->get($endpoint, [
                        'LANG' => 'ADQL',
                        'REQUEST' => 'doQuery',
                        'FORMAT' => 'csv',
                        'QUERY' => $adql,
                    ]);
            } catch (\Throwable) {
                $this->markPrimaryDown($endpoint, $mirrors);

                continue;
            }

            if ($response->failed()) {
                $this->markPrimaryDown($endpoint, $mirrors);

                continue;
            }

            $this->unreachable = false;
            $lines = preg_split("/\r\n|\n|\r/", trim($response->body())) ?: [];
            $header = array_map(fn (?string $column): string => Str::lower(trim((string) $column)), str_getcsv((string) array_shift($lines), escape: ''));

            return array_values(array_map(
                fn (string $line): array => array_combine($header, array_pad(str_getcsv($line, escape: ''), count($header), null)),
                array_filter($lines, fn (string $line): bool => $line !== '' && str_contains($line, ',')),
            ));
        }

        $this->unreachable = true;

        return null;
    }

    /** @param  array<int, string>  $mirrors */
    private function markPrimaryDown(string $endpoint, array $mirrors): void
    {
        if ($endpoint === $this->endpoint && $mirrors !== []) {
            Cache::put(self::PRIMARY_DOWN_CACHE_KEY, true, now()->addMinutes(10));
        }
    }

    /** Vrai si la dernière requête n'a obtenu de réponse d'aucun serveur SIMBAD. */
    public function wasUnreachable(): bool
    {
        return $this->unreachable;
    }

    private function toLightYears(mixed $distance, mixed $unit): ?float
    {
        if (! is_numeric($distance)) {
            return null;
        }

        $value = (float) $distance;
        $normalized = Str::lower(trim((string) $unit));

        $parsecs = match (true) {
            $normalized === '' || $normalized === 'pc' => $value,
            $normalized === 'kpc' => $value * 1_000,
            $normalized === 'mpc' => $value * 1_000_000,
            default => null,
        };

        return $parsecs === null ? null : $parsecs * self::PC_TO_LY;
    }
}
