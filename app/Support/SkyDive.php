<?php

namespace App\Support;

use App\Enums\AstrometryStatus;
use App\Models\Astrophoto;

/**
 * Données de « la Plongée » : du ciel au-dessus du lieu public jusqu'à la photo,
 * placée à sa position, son échelle et son orientation astrométriques, puis
 * exploration des objets du champ et de l'âge de leur lumière.
 */
class SkyDive
{
    /**
     * @return array<string, mixed>|null null si la photo n'est pas résolue
     */
    public static function for(Astrophoto $photo): ?array
    {
        if ($photo->astrometry_status !== AstrometryStatus::Solved || $photo->ra === null || $photo->dec === null || ! $photo->pixel_scale) {
            return null;
        }

        $mainDistance = $photo->celestialObject?->distance_ly;

        return [
            ...LiveSky::config(),
            'detailed' => false,
            'focus' => [
                'label' => $photo->celestialObject?->designation() ?: ($photo->target ?: $photo->localized('title')),
                'title' => $photo->localized('title'),
                'ra' => $photo->ra,
                'dec' => $photo->dec,
                'pixelScale' => $photo->pixel_scale,
                'orientation' => $photo->orientation ?? 0,
                'image' => $photo->getFirstMediaUrl('astros', 'optimized'),
                'distance' => $mainDistance,
                'light' => LightTravel::describe($mainDistance),
                'annotations' => collect($photo->annotations ?? [])
                    ->map(fn (array $annotation): array => [
                        'name' => (string) ($annotation['name'] ?? ''),
                        'x' => (float) ($annotation['x'] ?? 0),
                        'y' => (float) ($annotation['y'] ?? 0),
                        'r' => (float) ($annotation['r'] ?? 0),
                        'distance' => $annotation['distance_ly'] ?? null,
                        'light' => LightTravel::describe($annotation['distance_ly'] ?? null),
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }
}
