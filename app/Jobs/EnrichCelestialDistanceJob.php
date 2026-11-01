<?php

namespace App\Jobs;

use App\Models\CelestialObject;
use App\Services\Astronomy\CelestialDistanceResolver;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Récupère la distance d'un objet dès qu'une photo y est liée, sans attendre
 * le passage quotidien de nightscope:enrich-distances.
 */
class EnrichCelestialDistanceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $uniqueFor = 3600;

    public function __construct(public CelestialObject $celestialObject) {}

    public function uniqueId(): string
    {
        return (string) $this->celestialObject->id;
    }

    public function handle(CelestialDistanceResolver $resolver): void
    {
        $resolver->fillMissing($this->celestialObject);
    }
}
