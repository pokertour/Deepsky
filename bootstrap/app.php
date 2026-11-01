<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetLocale;
use App\Support\TrustedHosts;
use App\Support\TrustedProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (): string => route('admin.login'));

        $middleware->trustProxies(at: TrustedProxies::from(env('TRUSTED_PROXIES')));
        $middleware->trustHosts(at: TrustedHosts::patterns(env('TRUSTED_HOSTS')));

        // Photos aimées (liste d'IDs) et langue choisie : rien de sensible.
        $middleware->encryptCookies(except: ['liked_photos', SetLocale::COOKIE]);

        // Désinscription one-click (RFC 8058) : les clients mail envoient un POST
        // sans token CSRF ; le token de désinscription fait office de secret.
        $middleware->preventRequestForgery(except: ['newsletter/desinscription/*']);

        // Globale (et non limitée au groupe web) : les pages 404 sont aussi traduites.
        $middleware->append(SetLocale::class);
        $middleware->append(AddSecurityHeaders::class);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
