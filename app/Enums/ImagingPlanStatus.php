<?php

namespace App\Enums;

enum ImagingPlanStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => __('Planifiée'),
            self::Active => __('En cours'),
            self::Completed => __('Terminée'),
            self::Cancelled => __('Annulée'),
        };
    }
}
