<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @php
        $defaultTitle = __('Deepsky — Astrophotographie du ciel profond à Nantes');
        $canonicalQuery = request()->routeIs('welcome')
            ? request()->only(['search', 'type', 'constellation', 'year', 'equipment', 'sort', 'page'])
            : (request()->routeIs('observatory') ? ['year' => $selectedYear ?? null] : []);
        $canonicalQuery = array_filter($canonicalQuery, fn ($value, $key) => filled($value) && ($key !== 'page' || (int) $value > 1), ARRAY_FILTER_USE_BOTH);
        ksort($canonicalQuery);
        $canonicalUrl = url()->current().($canonicalQuery ? '?'.http_build_query($canonicalQuery) : '');
    @endphp
    <title>@yield('title', $defaultTitle)</title>
    <link rel="canonical" href="{{ App\Support\Locales::url(app()->getLocale(), url()->current(), $canonicalQuery) }}">
    @foreach (App\Support\Locales::codes() as $alternateLocale)
        <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ App\Support\Locales::url($alternateLocale, url()->current(), $canonicalQuery) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $canonicalUrl }}">

    @php($defaultDescription = __("Portfolio d'astrophotographie amateur : galaxies, nébuleuses et amas du ciel profond capturés depuis le ciel nantais, avec leurs données techniques."))

    <meta name="description" content="@yield('meta_description', $defaultDescription)">
    <meta name="robots" content="@yield('robots', 'index,follow')">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="DEEPSKY">
    <meta property="og:title" content="@yield('og_title', $defaultTitle)">
    <meta property="og:description" content="@yield('meta_description', $defaultDescription)">
    <meta property="og:image" content="@yield('og_image', asset('assets/images/logo/deepsky.svg'))">
    <meta property="og:url" content="{{ App\Support\Locales::url(app()->getLocale(), url()->current(), $canonicalQuery) }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_US' : 'fr_FR' }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="@yield('og_image', asset('assets/images/logo/deepsky.svg'))">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;700&family=JetBrains+Mono:wght@300;500&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo/deepsky.svg') }}">
    <x-pwa-head />
    <x-analytics />

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @livewireStyles
</head>

<body x-data="{ galleryFiltersOpen: false }" class="deepsky-sky bg-bg text-text-main font-['Space_Grotesk'] px-1 py-6 pt-[calc(1.5rem+env(safe-area-inset-top,0px)+var(--pwa-top-spacing))] sm:p-8 sm:pt-[calc(2rem+env(safe-area-inset-top,0px)+var(--pwa-top-spacing))] min-h-screen">
    <a href="#contenu"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[3000] focus:rounded-full focus:bg-accent focus:px-4 focus:py-2 focus:text-sm focus:font-bold focus:text-bg">{{ __('Aller au contenu') }}</a>
    <header
        class="max-w-[1400px] mx-auto mb-10 sm:mb-12 flex justify-between items-start md:items-end gap-3 px-4">
        <div class="brand min-w-0">
            @php($brandTag = request()->routeIs('astrophoto.show', 'observatory', 'time-machine') ? 'p' : 'h1')
            <{{ $brandTag }} class="text-4xl md:text-[2.5rem] text-text-bold font-bold tracking-tighter">
                <a wire:navigate href="{{ route('welcome') }}" class="transition-colors hover:text-accent">{{ __('DEEPSKY') }}</a>
            </{{ $brandTag }}>
            <p class="font-light text-accent text-[0.9rem] uppercase tracking-widest">
                {{ __('Capturé dans le ciel nantais') }}
            </p>
            <nav class="mt-3 flex flex-wrap gap-x-5 gap-y-1 font-mono text-[0.7rem] uppercase tracking-[0.2rem]">
                <a wire:navigate href="{{ route('welcome') }}" @if (request()->routeIs('welcome')) aria-current="page" @endif
                    class="border-b py-1 transition-colors {{ request()->routeIs('welcome') ? 'border-accent text-accent' : 'border-transparent text-text-main hover:text-accent' }}">{{ __('Galerie') }}</a>
                <a wire:navigate href="{{ route('observatory') }}" @if (request()->routeIs('observatory')) aria-current="page" @endif
                    class="border-b py-1 transition-colors {{ request()->routeIs('observatory') ? 'border-accent text-accent' : 'border-transparent text-text-main hover:text-accent' }}">{{ __('Observatoire') }}</a>
                <a wire:navigate href="{{ route('time-machine') }}" @if (request()->routeIs('time-machine')) aria-current="page" @endif
                    class="border-b py-1 transition-colors {{ request()->routeIs('time-machine') ? 'border-accent text-accent' : 'border-transparent text-text-main hover:text-accent' }}">{{ __('Remonter le temps') }}</a>
            </nav>
        </div>
        <div class="flex shrink-0 items-center gap-2">
        <x-locale-switcher />
        @if (request()->routeIs('welcome'))
            <button type="button" x-ref="filtersToggle"
                @click="galleryFiltersOpen = !galleryFiltersOpen"
                :aria-expanded="galleryFiltersOpen" aria-expanded="false" aria-controls="gallery-filters"
                :aria-label="galleryFiltersOpen ? @js(__('Masquer les filtres')) : @js(__('Afficher les filtres'))"
                aria-label="{{ __('Afficher les filtres') }}" title="{{ __('Recherche et filtres') }}"
                :class="galleryFiltersOpen ? 'bg-accent/10 text-accent' : 'text-text-main'"
                class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-full transition-colors hover:bg-accent/10 hover:text-accent focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-5">
                    <circle cx="10.5" cy="10.5" r="6.5" />
                    <path stroke-linecap="round" d="m16 16 4.5 4.5" />
                </svg>
            </button>
        @endif
        </div>
    </header>

    <main id="contenu" tabindex="-1" class="outline-none grid grid-cols-1 md:grid-cols-[repeat(auto-fill,minmax(400px,1fr))] gap-8 max-w-[1400px] mx-auto px-4">
        @yield('content')
    </main>

    <footer class="max-w-[1400px] mx-auto mt-12 px-4 py-12 border-t border-border/70 flex flex-col items-center">
        <div class="max-w-2xl mb-10 text-center">
            <p class="text-[0.9rem] text-text-main font-sans italic leading-relaxed">
                {{ __('Astrophotographe amateur nantais, je capture les lueurs du ciel profond depuis mon jardin. Ce portfolio est un carnet de bord technique de mes explorations au-delà de l\'atmosphère, entre nébuleuses diffuses et galaxies lointaines.') }}
            </p>
        </div>

        @unless (request()->routeIs('astrophoto.show') && $photo->is_published)
            <div class="mb-10 w-full max-w-md">
                <x-newsletter-opt-in compact />
            </div>
        @endunless
        <p class="text-text-main/60 text-[0.7rem] font-mono tracking-[0.1rem] uppercase text-center">
            {{ __('© :value DEEPSKY // CAPTURÉ DANS LE CIEL NANTAIS', ['value' => date('Y')]) }}
        </p>

        <a wire:navigate href="{{ route('admin.dashboard') }}" aria-label="{{ __('Administration') }}" title="{{ __('Administration') }}" rel="nofollow"
            class="mt-8 inline-flex items-center justify-center text-border transition-all duration-300 hover:text-accent hover:-translate-y-0.5">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"
                class="w-[1.2rem] h-[1.2rem]">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </a>
    </footer>

    @stack('scripts')
    @livewireScriptConfig
</body>

</html>
