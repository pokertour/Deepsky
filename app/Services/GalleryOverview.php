<?php

namespace App\Services;

use App\Models\Astrophoto;
use Illuminate\Support\Facades\Cache;

/**
 * Chiffres du bandeau d'accueil et image de partage par défaut, calculés sur les
 * seules photos publiées et mis en cache quelques minutes.
 */
class GalleryOverview
{
    /**
     * @return array{photos: int, hours: int, targets: int, since: ?int, cover: ?string}
     */
    public function summary(): array
    {
        return Cache::remember('gallery:overview', now()->addMinutes(10), function (): array {
            $published = Astrophoto::published();
            $firstCapture = (clone $published)->min('captured_at');
            $latest = (clone $published)->with('media')->orderByDesc('captured_at')->first();

            return [
                'photos' => (clone $published)->count(),
                'hours' => (int) round((clone $published)->sum('exposure') / 3600),
                'targets' => (clone $published)->whereNotNull('target')->distinct()->count('target'),
                'since' => $firstCapture ? (int) substr((string) $firstCapture, 0, 4) : null,
                'cover' => $latest?->getFirstMediaUrl('astros', 'optimized') ?: null,
            ];
        });
    }
}
