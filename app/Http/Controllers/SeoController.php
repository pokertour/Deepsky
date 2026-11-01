<?php

namespace App\Http\Controllers;

use App\Models\Astrophoto;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        return response()->view('seo.sitemap', [
            'photos' => Astrophoto::published()->orderBy('id')->get(['id']),
        ])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".route('sitemap')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
