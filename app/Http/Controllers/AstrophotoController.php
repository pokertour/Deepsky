<?php

namespace App\Http\Controllers;

use App\Actions\Gallery\ToggleAstrophotoLikeAction;
use App\Models\Astrophoto;
use App\Support\VisitorFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AstrophotoController extends Controller
{
    private const int MAX_LIKED_PHOTOS_IN_COOKIE = 500;

    /**
     * Compte une vue de la photo, en limitant le comptage par empreinte (IP + session + user agent).
     */
    public function recordView(Request $request, Astrophoto $photo): JsonResponse
    {
        abort_unless($photo->is_published, 404);

        if (VisitorFingerprint::isBot($request)) {
            return response()->json(['status' => 'ignored']);
        }

        $fingerprint = hash('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->session()->getId(),
            (string) $request->userAgent(),
        ]));

        $key = "photo-view:{$photo->id}:{$fingerprint}";

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return response()->json(['status' => 'ignored'], 429);
        }

        $photo->increment('views_count');
        RateLimiter::hit($key, 120);

        return response()->json(['status' => 'success']);
    }

    /**
     * Bascule le like anonyme d'un visiteur : le cookie garde l'état affiché,
     * l'empreinte visiteur en base empêche les likes répétés.
     */
    public function toggleLike(Request $request, Astrophoto $photo, ToggleAstrophotoLikeAction $toggle): JsonResponse
    {
        abort_unless($photo->is_published, 404);
        abort_if(VisitorFingerprint::isBot($request), 403);

        $cookieIds = json_decode((string) $request->cookie('liked_photos', '[]'), true);

        $likedIds = collect(is_array($cookieIds) ? $cookieIds : [])
            ->filter(fn ($id): bool => is_int($id) || ctype_digit((string) $id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->take(-self::MAX_LIKED_PHOTOS_IN_COOKIE);

        $liked = $toggle->execute($photo, VisitorFingerprint::hash($request), likedInCookie: $likedIds->contains($photo->id));

        $likedIds = $likedIds->reject(fn (int $id): bool => $id === $photo->id)
            ->when($liked, fn ($ids) => $ids->push($photo->id));

        return response()
            ->json(['liked' => $liked, 'likes_count' => $photo->fresh()->likes_count])
            ->cookie('liked_photos', $likedIds->values()->toJson(), 60 * 24 * 365);
    }

    /**
     * Affiche la version pleine résolution de la photo.
     */
    public function full(Request $request, Astrophoto $astrophoto): View|Response
    {
        $this->authorizeVisibility($request, $astrophoto);

        $astrophoto->load([
            'equipmentSetup.enabledSetupItems',
            'observingLocation:id,region',
            'celestialObject',
            'camera',
            'mount',
            'opticalTube',
        ]);

        return $this->photoView('astrophoto.full', $astrophoto, [
            'photo' => $astrophoto,
            'previous' => Astrophoto::published()->where('id', '<', $astrophoto->id)->orderByDesc('id')->first(['id', 'title']),
            'next' => Astrophoto::published()->where('id', '>', $astrophoto->id)->orderBy('id')->first(['id', 'title']),
        ]);
    }

    public function show(Request $request, Astrophoto $astrophoto): View|Response
    {
        $this->authorizeVisibility($request, $astrophoto);
        $astrophoto->load(['equipmentSetup.enabledSetupItems', 'observingLocation:id,region',
            'celestialObject', 'camera', 'mount', 'opticalTube', 'media']);

        $versions = Astrophoto::published()->with('media')
            ->where('target', $astrophoto->target)->whereKeyNot($astrophoto->id)
            ->when(blank($astrophoto->target), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('captured_at')->get();
        $previous = Astrophoto::published()->where('id', '<', $astrophoto->id)->orderByDesc('id')->first(['id', 'title']);
        $next = Astrophoto::published()->where('id', '>', $astrophoto->id)->orderBy('id')->first(['id', 'title']);

        return $this->photoView('astrophoto.show', $astrophoto, ['photo' => $astrophoto, 'versions' => $versions, 'previous' => $previous, 'next' => $next]);
    }

    private function photoView(string $view, Astrophoto $photo, array $data): View|Response
    {
        if (! $photo->is_published) {
            return response()->view($view, $data)->header('Cache-Control', 'private, no-store');
        }

        return view($view, $data);
    }

    private function authorizeVisibility(Request $request, Astrophoto $photo): void
    {
        abort_unless($photo->is_published || $request->user()?->isAdmin(), 404);
    }
}
