@props(['title'])
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="bg-bg">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
    <title>{{ __(':title · Deepsky Admin', ['title' => $title]) }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo/deepsky.svg') }}">
    <x-pwa-head />
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body data-offline-clear x-data="liveSky(@js(\App\Support\LiveSky::config()))" @mousemove.window="inspect($event)"
    class="min-h-screen overflow-x-hidden bg-[#05080f] font-[var(--font-main)] text-text-main antialiased">
    <canvas x-ref="sky" aria-hidden="true" class="fixed inset-0 size-full"></canvas>
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(ellipse_at_center,transparent_40%,rgba(5,8,15,.55)_100%)]"></div>

    <div x-cloak x-show="hover" x-transition.opacity.duration.150ms
        :style="hover && `left: ${hover.x + 14}px; top: ${hover.y + 14}px`"
        class="pointer-events-none fixed z-30 rounded-md border border-accent/30 bg-[#05080f]/85 px-2.5 py-1 font-[var(--font-mono)] text-[11px] text-text-bold backdrop-blur" x-text="hover?.label"></div>

    <x-locale-switcher class="fixed right-4 top-[calc(1rem+env(safe-area-inset-top,0px))] z-20 bg-bg/70 backdrop-blur" />
    <div class="relative z-10 mx-auto grid grid-cols-1 min-h-screen max-w-6xl items-center gap-8 px-5 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-[calc(1.5rem+env(safe-area-inset-top,0px)+var(--pwa-top-spacing))] lg:grid-cols-[minmax(0,1fr)_26rem] lg:gap-16">
        <section class="pointer-events-none select-none">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assets/images/logo/deepsky.svg') }}" alt="" class="size-11 rounded-xl shadow-[0_0_40px_rgba(88,166,255,.3)]">
                <div>
                    <p class="text-xl font-bold tracking-[.2em] text-text-bold">{{ __('DEEPSKY') }}</p>
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.3em] text-accent">{{ __('Administration console') }}</p>
                </div>
            </div>
            <div class="mt-8 hidden lg:block">
                <p class="font-[var(--font-mono)] text-[11px] uppercase tracking-[.3em] text-accent/80">{{ __('● Ciel en direct') }}</p>
                <p class="mt-3 text-5xl font-bold tracking-tight text-text-bold">{{ __('Au-dessus de') }} <span x-text="location">{{ config('nightscope.live_sky.label') }}</span></p>
                <p class="mt-3 font-[var(--font-mono)] text-6xl font-light tabular-nums text-text-bold/90" x-text="localTime"></p>
            </div>
            <dl class="mt-5 flex flex-wrap gap-2 font-[var(--font-mono)] text-[11px] lg:mt-8">
                <div class="flex gap-1.5 rounded-full border border-white/10 bg-[#05080f]/60 px-3 py-1.5 backdrop-blur lg:hidden"><dt class="text-accent">◷</dt><dd><span x-text="location"></span> · <span x-text="localTime"></span></dd></div>
                <div class="flex gap-1.5 rounded-full border border-white/10 bg-[#05080f]/60 px-3 py-1.5 backdrop-blur"><dt class="text-text-main/50">{{ __('TSL') }}</dt><dd class="text-text-bold" x-text="siderealTime"></dd></div>
                <div class="rounded-full border border-white/10 bg-[#05080f]/60 px-3 py-1.5 text-text-bold backdrop-blur" x-text="twilight"></div>
                <div class="rounded-full border border-white/10 bg-[#05080f]/60 px-3 py-1.5 text-text-bold backdrop-blur" x-text="moonLabel"></div>
                <div class="flex gap-1.5 rounded-full border border-white/10 bg-[#05080f]/60 px-3 py-1.5 backdrop-blur"><dt class="text-text-main/50">{{ __('Zénith') }}</dt><dd class="text-text-bold" x-text="zenith"></dd></div>
                <div x-show="visibleTargets > 0" class="flex gap-1.5 rounded-full border border-accent/30 bg-accent/10 px-3 py-1.5 text-accent backdrop-blur"><span x-text="visibleTargets"></span> <span x-text="visibleTargets > 1 ? @js(__('de tes cibles sont levées')) : @js(__('de tes cibles est levée'))"></span></div>
            </dl>
            <p class="mt-5 hidden max-w-md text-sm text-text-main/45 lg:block">{{ __('Survole les étoiles pour les identifier. Les cercles pointillés sont les objets que tu as déjà photographiés.') }}</p>
        </section>

        <div class="w-full">
            {{ $slot }}
            <a href="{{ route('welcome') }}" class="mt-6 block text-center text-xs text-text-main/50 transition hover:text-accent">{{ __('← Retour au site') }}</a>
        </div>
    </div>
    @livewireScriptConfig
</body>
</html>
