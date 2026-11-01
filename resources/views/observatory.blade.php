@extends('layouts.app')

@section('title', __('Observatoire — Le ciel de Nantes en direct | Deepsky'))
@section('meta_description', __("Le ciel au-dessus de Nantes en temps réel : constellations, planètes, Lune, objets du portfolio observables cette nuit, et le carnet de bord des acquisitions."))
@section('og_title', __('Observatoire — Deepsky'))

@section('content')
    @php($chip = 'rounded-full border border-white/10 bg-[#05080f]/70 px-3 py-1.5 backdrop-blur')
    <section class="col-span-full" x-data="liveSky(@js($sky))" aria-labelledby="observatory-title">
        <div class="mb-6 flex flex-col gap-2">
            <p class="font-mono text-[0.7rem] uppercase tracking-[0.3rem] text-accent"><span class="motion-safe:animate-pulse">●</span> {{ __('Ciel en direct') }}</p>
            <h1 id="observatory-title" class="text-3xl font-bold tracking-tight text-text-bold sm:text-4xl">{{ __('Le ciel au-dessus de :value', ['value' => $sky['label']]) }}</h1>
            <p class="max-w-2xl text-text-main/75">{{ __('Les étoiles, constellations, planètes et la Lune telles qu’elles sont placées en ce moment. Les cercles pointillés marquent les objets du portfolio : clique dessus pour voir la photo. Molette, double-clic ou pincement pour zoomer.') }}</p>
        </div>

        <div x-data="skyAr(@js($sky))" x-show="supported" x-cloak class="mb-4">
            <button type="button" @click="start()"
                class="flex w-full items-center justify-center gap-3 rounded-2xl border border-accent/40 bg-accent/10 px-5 py-4 text-left text-sm font-semibold text-text-bold transition hover:bg-accent/20">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" class="size-6 shrink-0 text-accent"><rect x="7" y="3" width="10" height="18" rx="2.5"/><path stroke-linecap="round" d="M11 18h2M12 1v-.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 6 1.5-1.5M4.5 6 3 4.5M12 7.5l.9 1.8 2 .3-1.45 1.4.35 2-1.8-.95-1.8.95.35-2-1.45-1.4 2-.3z"/></svg>
                <span>{{ __('Lève les yeux') }} <span class="block text-xs font-normal text-text-main/70">{{ __('Pointe ton téléphone vers le ciel : il te montre les constellations, les planètes et les objets du portfolio, là où ils sont vraiment.') }}</span></span>
            </button>
            <p x-show="status" x-text="status" class="mt-2 text-xs text-amber-200"></p>

            <template x-teleport="body">
                <div x-show="active" x-cloak class="fixed inset-0 z-[3000] bg-black text-text-main" role="dialog" aria-modal="true" aria-label="{{ __('Mode Lève les yeux') }}">
                    <video x-ref="camera" playsinline muted class="absolute inset-0 size-full object-cover" :class="cameraOn || 'hidden'"></video>
                    <canvas x-ref="ar" @click="tap($event)" class="absolute inset-0 size-full"></canvas>
                    <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-4 pt-[calc(1rem+env(safe-area-inset-top,0px))]">
                        <p class="rounded-2xl border border-white/10 bg-black/60 px-4 py-2 text-sm text-text-bold backdrop-blur" aria-live="polite" x-text="guide"></p>
                        <button type="button" @click="stop()" aria-label="{{ __('Quitter le mode Lève les yeux') }}" class="grid size-11 shrink-0 place-items-center rounded-full border border-white/15 bg-black/60 text-text-bold backdrop-blur">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" class="size-5"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 grid gap-3 p-4 pb-[calc(1rem+env(safe-area-inset-bottom,0px))]">
                        <div class="scrollbar-none flex gap-2 overflow-x-auto">
                            <template x-for="(target, index) in targets()" :key="target.label">
                                <button type="button" @click="targetIndex = index" x-text="target.label"
                                    :class="index === targetIndex ? 'border-accent bg-accent text-bg' : 'border-white/15 bg-black/60 text-text-bold'"
                                    class="shrink-0 rounded-full border px-3 py-1.5 font-mono text-xs backdrop-blur"></button>
                            </template>
                        </div>
                        <div class="flex items-center justify-between gap-3 text-xs text-text-main/70">
                            <span>{{ __('Calibre la boussole en dessinant un 8 avec le téléphone si le ciel semble décalé.') }}</span>
                            <button type="button" @click="toggleCamera()" class="shrink-0 rounded-full border border-white/15 bg-black/60 px-3 py-1.5 text-text-bold backdrop-blur" x-text="cameraOn ? @js(__('Masquer la caméra')) : @js(__('Afficher la caméra'))"></button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="relative h-[min(78vh,760px)] min-h-[440px] overflow-hidden rounded-2xl border border-border bg-[#05080f] shadow-[0_30px_80px_-30px_rgba(0,0,0,.9)]">
            <canvas x-ref="sky" class="absolute inset-0 size-full select-none" :class="hover?.url ? 'cursor-pointer' : (zoom > 1 ? 'cursor-grab active:cursor-grabbing' : 'cursor-crosshair')"
                :style="`touch-action: ${zoom > 1 ? 'none' : 'pan-y'}`"
                @pointerdown="onPointerDown($event)" @pointermove="onPointerMove($event)" @pointerup="onPointerUp($event)" @pointercancel="onPointerUp($event)"
                @mouseleave="hover = null" @click="openHovered()" @wheel.prevent="onWheel($event)" @dblclick.prevent="zoomIn()"
                role="img" aria-label="{{ __('Carte du ciel en direct au-dessus de :place', ['place' => $sky['label']]) }}"></canvas>

            <div class="absolute right-3 top-3 z-10 flex flex-col overflow-hidden rounded-xl border border-white/10 bg-[#05080f]/75 backdrop-blur sm:right-4 sm:top-4">
                @foreach ([['zoomIn()', __('Zoomer'), 'M12 5v14M5 12h14', 'zoom >= 8'], ['zoomOut()', __('Dézoomer'), 'M5 12h14', 'zoom <= 1'], ['resetView()', __('Vue d’ensemble'), 'M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5', 'zoom <= 1']] as [$action, $label, $path, $disabled])
                    <button type="button" @click="{{ $action }}" :disabled="{{ $disabled }}" aria-label="{{ $label }}" title="{{ $label }}"
                        class="grid size-10 place-items-center border-b border-white/10 text-text-bold transition-colors last:border-b-0 hover:bg-accent/15 hover:text-accent disabled:opacity-30 disabled:hover:bg-transparent">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="size-4" aria-hidden="true"><path d="{{ $path }}" /></svg>
                    </button>
                @endforeach
            </div>
            <p x-cloak x-show="zoom > 1" class="pointer-events-none absolute right-16 top-4 hidden rounded-full border border-white/10 bg-[#05080f]/75 px-3 py-1.5 font-mono text-[11px] text-text-bold backdrop-blur sm:block" x-text="`× ${zoom.toFixed(1)} · ` + @js(__('glisser pour se déplacer'))"></p>

            <div x-cloak x-show="hover" :style="hover && `left: ${hover.x + 14}px; top: ${hover.y + 14}px`"
                class="pointer-events-none absolute z-10 rounded-md border border-accent/30 bg-[#05080f]/90 px-2.5 py-1 font-mono text-[11px] text-text-bold backdrop-blur">
                <span x-text="hover?.label"></span><span x-show="hover?.url" class="text-accent"> {{ __('· voir la photo →') }}</span>
            </div>

            <dl class="pointer-events-none absolute left-3 top-3 flex max-w-[calc(100%-1.5rem)] flex-wrap gap-2 font-mono text-[11px] text-text-bold sm:left-4 sm:top-4">
                <div class="{{ $chip }} flex gap-1.5"><dt class="text-accent">◷</dt><dd x-text="localTime"></dd></div>
                <div class="{{ $chip }} flex gap-1.5"><dt class="text-text-main/50">{{ __('TSL') }}</dt><dd x-text="siderealTime"></dd></div>
                <div class="{{ $chip }}" x-text="twilight"></div>
                <div class="{{ $chip }} hidden sm:block" x-text="moonLabel"></div>
                <div class="{{ $chip }} hidden flex-row gap-1.5 sm:flex"><dt class="text-text-main/50">{{ __('Zénith') }}</dt><dd x-text="zenith"></dd></div>
            </dl>

            <div class="absolute inset-x-3 bottom-3 flex flex-wrap items-center gap-3 rounded-xl border border-white/10 bg-[#05080f]/75 px-4 py-3 backdrop-blur sm:inset-x-4 sm:bottom-4">
                <label for="sky-offset" class="font-mono text-[11px] uppercase tracking-[0.15rem] text-text-main/70">{{ __('Avancer dans la nuit') }}</label>
                <input id="sky-offset" type="range" min="0" max="720" step="10" x-model.number="offsetMinutes" class="min-w-40 flex-1 accent-accent">
                <span class="w-28 font-mono text-[12px] tabular-nums text-text-bold" x-text="offsetMinutes == 0 ? @js(__('Maintenant')) : `+${Math.floor(offsetMinutes / 60)} h ${String(offsetMinutes % 60).padStart(2, '0')} · ${localTime}`">{{ __('Maintenant') }}</span>
                <button type="button" x-show="offsetMinutes != 0" x-cloak @click="offsetMinutes = 0"
                    class="rounded-full border border-accent/40 px-3 py-1 text-xs text-accent transition-colors hover:bg-accent/10">{{ __('Revenir à maintenant') }}</button>
            </div>
        </div>

        <template x-if="night">
            <div class="mt-8">
                <h2 class="mb-5 border-b border-border pb-2 text-[0.8rem] font-bold uppercase tracking-[0.2rem] text-accent">{{ __('Cette nuit à :value', ['value' => $sky['label']]) }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-xl border border-border bg-card-bg p-5">
                        <p class="font-mono text-[0.65rem] uppercase tracking-[0.15rem] text-text-main/55">{{ __('Soleil') }}</p>
                        <p class="mt-3 text-2xl font-semibold text-text-bold"><span x-text="formatTime(night.sunset)"></span> <span class="text-base font-normal text-text-main/50">→</span> <span x-text="formatTime(night.sunrise)"></span></p>
                        <p class="mt-1 text-sm text-text-main/60">{{ __('coucher et lever') }}</p>
                    </article>
                    <article class="rounded-xl border border-border bg-card-bg p-5">
                        <p class="font-mono text-[0.65rem] uppercase tracking-[0.15rem] text-text-main/55">{{ __('Nuit noire') }}</p>
                        <p class="mt-3 text-2xl font-semibold text-text-bold" x-text="night.darkStart ? (night.alreadyDark ? @js(__('Jusqu’à')) + ' ' + formatTime(night.darkEnd) : formatTime(night.darkStart) + ' → ' + formatTime(night.darkEnd)) : @js(__('Aucune'))"></p>
                        <p class="mt-1 text-sm text-text-main/60" x-text="night.darkStart ? @js(__('Soleil à plus de 18° sous l’horizon')) : @js(__('Le Soleil ne descend pas assez bas cette nuit'))"></p>
                    </article>
                    <article class="rounded-xl border border-border bg-card-bg p-5">
                        <p class="font-mono text-[0.65rem] uppercase tracking-[0.15rem] text-text-main/55">{{ __('Lune') }}</p>
                        <p class="mt-3 text-2xl font-semibold text-text-bold"><span x-text="Math.round(night.moon.illumination * 100)"></span> % <span class="text-base font-normal text-text-main/60" x-text="night.moon.waxing ? @js(__('croissante')) : @js(__('décroissante'))"></span></p>
                        <p class="mt-1 text-sm text-text-main/60">{{ __('lever') }} <span x-text="formatTime(night.moon.rise)"></span> {{ __('· coucher') }} <span x-text="formatTime(night.moon.set)"></span></p>
                    </article>
                    <article class="rounded-xl border border-border bg-card-bg p-5">
                        <p class="font-mono text-[0.65rem] uppercase tracking-[0.15rem] text-text-main/55">{{ __('Planètes visibles') }}</p>
                        <ul class="mt-3 space-y-1.5 text-sm">
                            <template x-for="planet in night.planets" :key="planet.key">
                                <li class="flex justify-between gap-3"><span class="font-medium text-text-bold" :style="`color: ${planet.color}`" x-text="planet.name"></span><span class="font-mono text-text-main/60" x-text="'vers ' + formatTime(planet.date) + ' · ' + Math.round(planet.altitude) + '°'"></span></li>
                            </template>
                            <li x-show="! night.planets.length" class="text-text-main/60">{{ __('Aucune planète bien placée cette nuit.') }}</li>
                        </ul>
                    </article>
                </div>

                <div class="mt-6 rounded-xl border border-border bg-card-bg p-5" x-show="night.targets.length">
                    <p class="font-mono text-[0.65rem] uppercase tracking-[0.15rem] text-text-main/55">{{ __('Objets du portfolio observables cette nuit') }}</p>
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="target in night.targets" :key="target.label">
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-border/70 bg-bg/40 px-4 py-3">
                                <a :href="target.url" wire:navigate class="min-w-0 group">
                                    <span class="block font-mono text-xs text-accent" x-text="target.label"></span>
                                    <span class="block truncate text-sm font-medium text-text-bold group-hover:text-accent" x-text="target.name || target.title"></span>
                                    <span class="block text-xs text-text-main/60" x-text="@js(__('culmine vers :time à :altitude°')).replace(':time', formatTime(target.date)).replace(':altitude', Math.round(target.altitude))"></span>
                                </a>
                                <button type="button" title="{{ __('Afficher le ciel à ce moment') }}"
                                    @click="offsetMinutes = Math.max(0, Math.min(720, Math.round((target.date - Date.now()) / 600000) * 10)); $root.scrollIntoView({behavior: 'smooth'})"
                                    class="shrink-0 rounded-full border border-border px-3 py-1.5 text-xs text-text-main/80 transition-colors hover:border-accent hover:text-accent">{{ __('⌖ Voir') }}</button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </template>
    </section>

    <div id="carnet-de-bord" class="col-span-full mt-4 scroll-mt-8 space-y-12">
        <h2 class="text-2xl font-bold tracking-tight text-text-bold">{{ __('Carnet de bord') }}</h2>

        {{-- Filtre par année --}}
        @if ($availableYears->isNotEmpty())
            <nav class="flex flex-wrap gap-2 font-mono text-[0.7rem] uppercase tracking-[0.15rem]">
                <a wire:navigate href="{{ route('observatory') }}#carnet-de-bord"
                    class="px-4 py-1.5 rounded-full border transition-colors {{ $selectedYear === null ? 'border-accent text-accent' : 'border-border text-text-main hover:border-accent hover:text-accent' }}">
                    {{ __('Tout') }}
                </a>
                @foreach ($availableYears as $yearOption)
                    <a wire:navigate href="{{ route('observatory', ['year' => $yearOption]) }}#carnet-de-bord"
                        class="px-4 py-1.5 rounded-full border transition-colors {{ $selectedYear === $yearOption ? 'border-accent text-accent' : 'border-border text-text-main hover:border-accent hover:text-accent' }}">
                        {{ $yearOption }}
                    </a>
                @endforeach
            </nav>
        @endif

        {{-- Tuiles de totaux --}}
        <section>
            <h2 class="text-accent uppercase text-[0.8rem] tracking-[0.2rem] mb-6 border-b border-border pb-2 font-bold">
                {{ __('MISSION_LOG // depuis :value', ['value' => $totals['since']?->format('m/Y') ?? '—']) }}
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach ([
                    ['label' => __('Photos publiées'), 'value' => $totals['photos']],
                    ['label' => __('Heures d’intégration'), 'value' => $totals['hours'].'h'],
                    ['label' => __('Cibles capturées'), 'value' => $totals['targets']],
                    ['label' => __('Vues cumulées'), 'value' => Illuminate\Support\Number::format($totals['views'], locale: app()->getLocale())],
                    ['label' => 'Likes', 'value' => $totals['likes']],
                    ['label' => __('Première capture'), 'value' => $totals['since']?->format(__('d/m/Y')) ?? '—'],
                ] as $tile)
                    <div class="bg-card-bg border border-border rounded-xl p-5 hover:border-accent transition-colors">
                        <p class="text-[clamp(1.1rem,4vw,1.8rem)] font-bold text-text-bold font-mono leading-none break-words">{{ $tile['value'] }}</p>
                        <p class="text-[0.65rem] text-accent font-mono uppercase tracking-[0.15rem] mt-2">{{ $tile['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Heatmap des nuits d'acquisition --}}
        <section>
            <h2 class="text-accent uppercase text-[0.8rem] tracking-[0.2rem] mb-6 border-b border-border pb-2 font-bold">
                {{ __('NUITS D\'ACQUISITION // :value', ['value' => $selectedYear ?? now()->year]) }}
            </h2>
            <div class="bg-card-bg border border-border rounded-xl p-5 overflow-x-auto" x-data="{ selectedDay: null }">
                <p class="text-[0.7rem] font-mono text-text-main mb-4">
                    {{ __('Chaque carré représente une nuit : plus il est lumineux, plus les heures d\'acquisition ont été nombreuses. Cliquez sur un carré pour voir sa date.') }}
                </p>
                <div class="flex gap-[3px] w-max">
                    @foreach ($heatmap as $week)
                        <div class="flex flex-col gap-[3px]">
                            <span class="h-4 text-[0.55rem] font-mono text-text-main uppercase whitespace-nowrap overflow-visible">{{ $week['month'] }}</span>
                            @foreach ($week['days'] as $day)
                                @if ($day['level'] === -1)
                                    <div class="w-[11px] h-[11px]"></div>
                                    @continue
                                @endif
                                <button type="button"
                                    @click="selectedDay = {{ Js::from($day['date'].match (true) {
                                        $day['hours'] > 0 => ' — '.$day['hours']."h d'acquisition",
                                        $day['level'] > 0 => __(" — nuit d'acquisition (durée non renseignée)"),
                                        default => __(' — aucune acquisition'),
                                    }) }}"
                                    title="{{ $day['date'] }}{{ $day['hours'] > 0 ? ' — '.$day['hours'].'h' : '' }}"
                                    class="w-[11px] h-[11px] rounded-[2px] cursor-pointer {{ match ($day['level']) {
                                        0 => 'bg-border/40',
                                        1 => 'bg-accent/25',
                                        2 => 'bg-accent/50',
                                        3 => 'bg-accent/75',
                                        default => 'bg-accent',
                                    } }}"></button>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <p x-show="selectedDay" x-text="selectedDay" class="mt-4 text-[0.75rem] font-mono text-accent" x-cloak></p>
                <div class="flex items-center gap-2 mt-4 text-[0.6rem] font-mono text-text-main uppercase tracking-widest">
                    <span>{{ __('Moins') }}</span>
                    <div class="w-[11px] h-[11px] rounded-[2px] bg-border/40"></div>
                    <div class="w-[11px] h-[11px] rounded-[2px] bg-accent/25"></div>
                    <div class="w-[11px] h-[11px] rounded-[2px] bg-accent/50"></div>
                    <div class="w-[11px] h-[11px] rounded-[2px] bg-accent/75"></div>
                    <div class="w-[11px] h-[11px] rounded-[2px] bg-accent"></div>
                    <span>{{ __('Plus') }}</span>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Répartition par filtre --}}
            <section>
                <h2 class="text-accent uppercase text-[0.8rem] tracking-[0.2rem] mb-6 border-b border-border pb-2 font-bold">
                    {{ __('INTÉGRATION PAR FILTRE') }}
                </h2>
                <div class="space-y-4">
                    @forelse ($byFilter as $name => $row)
                        <x-stat-bar :name="$name" :row="$row" />
                    @empty
                        <p class="text-[0.8rem] font-mono text-text-main">{{ __('Aucune donnée.') }}</p>
                    @endforelse
                </div>
            </section>

            {{-- Top cibles --}}
            <section>
                <h2 class="text-accent uppercase text-[0.8rem] tracking-[0.2rem] mb-6 border-b border-border pb-2 font-bold">
                    {{ __('TOP CIBLES') }}
                </h2>
                <div class="space-y-4">
                    @forelse ($topTargets as $name => $row)
                        <x-stat-bar :name="$name" :row="$row" />
                    @empty
                        <p class="text-[0.8rem] font-mono text-text-main">{{ __('Aucune donnée.') }}</p>
                    @endforelse
                </div>
            </section>

            {{-- Par tube optique --}}
            <section>
                <h2 class="text-accent uppercase text-[0.8rem] tracking-[0.2rem] mb-6 border-b border-border pb-2 font-bold">
                    {{ __('PAR TUBE OPTIQUE') }}
                </h2>
                <div class="space-y-4">
                    @forelse ($byTube as $name => $row)
                        <x-stat-bar :name="$name" :row="$row" />
                    @empty
                        <p class="text-[0.8rem] font-mono text-text-main">{{ __('Aucune donnée.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

    </div>
@endsection
