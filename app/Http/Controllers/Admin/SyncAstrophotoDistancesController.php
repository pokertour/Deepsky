<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Astrophoto;
use App\Services\Astronomy\CelestialDistanceResolver;
use App\Services\Astronomy\FieldDistanceEnricher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Number;

class SyncAstrophotoDistancesController extends Controller
{
    public function __invoke(
        Astrophoto $astrophoto,
        CelestialDistanceResolver $distances,
        FieldDistanceEnricher $fieldDistances,
    ): RedirectResponse {
        $fieldObjectsCount = count($astrophoto->annotations ?? []);

        if ($astrophoto->celestialObject === null && $fieldObjectsCount === 0) {
            return back()->with('error', __('Rien à synchroniser : associe un objet du catalogue ou lance l’astrométrie pour identifier les objets du champ.'));
        }

        $summary = [];

        if ($astrophoto->celestialObject !== null) {
            $distance = $distances->fillMissing($astrophoto->celestialObject, force: true);
            $summary[] = $distance === null
                ? __(':object : distance introuvable sur SIMBAD', ['object' => $astrophoto->celestialObject->designation()])
                : __(':object : :distance al', ['object' => $astrophoto->celestialObject->designation(), 'distance' => Number::format($distance, 0, locale: app()->getLocale())]);
        }

        if ($fieldObjectsCount > 0) {
            $summary[] = __(':found/:total objet(s) du champ avec distance', ['found' => $fieldDistances->enrich($astrophoto, refresh: true), 'total' => $fieldObjectsCount]);
        }

        if ($distances->wasUnreachable() || $fieldDistances->simbadWasUnreachable()) {
            return back()->with('error', __('SIMBAD ne répond pas (serveur de Strasbourg et miroir de Harvard). Les distances connues sont conservées : réessaie plus tard.'));
        }

        return back()->with('success', __('Distances synchronisées · :summary.', ['summary' => implode(' · ', $summary)]));
    }
}
