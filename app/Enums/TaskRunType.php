<?php

namespace App\Enums;

enum TaskRunType: string
{
    case Command = 'command';
    case Job = 'job';

    public function label(): string
    {
        return match ($this) {
            self::Command => __('Tâche planifiée'),
            self::Job => __('Job en file'),
        };
    }
}
