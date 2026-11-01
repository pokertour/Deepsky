<?php

namespace App\Jobs;

use App\Models\Astrophoto;
use App\Services\Astronomy\FieldDistanceEnricher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Distances des objets du champ, lancé dès la résolution astrométrique.
 */
class EnrichFieldDistancesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $uniqueFor = 3600;

    public function __construct(public Astrophoto $astrophoto) {}

    public function uniqueId(): string
    {
        return (string) $this->astrophoto->id;
    }

    public function handle(FieldDistanceEnricher $enricher): void
    {
        $enricher->enrich($this->astrophoto);
    }
}
