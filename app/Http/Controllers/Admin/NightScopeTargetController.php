<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MosaicDisplayMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NightScopeTargetRequest;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Services\Planning\TargetDetailService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NightScopeTargetController extends Controller
{
    public function __invoke(
        NightScopeTargetRequest $request,
        CelestialObject $celestialObject,
        TargetDetailService $targets,
    ): View|RedirectResponse {
        $validated = $request->validated();
        $date = CarbonImmutable::parse($validated['date'] ?? 'today');
        $location = isset($validated['observing_location_id'])
            ? ObservingLocation::query()->findOrFail($validated['observing_location_id'])
            : null;
        $equipmentSetupSpecified = array_key_exists('equipment_setup_id', $validated);
        $equipmentSetup = $equipmentSetupSpecified && $validated['equipment_setup_id'] !== null
            ? EquipmentSetup::query()->findOrFail($validated['equipment_setup_id'])
            : null;
        $mosaicMode = MosaicDisplayMode::tryFrom($validated['mosaic'] ?? '')
            ?? MosaicDisplayMode::Auto;

        try {
            $data = $targets->build(
                $celestialObject,
                $date,
                $location,
                $equipmentSetup,
                $equipmentSetupSpecified,
                $mosaicMode,
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.nightscope.tonight', ['date' => $date->format('Y-m-d')])
                ->with('error', $exception->getMessage());
        }

        return view('admin.nightscope.target', [
            'data' => $data,
            'result' => $data->result,
            'date' => $data->date,
            'favorite' => $request->user()->celestialWishlistItems()->where('celestial_object_id', $celestialObject->id)->first(),
        ]);
    }
}
