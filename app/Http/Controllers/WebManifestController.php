<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class WebManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $icon192 = asset('pwa/icon-192.png');
        $icon512 = asset('pwa/icon-512.png');

        return response()->json([
            'id' => url('/'),
            'name' => 'DEEPSKY',
            'short_name' => 'Deepsky',
            'description' => __('Portfolio d’astrophotographie et cockpit NightScope.'),
            'lang' => 'fr',
            'dir' => 'ltr',
            'start_url' => route('welcome', absolute: false),
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui', 'browser'],
            'orientation' => 'any',
            'background_color' => '#0d1117',
            'theme_color' => '#0d1117',
            'icons' => [
                [
                    'src' => $icon192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $icon512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $icon512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => __('Galerie'),
                    'short_name' => __('Galerie'),
                    'url' => route('welcome', absolute: false),
                ],
                [
                    'name' => __('Tonight'),
                    'short_name' => __('Tonight'),
                    'url' => route('admin.nightscope.tonight', absolute: false),
                ],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
