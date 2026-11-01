<?php

namespace App\Console\Commands;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Services\Astronomy\CelestialDistanceResolver;
use App\Services\Astronomy\FieldDistanceEnricher;
use Illuminate\Console\Command;

class ResyncAstrophotoDistances extends Command
{
    protected $signature = 'astrophotos:resync-distances';

    protected $description = 'Réinterroge SIMBAD (sans cache) pour l’objet principal et les objets du champ de toutes les photos.';

    public function handle(CelestialDistanceResolver $distances, FieldDistanceEnricher $fieldDistances): int
    {
        $objects = CelestialObject::query()->whereHas('astrophotos')->get();
        $objectsWithDistance = 0;

        foreach ($objects as $object) {
            if ($distances->fillMissing($object, force: true) !== null) {
                $objectsWithDistance++;
            }

            if ($distances->wasUnreachable()) {
                $this->components->error('SIMBAD ne répond pas (Strasbourg et miroir) : synchronisation interrompue, distances connues conservées.');

                return self::FAILURE;
            }
        }

        $photos = 0;
        $fieldObjects = 0;
        $fieldObjectsWithDistance = 0;

        Astrophoto::query()->whereNotNull('annotations')->lazyById()->each(function (Astrophoto $photo) use ($fieldDistances, &$photos, &$fieldObjects, &$fieldObjectsWithDistance): void {
            $fieldObjectsWithDistance += $fieldDistances->enrich($photo, refresh: true);
            $fieldObjects += count($photo->annotations ?? []);
            $photos++;
        });

        $this->components->info("Objets principaux : {$objectsWithDistance}/{$objects->count()} avec distance. Champ : {$fieldObjectsWithDistance}/{$fieldObjects} objet(s) avec distance sur {$photos} photo(s).");

        return self::SUCCESS;
    }
}
