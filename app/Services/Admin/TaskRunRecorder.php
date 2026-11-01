<?php

namespace App\Services\Admin;

use App\Enums\TaskRunStatus;
use App\Enums\TaskRunType;
use App\Jobs\RunScheduledCommandJob;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\TaskRun;
use App\Services\Notifications\TaskFailureNotifier;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Alimente l'historique admin > Tâches. Enregistré en singleton : les débuts
 * de commande et de job sont gardés en mémoire jusqu'à leur fin dans le même
 * processus, puis retirés. Une erreur d'écriture ne doit jamais faire échouer
 * la tâche suivie.
 */
class TaskRunRecorder
{
    /** @var array<string, array{id: int, started: int}> */
    private array $running = [];

    /**
     * Posé par RunScheduledCommandJob : la commande qu'il lance est marquée
     * comme manuelle au lieu d'ajouter une seconde ligne pour le job.
     */
    private bool $manualLaunchPending = false;

    public function commandStarting(CommandStarting $event): void
    {
        $isManualLaunch = $this->manualLaunchPending;
        $this->manualLaunchPending = false;

        if (! $this->tracksCommand($event->command)) {
            return;
        }

        $this->safely(function () use ($event, $isManualLaunch): void {
            $run = TaskRun::create([
                'type' => TaskRunType::Command,
                'name' => (string) $event->command,
                'status' => TaskRunStatus::Running,
                'subject' => collect([$isManualLaunch ? __('Lancé manuellement') : null, $this->commandArguments($event)])
                    ->filter()->implode(' · ') ?: null,
                'started_at' => now(),
            ]);

            $this->running['command:'.$event->command] = ['id' => $run->id, 'started' => hrtime(true)];
        });
    }

    public function commandFinished(CommandFinished $event): void
    {
        $tracked = $this->pull('command:'.$event->command);

        if ($tracked === null) {
            return;
        }

        $this->safely(function () use ($event, $tracked): void {
            $run = TaskRun::find($tracked['id']);
            $run?->update([
                'status' => $event->exitCode === 0 ? TaskRunStatus::Succeeded : TaskRunStatus::Failed,
                'message' => $event->exitCode === 0 ? null : "Code de sortie {$event->exitCode}",
                'finished_at' => now(),
                'duration_ms' => $this->elapsedMilliseconds($tracked['started']),
            ]);

            $this->alertOnFailure($run);
        });
    }

    public function jobProcessing(JobProcessing $event): void
    {
        if ($event->job->resolveName() === RunScheduledCommandJob::class) {
            $this->manualLaunchPending = true;

            return;
        }

        $this->safely(function () use ($event): void {
            $uuid = $event->job->uuid();
            $run = $uuid ? TaskRun::firstWhere('job_uuid', $uuid) : null;

            if ($run === null) {
                $run = TaskRun::create([
                    'type' => TaskRunType::Job,
                    'name' => $event->job->resolveName(),
                    'status' => TaskRunStatus::Running,
                    'queue' => $event->job->getQueue(),
                    'job_uuid' => $uuid,
                    'attempts' => $event->job->attempts(),
                    'subject' => $this->jobSubject($event->job),
                    'started_at' => now(),
                ]);
            } else {
                $run->update([
                    'status' => TaskRunStatus::Running,
                    'attempts' => $event->job->attempts(),
                    'finished_at' => null,
                ]);
            }

            $this->running['job:'.$this->jobKey($event->job)] = ['id' => $run->id, 'started' => hrtime(true)];
        });
    }

    public function jobProcessed(JobProcessed $event): void
    {
        $this->finishJob($event->job, $event->job->isReleased() ? TaskRunStatus::Released : TaskRunStatus::Succeeded);
    }

    public function jobReleasedAfterException(JobReleasedAfterException $event): void
    {
        $this->finishJob($event->job, TaskRunStatus::Released, $event->exception);
    }

    public function jobFailed(JobFailed $event): void
    {
        $this->finishJob($event->job, TaskRunStatus::Failed, $event->exception);
    }

    private function finishJob(Job $job, TaskRunStatus $status, ?Throwable $exception = null): void
    {
        $tracked = $this->pull('job:'.$this->jobKey($job));

        $this->safely(function () use ($job, $status, $exception, $tracked): void {
            $run = $tracked !== null
                ? TaskRun::find($tracked['id'])
                : TaskRun::firstWhere('job_uuid', $job->uuid());

            $run?->update([
                'status' => $status,
                'message' => $exception ? str(class_basename($exception).' : '.$exception->getMessage())->limit(1000)->toString() : null,
                'finished_at' => now(),
                ...($tracked !== null ? ['duration_ms' => $this->elapsedMilliseconds($tracked['started'])] : []),
            ]);

            $this->alertOnFailure($run);
        });
    }

    private function alertOnFailure(?TaskRun $run): void
    {
        if ($run?->status === TaskRunStatus::Failed) {
            $this->safely(fn () => app(TaskFailureNotifier::class)->notify($run));
        }
    }

    private function tracksCommand(?string $command): bool
    {
        return $command !== null
            && str($command)->startsWith(config('admin.task_history.tracked_commands', []));
    }

    private function commandArguments(CommandStarting $event): ?string
    {
        $options = collect($event->input->getOptions())
            ->filter(fn (mixed $value, string $name): bool => $value !== null && $value !== false && $value !== []
                && $event->input->hasParameterOption('--'.$name))
            ->except(['verbose', 'quiet', 'ansi', 'no-ansi', 'no-interaction', 'env', 'help', 'version', 'silent'])
            ->map(fn (mixed $value, string $name): string => $value === true ? "--{$name}" : "--{$name}=".implode(',', (array) $value));

        return $options->isEmpty() ? null : $options->implode(' ');
    }

    /**
     * Décrit l'élément traité (photo, objet céleste) pour les jobs de l'application.
     */
    private function jobSubject(Job $job): ?string
    {
        $command = $job->payload()['data']['command'] ?? null;

        if (! is_string($command) || ! str_contains($command, 'App\\')) {
            return null;
        }

        return rescue(function () use ($command): ?string {
            // Les jobs spatie gardent le média en propriété protégée.
            $properties = (fn (): array => get_object_vars($this))->call(unserialize($command));
            $subject = match (true) {
                ($properties['media'] ?? null) instanceof Media => $properties['media']->model,
                isset($properties['mailable']) => $properties['mailable']->astrophoto ?? null,
                default => $properties['astrophoto'] ?? $properties['celestialObject'] ?? null,
            };

            return match (true) {
                $subject instanceof Astrophoto => "Photo #{$subject->id} · {$subject->title}",
                $subject instanceof CelestialObject => $subject->displayName(),
                default => null,
            };
        }, report: false);
    }

    private function jobKey(Job $job): string
    {
        return $job->uuid() ?? (string) $job->getJobId();
    }

    /** @return array{id: int, started: int}|null */
    private function pull(string $key): ?array
    {
        $tracked = $this->running[$key] ?? null;
        unset($this->running[$key]);

        return $tracked;
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function safely(callable $callback): void
    {
        rescue($callback, report: false);
    }
}
