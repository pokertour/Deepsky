<?php

namespace App\Support;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres du ciel en direct (connexion et observatoire public) : lieu public
 * et objets déjà photographiés, uniquement ceux de photos publiées.
 */
class LiveSky
{
    /**
     * @return array{label: string, latitude: float, longitude: float, timezone: string, targets: list<array{label: string, name: ?string, ra: float, dec: float, url: string, title: string, thumb: ?string, size: ?float}>}
     */
    public static function config(): array
    {
        return [
            'label' => (string) config('nightscope.live_sky.label'),
            'latitude' => (float) config('nightscope.live_sky.latitude'),
            'longitude' => (float) config('nightscope.live_sky.longitude'),
            'timezone' => (string) config('nightscope.live_sky.timezone'),
            'targets' => Cache::remember('live-sky:targets:'.app()->getLocale(), now()->addMinutes(10), fn (): array => CelestialObject::query()
                ->whereHas('astrophotos', fn ($query) => $query->published())
                ->whereNotNull('ra_deg')
                ->whereNotNull('dec_deg')
                ->with(['astrophotos' => fn ($query) => $query->published()->orderByDesc('captured_at')->with('media')->select(['id', 'title', 'celestial_object_id', 'captured_at'])])
                ->limit(60)
                ->get(['id', 'catalog', 'catalog_number', 'name', 'ra_deg', 'dec_deg', 'angular_width_arcmin', 'angular_height_arcmin'])
                ->map(function (CelestialObject $object): array {
                    /** @var Astrophoto $photo */
                    $photo = $object->astrophotos->first();

                    return [
                        'label' => $object->designation(),
                        'name' => $object->name,
                        'ra' => $object->ra_deg,
                        'dec' => $object->dec_deg,
                        'url' => route('astrophoto.show', $photo),
                        'title' => $photo->localized('title'),
                        'thumb' => $photo->thumbnailUrl(),
                        'size' => max($object->angular_width_arcmin ?? 0, $object->angular_height_arcmin ?? 0) ?: null,
                    ];
                })
                ->all()),
        ];
    }
}
