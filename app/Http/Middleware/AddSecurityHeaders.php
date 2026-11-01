<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de durcissement communs à toutes les réponses : anti-clickjacking,
 * anti-sniffing MIME, politique de referrer, permissions navigateur limitées
 * et Content-Security-Policy par listes d'origines.
 *
 * La CSP garde 'unsafe-inline' et 'unsafe-eval' (gestionnaires inline, Alpine et
 * Livewire en ont besoin) mais n'autorise que des origines connues pour les
 * scripts, connexions et iframes : un script injecté ne peut ni charger de code
 * tiers ni envoyer de données par fetch/XHR vers un autre domaine.
 */
class AddSecurityHeaders
{
    private const array CAPTCHA_ORIGINS = [
        'https://challenges.cloudflare.com',
        'https://hcaptcha.com',
        'https://*.hcaptcha.com',
    ];

    private const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'accelerometer=(), autoplay=(), camera=(self), geolocation=(self), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=(), interest-cohort=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $captcha = implode(' ', self::CAPTCHA_ORIGINS);
        $devServer = $this->viteDevServerOrigins();

        $directives = [
            'default-src' => "'self'",
            'script-src' => trim("'self' 'unsafe-inline' 'unsafe-eval' {$captcha} {$devServer}"),
            'style-src' => trim("'self' 'unsafe-inline' https://fonts.googleapis.com {$captcha} {$devServer}"),
            'font-src' => "'self' data: https://fonts.gstatic.com",
            'img-src' => "'self' data: blob: https:",
            'media-src' => "'self' blob:",
            'connect-src' => trim("'self' {$captcha} {$devServer}"),
            'frame-src' => trim("'self' {$captcha}"),
            'worker-src' => "'self' blob:",
            'manifest-src' => "'self'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
        ];

        $policy = collect($directives)->map(fn (string $value, string $directive): string => "{$directive} {$value}")->implode('; ');

        return $request->isSecure() ? "{$policy}; upgrade-insecure-requests" : $policy;
    }

    /**
     * Serveur Vite de `npm run dev` : autorisé seulement quand il tourne en local.
     */
    private function viteDevServerOrigins(): string
    {
        if (! Vite::isRunningHot()) {
            return '';
        }

        $origin = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');

        return $origin.' '.preg_replace('/^http/', 'ws', $origin);
    }
}
