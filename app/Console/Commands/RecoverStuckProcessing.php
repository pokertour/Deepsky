<?php

namespace App\Console\Commands;

use App\Actions\Gallery\QueueAstrophotoConversionsAction;
use App\Enums\AstrometryStatus;
use App\Enums\ConversionStatus;
use App\Models\Astrophoto;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Rattrape les traitements orphelins, typiquement après un redémarrage de Redis
 * ou du conteneur : conversions restées « en attente » sans job, astrométrie
 * bloquée, et répertoires temporaires laissés par une conversion interrompue.
 */
class RecoverStuckProcessing extends Command
{
    protected $signature = 'maintenance:recover-processing
        {--queued-hours=6 : Ancienneté au-delà de laquelle une conversion en attente est relancée}
        {--astrometry-hours=12 : Ancienneté au-delà de laquelle une astrométrie en cours passe en échec}
        {--temp-hours=24 : Ancienneté des répertoires temporaires de conversion à supprimer}';

    protected $description = 'Relance les conversions orphelines, débloque l\'astrométrie et nettoie les fichiers temporaires.';

    public function handle(QueueAstrophotoConversionsAction $queueConversions): int
    {
        $this->requeueOrphanedConversions($queueConversions);
        $this->failStuckAstrometry();
        $this->cleanTemporaryDirectories();

        return self::SUCCESS;
    }

    private function requeueOrphanedConversions(QueueAstrophotoConversionsAction $queueConversions): void
    {
        $photoIds = Media::query()
            ->where('model_type', (new Astrophoto)->getMorphClass())
            ->whereIn('collection_name', ['astros', 'starless'])
            ->where('custom_properties->processing_status', ConversionStatus::Queued->value)
            ->where('updated_at', '<', now()->subHours((int) $this->option('queued-hours')))
            ->distinct()
            ->pluck('model_id');

        $requeued = 0;
        foreach (Astrophoto::query()->with('media')->whereIn('id', $photoIds)->get() as $photo) {
            try {
                $queueConversions->execute($photo);
                $requeued++;
            } catch (DomainException $exception) {
                $this->components->warn("Photo #{$photo->id} : {$exception->getMessage()}");
            }
        }

        $this->components->info("{$requeued} conversion(s) orpheline(s) relancée(s).");
    }

    private function failStuckAstrometry(): void
    {
        $failed = Astrophoto::query()
            ->whereIn('astrometry_status', [AstrometryStatus::Pending, AstrometryStatus::Solving])
            ->where('updated_at', '<', now()->subHours((int) $this->option('astrometry-hours')))
            ->update(['astrometry_status' => AstrometryStatus::Failed]);

        $this->components->info("{$failed} astrométrie(s) bloquée(s) passée(s) en échec (relançables depuis l'admin).");
    }

    private function cleanTemporaryDirectories(): void
    {
        $root = config('media-library.temporary_directory_path') ?? storage_path('media-library/temp');
        $cutoff = now()->subHours((int) $this->option('temp-hours'))->getTimestamp();
        $removed = 0;

        foreach (File::isDirectory($root) ? File::directories($root) : [] as $directory) {
            if (File::lastModified($directory) < $cutoff && File::deleteDirectory($directory)) {
                $removed++;
            }
        }

        $this->components->info("{$removed} répertoire(s) temporaire(s) supprimé(s).");
    }
}
