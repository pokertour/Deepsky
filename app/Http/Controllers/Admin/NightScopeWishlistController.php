<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NightScopeDateRequest;
use App\Http\Requests\Admin\UpdateCelestialWishlistRequest;
use App\Models\CelestialObject;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NightScopeWishlistController extends Controller
{
    public function index(NightScopeDateRequest $request): View
    {
        return view('admin.nightscope.wishlist', [
            'items' => $request->user()->celestialWishlistItems()
                ->with('celestialObject')->orderBy('priority')->latest('id')->paginate(24)->withQueryString(),
            'date' => CarbonImmutable::parse($request->validated('date') ?? 'today'),
        ]);
    }

    public function update(UpdateCelestialWishlistRequest $request, CelestialObject $celestialObject): RedirectResponse
    {
        $request->user()->celestialWishlistItems()->updateOrCreate(
            ['celestial_object_id' => $celestialObject->id],
            $request->validated(),
        );

        return back()->with('success', __('Cible enregistrée dans tes favoris.'));
    }

    public function destroy(Request $request, CelestialObject $celestialObject): RedirectResponse
    {
        $request->user()->celestialWishlistItems()->where('celestial_object_id', $celestialObject->id)->delete();

        return back()->with('success', __('Cible retirée de tes favoris.'));
    }
}
