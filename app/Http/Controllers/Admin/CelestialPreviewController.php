<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Astronomy\CelestialPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
use Throwable;

class CelestialPreviewController extends Controller
{
    public function __invoke(Request $request, CelestialPreview $preview): Response
    {
        $data = $request->validate([
            'ra' => ['required', 'numeric', 'between:0,360'],
            'dec' => ['required', 'numeric', 'between:-90,90'],
            'fov' => ['required', 'numeric', 'between:0.1,10'],
        ]);

        try {
            $bytes = $preview->fetch((float) $data['ra'], (float) $data['dec'], (float) $data['fov']);
        } catch (Throwable $exception) {
            report($exception instanceof RuntimeException ? $exception : new RuntimeException($exception->getMessage(), 0, $exception));

            abort(502, __('Aperçu céleste indisponible.'));
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
