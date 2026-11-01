<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue de la requête : paramètre ?lang= (mémorisé un an dans un cookie),
 * sinon cookie, sinon langue préférée du navigateur, sinon langue par défaut.
 * Fixée à chaque requête : aucun état ne fuit d'une requête à l'autre sous Octane.
 */
class SetLocale
{
    public const COOKIE = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');
        $locale = match (true) {
            is_string($requested) && Locales::isSupported($requested) => $requested,
            Locales::isSupported((string) $request->cookie(self::COOKIE)) => (string) $request->cookie(self::COOKIE),
            default => $request->getPreferredLanguage(Locales::codes()) ?? Locales::default(),
        };
        $locale = Locales::isSupported($locale) ? $locale : Locales::default();

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);

        if (is_string($requested) && Locales::isSupported($requested) && $request->cookie(self::COOKIE) !== $requested) {
            $response->headers->setCookie(cookie(self::COOKIE, $requested, 60 * 24 * 365, httpOnly: false));
        }

        $response->headers->set('Content-Language', $locale);
        $response->setVary(array_unique([...$response->getVary(), 'Accept-Language', 'Cookie']));

        return $response;
    }
}
