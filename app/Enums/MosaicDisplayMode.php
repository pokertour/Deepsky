<?php

namespace App\Enums;

enum MosaicDisplayMode: string
{
    case Auto = 'auto';
    case Off = 'off';
    case Grid2x1 = '2x1';
    case Grid1x2 = '1x2';
    case Grid2x2 = '2x2';
    case Grid3x2 = '3x2';
    case Grid2x3 = '2x3';
    case Grid3x3 = '3x3';
    case Grid4x3 = '4x3';
    case Grid3x4 = '3x4';

    public function label(): string
    {
        return match ($this) {
            self::Auto => __('Auto (si nécessaire)'),
            self::Off => __('Masquée'),
            default => str_replace('x', '×', $this->value),
        };
    }

    /** @return array{int, int}|null */
    public function grid(): ?array
    {
        if ($this === self::Auto || $this === self::Off) {
            return null;
        }

        [$columns, $rows] = array_map('intval', explode('x', $this->value));

        return [$columns, $rows];
    }
}
