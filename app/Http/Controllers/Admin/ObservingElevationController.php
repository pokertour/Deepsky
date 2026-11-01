<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ObservingElevationRequest;
use App\Services\Observing\ElevationLookup;
use Illuminate\Http\JsonResponse;
use Throwable;

class ObservingElevationController extends Controller
{
    public function __invoke(ObservingElevationRequest $request, ElevationLookup $lookup): JsonResponse
    {
        try {
            return response()->json([
                'elevation' => $lookup->meters((float) $request->validated('latitude'), (float) $request->validated('longitude')),
                'source' => 'Open-Meteo · Copernicus DEM GLO-90',
            ])->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => __('Altitude indisponible. Tu peux la saisir manuellement.')], 503)
                ->header('Cache-Control', 'no-store, private');
        }
    }
}
