<?php

namespace App\Console\Commands;

use App\Enums\AstrometryStatus;
use App\Models\Astrophoto;
use App\Services\Astronomy\FieldDistanceEnricher;
use Illuminate\Console\Command;

class EnrichFieldDistances extends Command
{
    protected $signature = 'astrophotos:enrich-field-distances {--force : Recalcule aussi les photos déjà enrichies}';

    protected $description = 'Récupère sur SIMBAD la distance des objets annotés dans le champ des photos résolues.';

    public function handle(FieldDistanceEnricher $enricher): int
    {
        $photos = 0;
        $known = 0;

        Astrophoto::query()->where('astrometry_status', AstrometryStatus::Solved)->whereNotNull('annotations')
            ->lazyById()
            ->filter(fn (Astrophoto $photo): bool => $this->option('force') || $enricher->needsEnrichment($photo))
            ->each(function (Astrophoto $photo) use ($enricher, &$photos, &$known): void {
                $known += $enricher->enrich($photo);
                $photos++;
            });

        $this->components->info("{$photos} photo(s) traitée(s), {$known} distance(s) d'objets du champ connue(s).");

        return self::SUCCESS;
    }
}
