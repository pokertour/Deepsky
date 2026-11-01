<?php

namespace App\Enums;

enum CelestialObjectType: string
{
    case Galaxy = 'galaxy';
    case Nebula = 'nebula';
    case Cluster = 'cluster';

    public function label(): string
    {
        return match ($this) {
            self::Galaxy => __('Galaxies'),
            self::Nebula => __('Nébuleuses'),
            self::Cluster => __('Amas'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }
}
