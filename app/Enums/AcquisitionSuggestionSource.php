<?php

namespace App\Enums;

enum AcquisitionSuggestionSource: string
{
    case Preferred = 'preferred';
    case Historical = 'historical';
    case Heuristic = 'heuristic';

    public function label(): string
    {
        return match ($this) {
            self::Preferred => __('Préférence caméra'),
            self::Historical => __('Historique Rig'),
            self::Heuristic => __('Estimation'),
        };
    }
}
