<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Astronomy\SaveCelestialObjectAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SearchCelestialObjectRequest;
use App\Http\Requests\Admin\StoreCelestialObjectRequest;
use App\Http\Requests\Admin\UpdateCelestialObjectRequest;
use App\Models\CelestialObject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CelestialObjectController extends Controller
{
    public function index(Request $request): View
    {
        $objects = CelestialObject::query()
            ->withCount('astrophotos')
            ->search($request->string('search')->toString())
            ->when($request->string('catalog')->isNotEmpty(), fn ($query) => $query->where('catalog', $request->string('catalog')->upper()))
            ->ofType($request->string('type')->toString() ?: null)
            ->inConstellation($request->string('constellation')->toString() ?: null)
            ->magnitudeBetween(
                $request->filled('magnitude_max') ? (float) $request->input('magnitude_max') : null,
                $request->filled('magnitude_min') ? (float) $request->input('magnitude_min') : null,
            )
            ->minimumSize($request->filled('min_size') ? (float) $request->input('min_size') : null)
            ->photographed($request->string('photographed')->toString() ?: null)
            ->orderBy('catalog')
            ->orderByRaw('length(catalog_number), catalog_number')
            ->paginate(20)
            ->withQueryString();

        $catalogs = CelestialObject::query()->distinct()->orderBy('catalog')->pluck('catalog');
        $types = CelestialObject::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type');
        $constellations = CelestialObject::query()->whereNotNull('constellation')->distinct()->orderBy('constellation')->pluck('constellation');

        $favorites = $request->user()->celestialWishlistItems()->get()->keyBy('celestial_object_id');

        return view('admin.celestial-objects.index', compact('objects', 'catalogs', 'types', 'constellations', 'favorites'));
    }

    public function create(): View
    {
        return view('admin.celestial-objects.create');
    }

    public function store(StoreCelestialObjectRequest $request, SaveCelestialObjectAction $save): RedirectResponse
    {
        $save->execute(null, $request->validated());

        return redirect()->route('admin.celestial-objects.index')->with('success', __('Objet céleste ajouté.'));
    }

    public function edit(CelestialObject $celestialObject): View
    {
        return view('admin.celestial-objects.edit', compact('celestialObject'));
    }

    public function update(UpdateCelestialObjectRequest $request, CelestialObject $celestialObject, SaveCelestialObjectAction $save): RedirectResponse
    {
        $save->execute($celestialObject, $request->validated());

        return redirect()->route('admin.celestial-objects.index')->with('success', __('Objet céleste mis à jour.'));
    }

    public function destroy(CelestialObject $celestialObject): RedirectResponse
    {
        $celestialObject->delete();

        return back()->with('success', __('Objet céleste supprimé. Les astrophotos ont été conservées.'));
    }

    public function search(SearchCelestialObjectRequest $request): JsonResponse
    {
        $search = Str::squish($request->validated('q'));

        $objects = CelestialObject::query()
            ->select(['id', 'catalog', 'catalog_number', 'name', 'alternative_names', 'type'])
            ->search($search)
            ->orderBy('catalog')
            ->orderBy('catalog_number')
            ->limit(20)
            ->get()
            ->map(fn (CelestialObject $object): array => [
                'id' => $object->id,
                'label' => $object->displayName(),
                'designation' => $object->designation(),
                'name' => $object->name,
                'title' => $object->suggestedPhotoTitle(),
                'type' => $object->type,
                'tag' => $object->galleryTag(),
            ]);

        return response()->json($objects);
    }
}
