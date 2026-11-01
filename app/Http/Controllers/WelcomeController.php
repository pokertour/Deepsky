<?php

namespace App\Http\Controllers;

use App\Models\Astrophoto;
use App\Services\GalleryFilterOptions;
use App\Services\GalleryOverview;
use App\Support\VisitorFingerprint;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WelcomeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, GalleryFilterOptions $filterOptions, GalleryOverview $overview): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'in:galaxy,nebula,cluster'],
            'constellation' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:1900,2200'],
            'equipment' => ['nullable', 'string', 'regex:/^(rig|item):[0-9]+$/'],
            'sort' => ['nullable', 'string', 'in:views,likes'],
        ]);
        $this->recordVisit($request);

        $query = Astrophoto::published()->with([
            'equipmentSetup.enabledSetupItems',
            'observingLocation:id,region',
            'celestialObject',
            'camera',
            'mount',
            'opticalTube',
            'media',
        ]);

        if ($request->filled('search')) {
            $searchTerm = $request->input('search');

            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('target', 'like', "%{$searchTerm}%") // Ajouté car présent dans ta table
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhereHas('observingLocation', fn ($locationQuery) => $locationQuery->where('region', 'like', "%{$searchTerm}%"))
                    ->orWhereHas('celestialObject', function ($celestialQuery) use ($searchTerm): void {
                        $celestialQuery->search($searchTerm);
                    })
                    ->orWhereHas('equipmentSetup', function ($rigQuery) use ($searchTerm) {
                        $rigQuery->where('name', 'like', "%{$searchTerm}%")
                            ->orWhereHas('enabledSetupItems', fn ($itemQuery) => $itemQuery
                                ->where('name', 'like', "%{$searchTerm}%")
                                ->orWhere('brand', 'like', "%{$searchTerm}%"));
                    })
                    ->orWhereHas('camera', function ($q2) use ($searchTerm) {
                        $q2->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('mount', function ($q3) use ($searchTerm) {
                        $q3->where('name', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('opticalTube', function ($q4) use ($searchTerm) {
                        $q4->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        if ($request->filled('type')) {
            $type = $request->input('type');
            // On utilise whereJsonContains car ta colonne 'tags' est de type JSON
            $query->where(fn ($q) => $q->whereJsonContains('tags', $type)
                ->orWhereHas('celestialObject', fn ($object) => $object->where('type', $type)));
        }

        $query->when($request->filled('constellation'), fn ($q) => $q->whereHas('celestialObject',
            fn ($object) => $object->where('constellation', $request->string('constellation')->toString())))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('captured_at', $request->integer('year')));

        if ($request->filled('equipment')) {
            [$kind, $equipmentId] = explode(':', $request->string('equipment')->toString());
            if ($kind === 'rig') {
                $query->where('equipment_setup_id', (int) $equipmentId);
            } else {
                $query->where(fn ($q) => $q
                    ->whereHas('equipmentSetup.enabledSetupItems', fn ($item) => $item->where('setup_items.id', (int) $equipmentId))
                    ->orWhere('camera_id', (int) $equipmentId)
                    ->orWhere('mount_id', (int) $equipmentId)
                    ->orWhere('optical_tube_id', (int) $equipmentId));
            }
        }

        match ($request->input('sort')) {
            'views' => $query->orderByDesc('views_count'),
            'likes' => $query->orderByDesc('likes_count'),
            default => $query->orderByDesc('captured_at'),
        };

        $photos = $query->paginate(12)->withQueryString();

        // Autres captures des mêmes cibles (toutes pages confondues),
        // pour proposer la comparaison avant/après entre versions.
        $versionsByTarget = Astrophoto::published()->with('media')
            ->whereIn('target', $photos->getCollection()->pluck('target')->filter())
            ->orderBy('captured_at')
            ->get()
            ->groupBy('target');

        return view('welcome', [
            'photos' => $photos,
            'versionsByTarget' => $versionsByTarget,
            'filterOptions' => $filterOptions->all(),
            'overview' => $overview->summary(),
        ]);
    }

    /**
     * Incrémente le compteur de visites une seule fois par session
     * afin d'éviter de gonfler le total à chaque rafraîchissement.
     */
    private function recordVisit(Request $request): void
    {
        if (VisitorFingerprint::isBot($request) || $request->session()->has('visit_counted')) {
            return;
        }

        $request->session()->put('visit_counted', true);

        // Un client sans cookie de session ouvrirait une nouvelle visite à chaque page.
        if (! Cache::add('visit:'.VisitorFingerprint::hash($request), true, now()->addHours(12))) {
            return;
        }

        DB::table('site_settings')->where('key', 'total_visits')->increment('value');
    }
}
