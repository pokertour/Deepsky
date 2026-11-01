<?php

namespace App\Console\Commands;

use App\Enums\CelestialObjectType;
use App\Models\CelestialObject;
use App\Services\Astronomy\CelestialDistanceResolver;
use Illuminate\Console\Command;

class EnrichCelestialDistances extends Command
{
    protected $signature = 'nightscope:enrich-distances
                            {--limit=40 : Maximum objects to process}
                            {--force : Refresh distances even when already set}
                            {--types=* : Restrict to object types (galaxy, nebula, cluster)}';

    protected $description = 'Fetch missing celestial object distances from SIMBAD TAP';

    public function handle(CelestialDistanceResolver $resolver): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $types = array_values(array_intersect(
            array_map('strval', $this->option('types') ?: []),
            CelestialObjectType::values(),
        ));

        $query = CelestialObject::query()
            ->withExists('astrophotos')
            ->orderByDesc('astrophotos_exists')
            ->orderByRaw('magnitude IS NULL')
            ->orderBy('magnitude')
            ->limit($limit);

        // Sans ce filtre, les objets absents de SIMBAD restent en tête du tri
        // et le même lot est retraité à chaque passage.
        if (! $force) {
            $query->whereNull('distance_ly')
                ->where(fn ($checked) => $checked->whereNull('distance_checked_at')
                    ->orWhere('distance_checked_at', '<', now()->subDays((int) config('nightscope.distances.recheck_days', 30))));
        }

        if ($types !== []) {
            $query->whereIn('type', $types);
        } else {
            $query->whereIn('type', CelestialObjectType::values());
        }

        $objects = $query->get();
        $filled = 0;
        $missing = 0;

        foreach ($objects as $object) {
            $distance = $resolver->fillMissing($object, $force);

            if ($distance === null) {
                $missing++;
                $this->line("· {$object->designation()} — aucune distance SIMBAD");

                continue;
            }

            $filled++;
            $this->info("✓ {$object->designation()} — ".number_format($distance, 0, ',', ' ').' al');
        }

        $this->newLine();
        $this->info("Enrichies : {$filled} · introuvables : {$missing} · traitées : {$objects->count()}");

        return self::SUCCESS;
    }
}
