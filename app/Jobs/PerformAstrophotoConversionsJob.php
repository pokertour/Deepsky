<?php

namespace App\Jobs;

use App\Enums\ConversionStatus;
use App\Models\Astrophoto;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Throwable;

class PerformAstrophotoConversionsJob extends PerformConversionsJob
{
    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    /**
     * Les masters TIFF peuvent prendre plusieurs minutes avec Imagick. Doit rester
     * inférieur au retry_after de la connexion de file (config/queue.php).
     */
    public int $timeout = 900;

    /**
     * Un dépassement sur le même master se reproduirait à chaque tentative :
     * on échoue tout de suite pour que l'admin voie l'état « failed ».
     */
    public bool $failOnTimeout = true;

    public function middleware(): array
    {
        return [(new WithoutOverlapping('media-conversions-'.$this->media->id))->releaseAfter(30)->expireAfter(1800)];
    }

    public function handle(FileManipulator $fileManipulator): bool
    {
        $this->setStatus(ConversionStatus::Converting);
        $result = parent::handle($fileManipulator);
        $this->media->refresh();

        if ($this->media->model instanceof Astrophoto && ! collect(['thumb', 'optimized', 'full_res'])
            ->every(fn (string $name) => $this->media->hasGeneratedConversion($name))) {
            throw new RuntimeException('Les conversions attendues ne sont pas disponibles.');
        }

        $this->setStatus(ConversionStatus::Ready);

        return $result;
    }

    public function failed(?Throwable $exception): void
    {
        $this->setStatus(ConversionStatus::Failed);
    }

    private function setStatus(ConversionStatus $status): void
    {
        if ($this->media->exists && $this->media->model instanceof Astrophoto) {
            $this->media->setCustomProperty('processing_status', $status->value);
            if ($status === ConversionStatus::Converting) {
                $this->media->setCustomProperty('processing_started_at', now()->toIso8601String());
            }
            $this->media->save();
        }
    }
}
