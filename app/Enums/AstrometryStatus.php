<?php

namespace App\Enums;

enum AstrometryStatus: string
{
    case Idle = 'idle';
    case Pending = 'pending';
    case Solving = 'solving';
    case Solved = 'solved';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Idle => __('Non résolu'),
            self::Pending => __('En attente'),
            self::Solving => __('Résolution...'),
            self::Solved => __('Résolu'),
            self::Failed => __('Échec'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Idle => 'gray',
            self::Pending, self::Solving => 'warning',
            self::Solved => 'success',
            self::Failed => 'danger',
        };
    }
}
