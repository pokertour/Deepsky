<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CelestialObject;
use App\Services\Astronomy\CelestialDistanceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Number;

class EnrichCelestialDistanceController extends Controller
{
    public function __invoke(
        CelestialObject $celestialObject,
        CelestialDistanceResolver $distances,
    ): RedirectResponse {
        $distance = $distances->fillMissing($celestialObject, force: true);

        if ($distances->wasUnreachable()) {
            return back()->with('error', __('SIMBAD ne répond pas (serveur de Strasbourg et miroir de Harvard). Les distances connues sont conservées : réessaie plus tard.'));
        }

        if ($distance === null) {
            return back()->with('error', __('Aucune distance SIMBAD trouvée pour :object.', ['object' => $celestialObject->designation()]));
        }

        return back()->with(
            'success',
            __('Distance mise à jour : :distance al.', ['distance' => Number::format($distance, 0, locale: app()->getLocale())]),
        );
    }
}
