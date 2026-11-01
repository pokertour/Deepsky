<?php

namespace App\Enums;

enum TaskRunStatus: string
{
    case Running = 'running';
    case Released = 'released';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('En cours'),
            self::Released => __('Reprogrammé'),
            self::Succeeded => __('Réussi'),
            self::Failed => __('Échec'),
        };
    }

    /**
     * Classes Tailwind du badge, alignées sur les badges de statut de l'admin.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Running => 'border-accent/25 bg-accent/10 text-accent',
            self::Released => 'border-amber-400/20 bg-amber-400/10 text-amber-300',
            self::Succeeded => 'border-green-400/20 bg-green-400/10 text-green-300',
            self::Failed => 'border-red-400/25 bg-red-400/10 text-red-300',
        };
    }
}
