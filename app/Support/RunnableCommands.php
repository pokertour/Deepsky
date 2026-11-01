<?php

namespace App\Support;

use App\Models\LoginEvent;
use App\Models\TaskRun;

/**
 * Commandes planifiées que l'admin peut lancer à la main depuis la page Tâches,
 * avec les mêmes paramètres que dans routes/console.php.
 */
class RunnableCommands
{
    /** @var list<string> */
    private const DESTRUCTIVE = ['newsletter:purge-unconfirmed', 'model:prune', 'queue:prune-failed'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'nightscope:warm-weather' => [],
            'nightscope:enrich-distances' => ['--limit' => (int) config('nightscope.distances.enrich_batch', 150)],
            'nightscope:import-catalog' => ['--latest' => true],
            'nightscope:notify-daily' => ['--force' => true],
            'nightscope:notify-weekly' => ['--force' => true],
            'r2:warm-stats' => [],
            'newsletter:purge-unconfirmed' => [],
            'model:prune' => ['--model' => [TaskRun::class, LoginEvent::class]],
            'maintenance:recover-processing' => [],
            'astrophotos:enrich-field-distances' => [],
            'astrophotos:resync-distances' => [],
            'queue:prune-failed' => ['--hours' => 24 * 30],
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function parameters(string $command): array
    {
        return self::all()[$command] ?? [];
    }

    public static function isDestructive(string $command): bool
    {
        return in_array($command, self::DESTRUCTIVE, true);
    }
}
