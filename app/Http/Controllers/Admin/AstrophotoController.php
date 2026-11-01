<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Gallery\QueueAstrophotoConversionsAction;
use App\Enums\AstrometryStatus;
use App\Enums\ConversionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAstrophotoRequest;
use App\Http\Requests\Admin\StoreAstrophotoRequest;
use App\Http\Requests\Admin\UpdateAstrophotoPublicationRequest;
use App\Http\Requests\Admin\UpdateAstrophotoRequest;
use App\Jobs\SolveAstrophotoJob;
use App\Models\Astrophoto;
use App\Models\EquipmentSetup;
use App\Models\ImagingSession;
use App\Models\ObservingLocation;
use App\Services\Admin\AstrophotoAttentionService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class AstrophotoController extends Controller
{
    public function index(IndexAstrophotoRequest $request, AstrophotoAttentionService $attention): View
    {
        $filter = $request->validated('attention');
        $attentionIds = in_array($filter, ['ready', 'failed'], true) ? $attention->groups()[$filter] : null;
        $photos = Astrophoto::query()
            ->with(['media', 'equipmentSetup:id,name'])
            ->when($attentionIds !== null, fn ($query) => $query->whereIn('id', $attentionIds))
            ->when($filter === 'unlinked', fn ($query) => $query->whereNull('celestial_object_id'))
            ->when(in_array($request->query('status'), ['draft', 'published'], true),
                fn ($query) => $query->where('is_published', $request->query('status') === 'published'))
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where('title', 'like', $search)
                    ->orWhere('target', 'like', $search)
                    ->orWhere('location_tag', 'like', $search)
                    ->orWhereHas('observingLocation', fn ($locationQuery) => $locationQuery
                        ->where('name', 'like', $search)
                        ->orWhere('region', 'like', $search))
                    ->orWhereHas('equipmentSetup', fn ($rigQuery) => $rigQuery
                        ->where('name', 'like', $search)
                        ->orWhereHas('enabledSetupItems', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', $search)
                            ->orWhere('brand', 'like', $search)));
            }))
            ->latest('captured_at')->paginate(12)->withQueryString();

        return view('admin.astrophotos.index', compact('photos'));
    }

    public function create(): View
    {
        return view('admin.astrophotos.create', [
            'equipmentSetups' => $this->equipmentSetups(),
            'imagingSessions' => $this->imagingSessions(),
            'observingLocations' => $this->observingLocations(),
        ]);
    }

    public function store(StoreAstrophotoRequest $request): RedirectResponse|JsonResponse
    {
        $photo = Astrophoto::create([
            ...$request->safe()->except(['image', 'starless_image', 'remove_starless']),
            'is_published' => false,
            'publish_at' => $request->scheduledPublication(),
        ]);
        if (! $this->saveImages($request, $photo)) {
            return $this->savedResponse($request, 'admin.astrophotos.edit', $photo, 'error', __('Le traitement de l’image a échoué. La fiche est conservée : vérifie le fichier puis relance les conversions ou remplace l’image.'));
        }

        return $this->savedResponse($request, 'admin.astrophotos.index', null, 'success', $photo->is_published ? __('Astrophoto ajoutée au vault.') : __('Brouillon enregistré.'));
    }

    public function edit(Astrophoto $astrophoto): View
    {
        $astrophoto->load(['media', 'celestialObject', 'equipmentSetup.enabledSetupItems', 'observingLocation']);

        return view('admin.astrophotos.edit', [
            'photo' => $astrophoto,
            'equipmentSetups' => $this->equipmentSetups(),
            'imagingSessions' => $this->imagingSessions(),
            'observingLocations' => $this->observingLocations(),
        ]);
    }

    public function update(UpdateAstrophotoRequest $request, Astrophoto $astrophoto): RedirectResponse|JsonResponse
    {
        $astrophoto->update([
            ...$request->safe()->except(['image', 'starless_image', 'remove_starless']),
            'publish_at' => $request->scheduledPublication(),
        ]);

        if (! $this->saveImages($request, $astrophoto)) {
            return $this->savedResponse($request, 'admin.astrophotos.edit', $astrophoto, 'error', __('Le traitement de l’image a échoué. Relance les conversions ou remplace l’image.'));
        }

        if (! $request->hasFile('starless_image') && $request->boolean('remove_starless')) {
            $astrophoto->clearMediaCollection('starless');
        }

        return $this->savedResponse($request, 'admin.astrophotos.index', null, 'success', __('Astrophoto mise à jour.'));
    }

    public function updatePublication(UpdateAstrophotoPublicationRequest $request, Astrophoto $astrophoto): RedirectResponse
    {
        $astrophoto->update([...$request->validated(), 'publish_at' => null]);

        return back()->with('success', $astrophoto->is_published ? __('Astrophoto publiée dans la galerie.') : __('Astrophoto repassée en brouillon.'));
    }

    public function destroy(Astrophoto $astrophoto): RedirectResponse
    {
        $astrophoto->delete();

        return back()->with('success', __('Astrophoto supprimée.'));
    }

    public function solve(Astrophoto $astrophoto): RedirectResponse
    {
        if (blank(config('services.astrometry.key'))) {
            return back()->with('error', __('Configure d’abord la clé API Astrometry.net dans les paramètres.'));
        }

        if (in_array($astrophoto->astrometry_status, [AstrometryStatus::Pending, AstrometryStatus::Solving], true)) {
            return back()->with('error', __('Une résolution astrométrique est déjà en cours pour cette photo.'));
        }

        $astrophoto->update(['astrometry_status' => AstrometryStatus::Pending, 'astrometry_submission_id' => null]);
        SolveAstrophotoJob::dispatch($astrophoto);

        return back()->with('success', __('Résolution astrométrique lancée.'));
    }

    public function retryConversions(Astrophoto $astrophoto, QueueAstrophotoConversionsAction $queueConversions): RedirectResponse
    {
        if ($astrophoto->conversion_status === ConversionStatus::Converting) {
            return back()->with('error', __('Une conversion est déjà en cours.'));
        }

        try {
            $queueConversions->execute($astrophoto);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Conversions relancées.'));
    }

    public function processingStatus(Astrophoto $astrophoto): JsonResponse
    {
        $astrophoto->load('media');

        return response()->json([
            'conversion_status' => $astrophoto->conversion_status->value,
            'conversion_label' => $astrophoto->conversion_status_label,
            'astrometry_status' => $astrophoto->astrometry_status?->value,
            'astrometry_label' => $astrophoto->astrometry_status?->getLabel() ?? __('Non résolue'),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function saveImages(Request $request, Astrophoto $photo): bool
    {
        try {
            foreach (['image' => 'astros', 'starless_image' => 'starless'] as $input => $collection) {
                if ($request->hasFile($input)) {
                    $photo->addMediaFromRequest($input)
                        ->withCustomProperties(['processing_status' => ConversionStatus::Queued->value])
                        ->toMediaCollection($collection, 's3');
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $photo->refresh()->load('media');
            foreach ($photo->media as $item) {
                if (! $item->hasGeneratedConversion('thumb')) {
                    $item->setCustomProperty('processing_status', ConversionStatus::Failed->value)->save();
                }
            }

            return false;
        }

        return true;
    }

    private function savedResponse(Request $request, string $route, ?Astrophoto $photo, string $type, string $message): RedirectResponse|JsonResponse
    {
        $url = route($route, $photo);
        $request->session()->flash($type, $message);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect($url);
    }

    /** @return Collection<int, EquipmentSetup> */
    private function equipmentSetups(): Collection
    {
        return EquipmentSetup::query()
            ->with(['enabledSetupItems' => fn ($query) => $query->select(['setup_items.id', 'name', 'brand', 'type'])])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
    }

    /** @return Collection<int, ImagingSession> */
    private function imagingSessions(): Collection
    {
        return ImagingSession::query()
            ->with(['observingLocation:id,name', 'equipmentSetup:id,name'])
            ->latest('started_at')
            ->limit(20)
            ->get(['id', 'observing_location_id', 'equipment_setup_id', 'started_at', 'ended_at']);
    }

    /** @return Collection<int, ObservingLocation> */
    private function observingLocations(): Collection
    {
        return ObservingLocation::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'region', 'is_default']);
    }
}
