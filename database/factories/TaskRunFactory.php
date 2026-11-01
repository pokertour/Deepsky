<?php

namespace Database\Factories;

use App\Enums\TaskRunStatus;
use App\Enums\TaskRunType;
use App\Jobs\PerformAstrophotoConversionsJob;
use App\Models\TaskRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaskRun>
 */
class TaskRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(1, 600));

        return [
            'type' => TaskRunType::Command,
            'name' => 'nightscope:warm-weather',
            'status' => TaskRunStatus::Succeeded,
            'attempts' => 1,
            'started_at' => $startedAt,
            'finished_at' => $startedAt->copy()->addSeconds(3),
            'duration_ms' => 3_000,
        ];
    }

    public function job(string $name = PerformAstrophotoConversionsJob::class): static
    {
        return $this->state(fn (): array => [
            'type' => TaskRunType::Job,
            'name' => $name,
            'queue' => 'media',
            'job_uuid' => (string) Str::uuid(),
        ]);
    }

    public function failed(string $message = 'Échec simulé'): static
    {
        return $this->state(fn (): array => [
            'status' => TaskRunStatus::Failed,
            'message' => $message,
        ]);
    }
}
