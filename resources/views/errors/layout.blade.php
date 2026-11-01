<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0d1117">
    <title>@yield('code') · @yield('title') {{ __('— Deepsky') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo/deepsky.svg') }}">
    <link rel="stylesheet" href="{{ asset('errors.css') }}">
</head>
<body>
    <div class="starfield" aria-hidden="true"></div>
    <div class="page">
        <header class="header">
            <a class="brand" href="{{ url('/') }}" aria-label="{{ __('Deepsky — accueil') }}">{{ __('DEEPSKY') }}<span>{{ __('EXPLORER LE CIEL PROFOND') }}</span></a>
            <span class="header-label">{{ __('CARNET D’EXPLORATION') }}</span>
        </header>

        <main id="main" class="error">
            <div class="sky" aria-hidden="true">
                <svg class="orbit" viewBox="0 0 600 380" fill="none">
                    <defs>
                        <radialGradient id="planet" cx=".3" cy=".25" r=".8">
                            <stop stop-color="#4a7bb6"/><stop offset=".5" stop-color="#1b3655"/><stop offset="1" stop-color="#0d1117"/>
                        </radialGradient>
                        <linearGradient id="ring" x1="80" y1="280" x2="510" y2="100" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#58a6ff" stop-opacity=".05"/><stop offset=".5" stop-color="#58a6ff" stop-opacity=".8"/><stop offset="1" stop-color="#58a6ff" stop-opacity=".1"/>
                        </linearGradient>
                    </defs>
                    <circle cx="300" cy="190" r="142" stroke="#58a6ff" stroke-opacity=".08"/>
                    <circle cx="300" cy="190" r="172" stroke="#58a6ff" stroke-opacity=".1" stroke-dasharray="2 9"/>
                    <ellipse cx="300" cy="190" rx="245" ry="62" transform="rotate(-24 300 190)" stroke="url(#ring)"/>
                    <circle cx="300" cy="190" r="94" fill="url(#planet)" stroke="#58a6ff" stroke-opacity=".2"/>
                    <path d="M77 279c36 25 163 3 282-50s200-112 184-136" stroke="url(#ring)" stroke-width="2"/>
                    <circle cx="469" cy="111" r="5" fill="#b4d7ff"/>
                    <path d="M128 86v14m-7-7h14M488 279v10m-5-5h10" stroke="#58a6ff" stroke-linecap="round"/>
                </svg>
                <span class="error-code">@yield('code')</span>
                <span class="sky-label">{{ __('DEEPSKY / SIGNAL') }} @yield('code')</span>
            </div>

            <div class="message">
                <p class="eyebrow"><span></span>@yield('eyebrow')</p>
                <h1>@yield('title')</h1>
                <p class="description">@yield('description')</p>
                <div class="actions">
                    <a class="button button-primary" href="{{ url('/') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m12 3-9 8v10h6v-6h6v6h6V11l-9-8Z" stroke-linejoin="round"/></svg>
                        {{ __('Retour à la galerie') }}
                    </a>
                    @hasSection('secondary-label')
                        <a class="button button-secondary" href="@yield('secondary-url')">@yield('secondary-label')<span aria-hidden="true">↗</span></a>
                    @endif
                </div>
                <p class="hint">@yield('hint', __('Le ciel a encore beaucoup à vous montrer.'))</p>
            </div>
        </main>

        <footer class="footer"><span>{{ __('CAPTURÉ DANS LE CIEL NANTAIS') }}</span><span>{{ __('GARDER LES YEUX VERS LES ÉTOILES') }} <span class="footer-star" aria-hidden="true">✦</span></span></footer>
    </div>
</body>
</html>
