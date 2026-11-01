@props(['title', 'eyebrow' => null])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="bg-bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __(':title · Deepsky Admin', ['title' => $title]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo/deepsky.svg') }}">
    <x-pwa-head />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php
    $isNightScope = request()->routeIs('admin.nightscope.*') || request()->routeIs('admin.celestial-objects.*');
    $nightScopeTabs = [
        ['admin.nightscope.tonight', __('Tonight')],
        ['admin.nightscope.forecast', __('Prévisions')],
        ['admin.nightscope.planner', __('Planner')],
        ['admin.nightscope.wishlist', __('Favoris')],
        ['admin.celestial-objects.index', __('Catalogue')],
    ];
@endphp
<body
    data-offline-user="{{ auth()->id() }}"
    class="min-h-screen overflow-x-hidden bg-bg font-[var(--font-main)] text-text-main antialiased"
    x-data="{ navigationOpen: false, nightScopeOpen: {{ $isNightScope ? 'true' : 'false' }} }"
    :class="navigationOpen && 'overflow-hidden lg:overflow-x-hidden'"
>
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_80%_-10%,rgba(88,166,255,.14),transparent_38%),radial-gradient(circle_at_0%_100%,rgba(63,185,80,.06),transparent_32%)]"></div>

    <div x-cloak x-show="navigationOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/70 backdrop-blur-sm lg:hidden" @click="navigationOpen = false"></div>

    <aside class="fixed inset-y-0 left-0 z-50 flex w-[min(18rem,88vw)] -translate-x-full flex-col border-r border-border bg-card-bg/95 pt-[calc(env(safe-area-inset-top,0px)+var(--pwa-top-spacing))] backdrop-blur-xl transition-transform duration-300 lg:w-72 lg:translate-x-0" :class="navigationOpen && 'translate-x-0'" style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="flex h-16 items-center justify-between border-b border-border px-4 sm:h-20 sm:px-6">
            <a wire:navigate href="{{ route('admin.dashboard') }}" class="flex min-w-0 items-center gap-3" @click="navigationOpen = false">
                <img src="{{ asset('assets/images/logo/deepsky.svg') }}" alt="{{ __('Logo Deepsky') }}" class="size-9 shrink-0 rounded-xl shadow-[0_0_24px_rgba(88,166,255,.16)] sm:size-10">
                <span class="min-w-0"><span class="block text-sm font-bold tracking-[.18em] text-text-bold">{{ __('DEEPSKY') }}</span><span class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.24em] text-accent">{{ __('Centre de contrôle') }}</span></span>
            </a>
            <button type="button" class="grid size-10 place-items-center rounded-xl border border-border text-text-main lg:hidden" @click="navigationOpen = false" aria-label="{{ __('Fermer le menu') }}">✕</button>
        </div>

        @php
            $icons = [
                'dashboard' => 'M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Zm10 0a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5ZM4 16a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3Zm10-4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-7Z',
                'photo' => 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Zm0 8 4.5-4.5a1.5 1.5 0 0 1 2 0L16 16m-2-2 1.5-1.5a1.5 1.5 0 0 1 2 0L20 15M15 9h.01',
                'equipment' => 'M14.5 4.5 19.5 9.5M12 7l5 5-7.5 7.5a2.1 2.1 0 0 1-3-3L12 7Zm-6 13 2-2M15 3l6 6',
                'rig' => 'M4 7h16M4 12h16M4 17h16M8 5v4M16 10v4M10 15v4',
                'location' => 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Zm0-9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
                'nightscope' => 'M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z',
                'tasks' => 'M9 6h11M9 12h11M9 18h11M4.5 6l.75.75L6.75 5M4.5 12l.75.75L6.75 11M4.5 18l.75.75L6.75 17',
                'newsletter' => 'M4 6h16v12H4V6Zm0 1 8 6 8-6',
                'settings' => 'M10.3 4.3a1.7 1.7 0 0 1 3.4 0 1.7 1.7 0 0 0 2.6 1.1 1.7 1.7 0 0 1 2.4 2.4 1.7 1.7 0 0 0 1 2.5 1.7 1.7 0 0 1 0 3.4 1.7 1.7 0 0 0-1 2.6 1.7 1.7 0 0 1-2.4 2.4 1.7 1.7 0 0 0-2.6 1 1.7 1.7 0 0 1-3.4 0 1.7 1.7 0 0 0-2.5-1 1.7 1.7 0 0 1-2.4-2.4 1.7 1.7 0 0 0-1.1-2.6 1.7 1.7 0 0 1 0-3.4 1.7 1.7 0 0 0 1.1-2.5 1.7 1.7 0 0 1 2.4-2.4 1.7 1.7 0 0 0 2.5-1.1ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
                'security' => 'M12 3 5 6v5c0 4.4 3 8.3 7 10 4-1.7 7-5.6 7-10V6l-7-3Zm-2.5 9 2 2 4-4',
            ];
            $sections = [
                __('Galerie') => [
                    ['admin.dashboard', __('Vue d’ensemble'), 'dashboard'],
                    ['admin.astrophotos.index', __('Astrophotos'), 'photo'],
                ],
                __('Matériel') => [
                    ['admin.equipment.index', __('Équipements'), 'equipment'],
                    ['admin.equipment-setups.index', __('Rigs'), 'rig'],
                    ['admin.observing-locations.index', __('Lieux d’observation'), 'location'],
                ],
                __('Observation') => 'nightscope',
                __('Système') => [
                    ['admin.tasks.index', __('Tâches'), 'tasks'],
                    ['admin.newsletter.index', __('Newsletter'), 'newsletter'],
                    ['admin.settings.edit', __('Paramètres'), 'settings'],
                    ['admin.security.edit', __('Sécurité'), 'security'],
                ],
            ];
            $nightScopeLinks = [
                ['admin.nightscope.tonight', __('Tonight')],
                ['admin.nightscope.forecast', __('Prévisions 7 nuits')],
                ['admin.nightscope.planner', __('Night Planner')],
                ['admin.nightscope.wishlist', __('Favoris · À photographier')],
                ['admin.celestial-objects.index', __('Catalogue céleste')],
            ];
            $isActive = fn (string $routeName): bool => request()->routeIs($routeName) || request()->routeIs(str_replace(['.index', '.edit'], '.*', $routeName));
        @endphp
        <nav class="sidebar-scroll flex-1 overflow-y-auto overscroll-contain px-3 py-3" aria-label="{{ __('Administration') }}">
            @foreach ($sections as $sectionTitle => $links)
                <p @class(['px-3 pb-1.5 font-[var(--font-mono)] text-[10px] uppercase tracking-[.22em] text-text-main/40', 'pt-1' => $loop->first, 'pt-4' => ! $loop->first])>{{ $sectionTitle }}</p>
                @if ($links === 'nightscope')
                    <button type="button" @click="nightScopeOpen = !nightScopeOpen" :aria-expanded="nightScopeOpen" @class([
                        'flex min-h-10 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                        'bg-accent/10 text-text-bold' => $isNightScope,
                        'text-text-main/70 hover:bg-white/[.04] hover:text-text-bold' => ! $isNightScope,
                    ])>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" @class(['size-[18px] shrink-0', 'text-accent' => $isNightScope, 'text-text-main/45' => ! $isNightScope]) aria-hidden="true"><path d="{{ $icons['nightscope'] }}"/></svg>
                        <span class="min-w-0 flex-1">{{ __('NightScope') }}</span>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4 text-text-main/45 transition-transform" :class="nightScopeOpen && 'rotate-180'" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg>
                    </button>
                    <div x-cloak x-show="nightScopeOpen" x-transition class="ml-[1.4rem] mt-0.5 grid gap-0.5 border-l border-border pl-3">
                        @foreach ($nightScopeLinks as [$routeName, $label])
                            <a wire:navigate href="{{ route($routeName) }}" @click="navigationOpen = false" @class([
                                'rounded-md px-3 py-2 text-[13px] transition',
                                'bg-accent/10 font-medium text-accent' => $isActive($routeName),
                                'text-text-main/60 hover:bg-white/[.04] hover:text-text-bold' => ! $isActive($routeName),
                            ])>{{ $label }}</a>
                        @endforeach
                    </div>
                @else
                    <div class="grid gap-0.5">
                        @foreach ($links as [$routeName, $label, $icon])
                            <a wire:navigate href="{{ route($routeName) }}" @click="navigationOpen = false" @if($isActive($routeName)) aria-current="page" @endif @class([
                                'flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                'bg-accent/10 text-text-bold shadow-[inset_2px_0_0_#58a6ff]' => $isActive($routeName),
                                'text-text-main/70 hover:bg-white/[.04] hover:text-text-bold' => ! $isActive($routeName),
                            ])>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" @class(['size-[18px] shrink-0', 'text-accent' => $isActive($routeName), 'text-text-main/45' => ! $isActive($routeName)]) aria-hidden="true"><path d="{{ $icons[$icon] }}"/></svg>
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="border-t border-border px-3 pt-3 pb-2">
            <div class="flex items-center gap-2.5">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-accent/15 text-xs font-bold text-accent" aria-hidden="true">{{ str(auth()->user()->name)->substr(0, 2)->upper() }}</span>
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-text-bold">{{ auth()->user()->name }}</span><span class="block truncate text-[11px] text-text-main/50">{{ auth()->user()->email }}</span></span>
                <a href="{{ route('welcome') }}" target="_blank" class="grid size-9 shrink-0 place-items-center rounded-lg text-text-main/60 transition hover:bg-white/[.05] hover:text-accent" title="{{ __('Voir le site') }}" aria-label="{{ __('Voir le site (nouvel onglet)') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/></svg>
                </a>
                <form action="{{ route('admin.logout') }}" method="POST" class="shrink-0">@csrf
                    <button class="grid size-9 place-items-center rounded-lg text-red-400/85 transition hover:bg-red-400/10 hover:text-red-300" title="{{ __('Déconnexion') }}" aria-label="{{ __('Déconnexion') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]" aria-hidden="true"><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3M14 16l4-4-4-4M18 12H9"/></svg>
                    </button>
                </form>
            </div>
            <div class="mt-2 flex items-center justify-between px-1 font-[var(--font-mono)] text-[10px] text-text-main/40">
                <a href="/offline.html" class="hover:text-accent">{{ __('Plans hors connexion ↗') }}</a>
                <x-locale-switcher class="tracking-normal" />
                <span>{{ config('app.version') }}</span>
            </div>
        </div>
    </aside>

    <main class="relative min-h-screen lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-border bg-bg/90 pt-[calc(env(safe-area-inset-top,0px)+var(--pwa-top-spacing))] backdrop-blur-xl">
            <div class="flex flex-col gap-3 px-4 py-3 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:gap-4 lg:px-10 lg:py-0 lg:h-20">
                <div class="flex min-w-0 items-center gap-3 lg:flex-1">
                    <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl border border-border bg-card-bg text-text-bold lg:hidden" @click="navigationOpen = true" aria-label="{{ __('Ouvrir le menu') }}">☰</button>
                    <div class="min-w-0 flex-1">
                        @if ($eyebrow)<p class="truncate font-[var(--font-mono)] text-[10px] uppercase tracking-[.2em] text-accent">{{ $eyebrow }}</p>@endif
                        <h1 class="truncate text-lg font-semibold text-text-bold sm:text-xl">{{ $title }}</h1>
                    </div>
                    <button type="button" data-night-mode-toggle aria-pressed="false" title="{{ __('Mode nuit rouge') }}" class="flex min-h-11 shrink-0 items-center gap-2 rounded-xl border border-border px-3 py-2 text-xs text-accent transition hover:border-accent/40">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.5A9.72 9.72 0 0 1 18 16.25 9.75 9.75 0 0 1 8.25 6.5c0-1.33.27-2.6.75-3.75A9.75 9.75 0 0 0 12 21.75a9.75 9.75 0 0 0 9.75-6.25Z"/></svg>
                        <span data-night-mode-label class="sr-only sm:not-sr-only">{{ __('Mode nuit rouge') }}</span>
                    </button>
                </div>
                @isset($actions)
                    <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:w-auto lg:justify-end">{{ $actions }}</div>
                @endisset
            </div>

            @if ($isNightScope)
                <nav class="scrollbar-none flex gap-2 overflow-x-auto px-4 pb-3 sm:px-6 lg:hidden" aria-label="{{ __('NightScope') }}">
                    @foreach ($nightScopeTabs as [$routeName, $label])
                        <a wire:navigate href="{{ route($routeName) }}" @class([
                            'shrink-0 rounded-full border px-3.5 py-2 text-xs font-medium transition',
                            'border-accent/30 bg-accent/15 text-accent' => request()->routeIs(str_replace('.index', '.*', $routeName)) || request()->routeIs($routeName) || ($routeName === 'admin.nightscope.planner' && request()->routeIs('admin.nightscope.plans.*')),
                            'border-border bg-card-bg text-text-main/65' => ! (request()->routeIs(str_replace('.index', '.*', $routeName)) || request()->routeIs($routeName) || ($routeName === 'admin.nightscope.planner' && request()->routeIs('admin.nightscope.plans.*'))),
                        ])>{{ $label }}</a>
                    @endforeach
                </nav>
            @endif
        </header>

        <div class="mx-auto max-w-[1600px] p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:p-6 lg:p-10">
            @session('success')<div class="mb-6 flex items-start gap-3 rounded-xl border border-green-400/25 bg-green-400/10 px-4 py-3 text-sm text-green-300"><span class="shrink-0">✓</span><span class="min-w-0">{{ $value }}</span></div>@endsession
            @session('warning')<div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-400/25 bg-amber-400/10 px-4 py-3 text-sm text-amber-200"><span class="shrink-0">!</span><span class="min-w-0">{{ $value }}</span></div>@endsession
            @session('error')<div class="mb-6 flex items-start gap-3 rounded-xl border border-red-400/25 bg-red-400/10 px-4 py-3 text-sm text-red-300"><span class="shrink-0">!</span><span class="min-w-0">{{ $value }}</span></div>@endsession
            @if ($errors->any())<div class="mb-6 rounded-xl border border-red-400/25 bg-red-400/10 px-4 py-3 text-sm text-red-300">{{ __('Certains champs doivent être corrigés.') }}</div>@endif
            {{ $slot }}
        </div>
    </main>
    @livewireScriptConfig
</body>
</html>
