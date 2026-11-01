<?php

namespace App\Jobs;

use App\Support\RunnableCommands;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

/**
 * Lancement manuel d'une tâche planifiée depuis admin > Tâches. Passe par la
 * file pour ne pas bloquer la requête (l'import du catalogue dure plusieurs minutes).
 */
class RunScheduledCommandJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Prime sur le --timeout=120 du worker par défaut ; reste sous le retry_after (960 s).
     */
    public int $timeout = 900;

    public function __construct(public string $command) {}

    public function handle(): void
    {
        Artisan::call($this->command, RunnableCommands::parameters($this->command));
    }
}
