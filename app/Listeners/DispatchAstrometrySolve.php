<?php

namespace App\Listeners;

use App\Enums\AstrometryStatus;
use App\Jobs\SolveAstrophotoJob;
use App\Models\Astrophoto;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class DispatchAstrometrySolve
{
    /**
     * Lance automatiquement le plate-solving quand une photo est ajoutée
     * (nouvelle astrophoto ou remplacement de l'image existante).
     */
    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $model = $event->media->model;

        if (! $model instanceof Astrophoto || $event->media->collection_name !== 'astros') {
            return;
        }

        if (blank(config('services.astrometry.key'))) {
            return;
        }

        $model->update([
            'astrometry_status' => AstrometryStatus::Pending,
            'astrometry_submission_id' => null,
        ]);

        SolveAstrophotoJob::dispatch($model);
    }
}
