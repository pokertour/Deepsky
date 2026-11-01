<?php

namespace App\Enums;

enum FramingRating: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case Small = 'small';
    case TooLarge = 'too_large';
    case Mosaic = 'mosaic';

    public function label(): string
    {
        return match ($this) {
            self::Excellent => __('Excellent'),
            self::Good => __('Bon'),
            self::Small => __('Petit'),
            self::TooLarge => __('Trop grand'),
            self::Mosaic => __('Mosaïque'),
        };
    }
}
