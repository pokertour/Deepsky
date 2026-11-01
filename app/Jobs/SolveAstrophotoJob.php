<?php

namespace App\Jobs;

use App\Enums\AstrometryStatus;
use App\Models\Astrophoto;
use App\Services\AstrometryService;
use App\Services\SimbadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Image as ImageGenerator;
use Throwable;

class SolveAstrophotoJob implements ShouldQueue
{
    use Queueable;

    /**
     * Le plate-solving prend plusieurs minutes côté Astrometry.net :
     * le job se re-programme via release() jusqu'à résolution.
     */
    public int $tries = 40;

    public int $backoff = 30;

    public function __construct(public Astrophoto $astrophoto) {}

    public function handle(AstrometryService $astrometry): void
    {
        $photo = $this->astrophoto;

        // Étape 1 : soumettre l'image si pas encore fait
        if ($photo->astrometry_submission_id === null) {
            $media = $photo->getFirstMedia('astros');

            if ($media === null) {
                Log::warning("Astrometry [photo {$photo->id}] : aucun média, abandon.");
                $photo->update(['astrometry_status' => AstrometryStatus::Failed]);

                return;
            }

            // La conversion 'optimized' (webp léger) est générée en queue :
            // on attend qu'elle existe plutôt que d'envoyer l'original (trop lourd).
            if (! $media->hasGeneratedConversion('optimized')) {
                // Si aucun générateur d'images ne supporte ce format (ex: TIFF avec
                // le driver GD), la conversion n'arrivera jamais : inutile d'attendre.
                if (! (new ImageGenerator)->canConvert($media)) {
                    Log::error("Astrometry [photo {$photo->id}] : format '{$media->mime_type}' non convertible avec le driver d'image actuel (".config('media-library.image_driver').'), abandon.');
                    $photo->update(['astrometry_status' => AstrometryStatus::Failed]);

                    return;
                }

                Log::info("Astrometry [photo {$photo->id}] : conversion 'optimized' pas encore prête, nouvel essai dans {$this->backoff}s.");
                $this->release($this->backoff);

                return;
            }

            // Astrometry.net ne supporte pas le WebP : on convertit en JPEG avant l'envoi.
            $contents = $this->convertToJpeg(
                Storage::disk($media->conversions_disk ?: $media->disk)
                    ->get($media->getPathRelativeToRoot('optimized'))
            );

            $session = $astrometry->login();
            $submissionId = $astrometry->submitFile($session, $contents, $photo->id.'-optimized.jpg');

            Log::info("Astrometry [photo {$photo->id}] : image envoyée (upload direct), submission #{$submissionId}.");

            $photo->update([
                'astrometry_submission_id' => $submissionId,
                'astrometry_status' => AstrometryStatus::Solving,
            ]);

            $this->release($this->backoff);

            return;
        }

        // Étape 2 : attendre qu'un job soit créé pour la soumission
        $submission = $astrometry->getSubmission($photo->astrometry_submission_id);

        if ($submission['job_id'] === null) {
            if (filled($submission['error'])) {
                Log::error("Astrometry [photo {$photo->id}] : la soumission #{$photo->astrometry_submission_id} a échoué côté serveur : {$submission['error']}");
                $photo->update(['astrometry_status' => AstrometryStatus::Failed]);

                return;
            }

            Log::info("Astrometry [photo {$photo->id}] : soumission #{$photo->astrometry_submission_id} en file côté serveur, nouvel essai dans {$this->backoff}s.");
            $this->release($this->backoff);

            return;
        }

        // Étape 3 : vérifier le statut du job
        $status = $astrometry->getJobStatus($submission['job_id']);

        Log::info("Astrometry [photo {$photo->id}] : job #{$submission['job_id']} statut '{$status}' (tentative {$this->attempts()}/{$this->tries}).");

        if ($status === 'failure') {
            $photo->update(['astrometry_status' => AstrometryStatus::Failed]);

            return;
        }

        if ($status !== 'success') {
            $this->release($this->backoff);

            return;
        }

        // Étape 4 : récupérer la calibration et les objets du champ
        $result = $astrometry->getJobResult($submission['job_id']);

        Log::info("Astrometry [photo {$photo->id}] : résolu ! RA={$result['ra']} Dec={$result['dec']} objets=".implode(', ', $result['objects_in_field']));

        $this->storeAnnotations($photo, $astrometry, $submission['job_id']);

        $photo->update([
            ...$result,
            'astrometry_status' => AstrometryStatus::Solved,
            'astrometry_solved_at' => now(),
            'tags' => $this->mergeTagsFromObjects($photo, $result['objects_in_field']),
        ]);

        EnrichFieldDistancesJob::dispatch($photo);
    }

    /**
     * Récupère les positions pixel des objets identifiés pour l'overlay
     * d'annotations. Non bloquant : un échec ne remet pas en cause la résolution.
     */
    private function storeAnnotations(Astrophoto $photo, AstrometryService $astrometry, int $jobId): void
    {
        try {
            $annotations = $astrometry->getAnnotations($jobId);

            $photo->update(['annotations' => $annotations]);

            Log::info("Astrometry [photo {$photo->id}] : ".count($annotations).' annotations stockées.');
        } catch (Throwable $exception) {
            Log::warning("Astrometry [photo {$photo->id}] : échec de la récupération des annotations : {$exception->getMessage()}");
        }
    }

    private function convertToJpeg(string $webpContents): string
    {
        $source = tempnam(sys_get_temp_dir(), 'astro').'.webp';
        $target = tempnam(sys_get_temp_dir(), 'astro').'.jpg';

        try {
            file_put_contents($source, $webpContents);

            Image::load($source)->quality(90)->save($target);

            return file_get_contents($target);
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    /**
     * Déduit des tags (galaxy, nebula...) en résolvant le type SIMBAD
     * des objets détectés dans le champ, et les fusionne aux tags existants.
     *
     * @param  list<string>  $objects
     * @return list<string>
     */
    private function mergeTagsFromObjects(Astrophoto $photo, array $objects): array
    {
        $detectedTags = collect($objects)
            ->take(10)
            ->map(fn (string $object): ?array => SimbadService::lookup($object))
            ->filter()
            ->map(fn (array $data): ?string => SimbadService::tagForType($data['type']))
            ->filter();

        return collect($photo->tags ?? [])
            ->merge($detectedTags)
            ->unique()
            ->values()
            ->all();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error("Astrometry [photo {$this->astrophoto->id}] : job abandonné : ".($exception?->getMessage() ?? 'raison inconnue'));

        $this->astrophoto->update(['astrometry_status' => AstrometryStatus::Failed]);
    }
}
