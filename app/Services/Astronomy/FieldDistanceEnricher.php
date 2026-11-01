<?php

namespace App\Services\Astronomy;

use App\Models\Astrophoto;

/**
 * Ajoute la distance (années-lumière) de chaque objet annoté dans le champ d'une
 * photo résolue, en une seule requête SIMBAD pour toute la photo.
 */
class FieldDistanceEnricher
{
    public function __construct(private CelestialDistanceResolver $resolver) {}

    /** Vrai si SIMBAD n'a pas répondu lors du dernier enrichissement. */
    public function simbadWasUnreachable(): bool
    {
        return $this->resolver->wasUnreachable();
    }

    public function needsEnrichment(Astrophoto $photo): bool
    {
        return collect($photo->annotations ?? [])->contains(fn (array $annotation): bool => ! array_key_exists('distance_ly', $annotation));
    }

    /**
     * Une distance déjà connue est conservée si SIMBAD ne renvoie rien (panne, objet absent).
     *
     * @return int nombre d'objets dont la distance est connue
     */
    public function enrich(Astrophoto $photo, bool $refresh = false): int
    {
        $annotations = $photo->annotations ?? [];

        if ($annotations === []) {
            return 0;
        }

        $identifiers = array_map(fn (array $annotation): array => $this->identifiers((string) ($annotation['name'] ?? '')), $annotations);
        $distances = $this->resolver->lookupMany(array_merge(...$identifiers), $refresh);

        foreach ($annotations as $index => $annotation) {
            $annotations[$index]['distance_ly'] = collect($identifiers[$index])
                ->map(fn (string $identifier): ?float => $distances[$identifier] ?? null)
                ->first(fn (?float $distance): bool => $distance !== null) ?? ($annotation['distance_ly'] ?? null);
        }

        $photo->forceFill(['annotations' => $annotations])->saveQuietly();

        return collect($annotations)->whereNotNull('distance_ly')->count();
    }

    /**
     * Astrometry.net regroupe les noms d'un objet : « NGC 7000 / North America Nebula ».
     *
     * @return list<string>
     */
    private function identifiers(string $name): array
    {
        return array_values(array_filter(array_map('trim', explode('/', $name))));
    }
}
