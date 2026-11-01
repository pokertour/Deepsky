<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Observing\SaveObservingLocationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreObservingLocationRequest;
use App\Http\Requests\Admin\UpdateObservingLocationRequest;
use App\Models\ObservingLocation;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ObservingLocationController extends Controller
{
    public function index(): View
    {
        $locations = ObservingLocation::query()->latest('is_default')->latest()->paginate(12);

        return view('admin.observing-locations.index', compact('locations'));
    }

    public function create(): View
    {
        return view('admin.observing-locations.create', $this->formData());
    }

    public function store(StoreObservingLocationRequest $request, SaveObservingLocationAction $save): RedirectResponse
    {
        $save->execute(null, $request->validated());

        return redirect()->route('admin.observing-locations.index')->with('success', __('Lieu d’observation ajouté.'));
    }

    public function edit(ObservingLocation $observingLocation): View
    {
        return view('admin.observing-locations.edit', ['observingLocation' => $observingLocation, ...$this->formData()]);
    }

    public function update(UpdateObservingLocationRequest $request, ObservingLocation $observingLocation, SaveObservingLocationAction $save): RedirectResponse
    {
        $save->execute($observingLocation, $request->validated());

        return redirect()->route('admin.observing-locations.index')->with('success', __('Lieu d’observation mis à jour.'));
    }

    public function destroy(ObservingLocation $observingLocation): RedirectResponse
    {
        $observingLocation->delete();

        return back()->with('success', __('Lieu d’observation supprimé.'));
    }

    /** @return array{timezones: list<string>} */
    private function formData(): array
    {
        return ['timezones' => DateTimeZone::listIdentifiers()];
    }
}
