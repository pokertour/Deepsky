<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NightScopeTonightRequest;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Services\Planning\TonightService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class NightScopeTonightController extends Controller
{
    public function __invoke(NightScopeTonightRequest $request, TonightService $tonight): View
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? 'today');
        $favorites = $request->user()->celestialWishlistItems()->get()->keyBy('celestial_object_id');
        $setupSpecified = $request->exists('equipment_setup_id');

        return view('admin.nightscope.tonight', [
            'data' => $tonight->build(
                $date,
                $request->objectTypes(),
                $request->recommendationLimit(),
                $request->neverPhotographedOnly(),
                $request->validated('search'),
                $request->validated('observing_location_id') ? ObservingLocation::findOrFail($request->validated('observing_location_id')) : null,
                $request->validated('equipment_setup_id') ? EquipmentSetup::findOrFail($request->validated('equipment_setup_id')) : null,
                $setupSpecified,
                $request->validated('favorites_only') ? $favorites->keys()->all() : null,
            ),
            'locations' => ObservingLocation::query()->orderBy('name')->get(),
            'equipmentSetups' => EquipmentSetup::query()->orderBy('name')->get(),
            'favorites' => $favorites,
        ]);
    }
}
