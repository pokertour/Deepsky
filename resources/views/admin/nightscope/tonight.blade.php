<x-admin.layout title="{{ __('Tonight') }}" eyebrow="{{ __('NightScope · Que photographier ce soir ?') }}">
    <x-slot:actions>
        <form method="GET" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            <input type="hidden" name="search" value="{{ $data->search }}">
            <input type="hidden" name="observing_location_id" value="{{ $data->location?->id }}">
            <input type="hidden" name="equipment_setup_id" value="{{ $data->equipmentSetup?->id }}">
            <input type="hidden" name="favorites_only" value="{{ $data->favoritesOnly ? 1 : 0 }}">
            @foreach ($data->objectTypes as $type)
                <input type="hidden" name="types[]" value="{{ $type }}">
            @endforeach
            @if($data->neverPhotographedOnly)
                <input type="hidden" name="never_photographed" value="1">
            @endif
            <input type="hidden" name="limit" value="{{ $data->recommendationLimit }}">
            <input type="date" name="date" value="{{ $data->date->format('Y-m-d') }}" class="min-h-11 min-w-0 flex-1 rounded-xl border border-border bg-card-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent sm:flex-none">
            <button class="min-h-11 shrink-0 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ __('Calculer') }}</button>
        </form>
    </x-slot:actions>

    @if (! $data->location)
        <section class="rounded-2xl border border-amber-400/25 bg-amber-400/10 p-6">
            <p class="font-semibold text-amber-200">{{ __('Lieu d’observation requis') }}</p>
            <p class="mt-2 text-sm text-text-main/65">{{ __('Configure un lieu pour calculer la nuit et la visibilité des cibles.') }}</p>
            <a wire:navigate href="{{ route('admin.observing-locations.create') }}" class="mt-5 inline-flex rounded-xl bg-amber-300 px-4 py-3 text-sm font-semibold text-slate-950">{{ __('Ajouter un lieu') }}</a>
        </section>
    @else
        <form method="GET" class="mb-5 rounded-2xl border border-border bg-card-bg p-4">
            <input type="hidden" name="date" value="{{ $data->date->format('Y-m-d') }}">
            <input type="hidden" name="limit" value="{{ config('nightscope.tonight.recommendation_limit', 12) }}">
            <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="text-sm text-text-main">{{ __('Lieu d’observation') }}
                    <select name="observing_location_id" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-bg px-3 text-text-bold">
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected($data->location?->is($location))>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm text-text-main">{{ __('Rig') }}
                    <select name="equipment_setup_id" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-bg px-3 text-text-bold">
                        <option value="" @selected(! $data->equipmentSetup)>{{ __('Sans Rig') }}</option>
                        @foreach($equipmentSetups as $setup)
                            <option value="{{ $setup->id }}" @selected($data->equipmentSetup?->is($setup))>{{ $setup->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="mb-4">
                <label for="tonight-search" class="mb-2 block text-sm font-medium text-text-bold">{{ __('Rechercher une cible') }}</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="tonight-search" type="search" name="search" value="{{ $data->search }}" maxlength="100" placeholder="{{ __('Nom, M31, NGC 7000, constellation…') }}" class="min-h-11 min-w-0 flex-1 rounded-xl border border-border bg-bg px-4 py-2 text-sm text-text-bold outline-none placeholder:text-text-main/45 focus:border-accent">
                    <button type="submit" class="min-h-11 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ __('Rechercher') }}</button>
                    @if($data->search !== '')
                        <a wire:navigate href="{{ route('admin.nightscope.tonight', ['date' => $data->date->format('Y-m-d'), 'types' => $data->objectTypes, 'never_photographed' => $data->neverPhotographedOnly ? 1 : null, 'observing_location_id' => $data->location?->id, 'equipment_setup_id' => $data->equipmentSetup?->id ?? '', 'favorites_only' => $data->favoritesOnly ? 1 : null]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-border px-4 py-2 text-sm text-text-main transition hover:border-accent/30 hover:text-accent">{{ __('Effacer') }}</a>
                    @endif
                </div>
                @error('search')<p class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Filtres Tonight') }}</p>
                    <p class="mt-1 text-xs text-text-main/45">{{ __('Types d’objets et cibles jamais photographiées (le tri reste par score).') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex min-h-10 items-center gap-2 rounded-full border border-accent/30 px-3 py-2 text-xs text-accent">
                        <input type="checkbox" name="favorites_only" value="1" @checked($data->favoritesOnly) onchange="this.form.submit()" class="size-4 rounded border-border bg-bg text-accent">
                        {{ __('Mes favoris') }}
                    </label>
                    <a wire:navigate href="{{ route('admin.nightscope.wishlist') }}" class="inline-flex min-h-10 items-center px-2 text-xs text-accent">{{ __('Gérer mes favoris →') }}</a>
                    @foreach (App\Enums\CelestialObjectType::cases() as $type)
                        <label @class([
                            'inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-medium transition',
                            'border-accent/35 bg-accent/15 text-accent' => in_array($type->value, $data->objectTypes, true),
                            'border-border bg-bg text-text-main/65 hover:border-accent/30 hover:text-text-bold' => ! in_array($type->value, $data->objectTypes, true),
                        ])>
                            <input
                                type="checkbox"
                                name="types[]"
                                value="{{ $type->value }}"
                                class="size-4 rounded border-border bg-bg text-accent focus:ring-accent/30"
                                @checked(in_array($type->value, $data->objectTypes, true))
                                onchange="this.form.submit()"
                            >
                            {{ $type->label() }}
                        </label>
                    @endforeach
                    <label @class([
                        'inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-medium transition',
                        'border-green-400/35 bg-green-400/10 text-green-300' => $data->neverPhotographedOnly,
                        'border-border bg-bg text-text-main/65 hover:border-accent/30 hover:text-text-bold' => ! $data->neverPhotographedOnly,
                    ])>
                        <input
                            type="checkbox"
                            name="never_photographed"
                            value="1"
                            class="size-4 rounded border-border bg-bg text-accent focus:ring-accent/30"
                            @checked($data->neverPhotographedOnly)
                            onchange="this.form.submit()"
                        >
                        {{ __('Jamais photographié') }}
                    </label>
                </div>
            </div>
        </form>
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-2xl border border-accent/25 bg-gradient-to-br from-accent/15 to-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('NightScore') }}</p>
                <div class="mt-3 flex items-end gap-2"><strong class="text-4xl text-text-bold">{{ $data->nightScore->score }}</strong><span class="pb-1 text-sm text-text-main/45">/100</span></div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-bg"><div class="h-full rounded-full bg-accent" style="width: {{ $data->nightScore->score }}%"></div></div>
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-text-main/45">{{ __('Nuit astronomique') }}</p>
                @if ($data->night->hasAstronomicalNight())
                    <p class="mt-3 text-xl font-semibold text-text-bold">{{ $data->night->astronomicalNightStart->format('H:i') }} → {{ $data->night->astronomicalNightEnd->format('H:i') }}</p>
                    <p class="mt-2 text-xs text-text-main/50">{{ __(':value h :value2 min exploitables', ['value' => intdiv($data->nightScore->darknessMinutes, 60), 'value2' => $data->nightScore->darknessMinutes % 60]) }}</p>
                @elseif ($data->night->usesNauticalFallback())
                    <p class="mt-3 text-xl font-semibold text-text-bold">{{ $data->night->observingWindow()[0]->format('H:i') }} → {{ $data->night->observingWindow()[1]->format('H:i') }}</p>
                    <p class="mt-2 text-xs text-amber-200">{{ __('Pas de nuit astronomique : cibles calculées sur la nuit nautique (Soleil sous −12°), idéale pour la bande étroite.') }}</p>
                @else
                    <p class="mt-3 text-sm text-amber-300">{{ __('Pas de nuit astronomique') }}</p>
                @endif
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-text-main/45">{{ __('Lune') }}</p>
                <p class="mt-3 text-xl font-semibold text-text-bold">{{ number_format($data->moon->illuminationPercent, 0) }} %</p>
                <p class="mt-2 text-xs text-text-main/50">{{ __('Altitude au milieu de nuit : :value°', ['value' => number_format($data->moon->altitudeDeg, 0)]) }}</p>
                <p class="mt-1 text-xs text-text-main/50">
                    {{ __('Lever :value · Coucher :value2', ['value' => $data->moon->rise?->format('H:i') ?? '—', 'value2' => $data->moon->set?->format('H:i') ?? '—']) }}
                </p>
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-text-main/45">{{ __('Configuration') }}</p>
                <p class="mt-3 truncate text-sm font-semibold text-text-bold">{{ $data->location->name }}</p>
                <p class="mt-2 truncate text-xs text-text-main/50">{{ $data->equipmentSetup?->name ?? __('Aucun rig') }}</p>
                @if ($data->optics)<p class="mt-1 text-xs text-accent">{{ __(':value mm · :value2″/px', ['value' => number_format($data->optics->effectiveFocalLengthMm, 0), 'value2' => number_format($data->optics->pixelScaleArcsec, 2)]) }}</p>@endif
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-text-main/45">{{ __('Météo') }}</p>
                @if($data->weather)
                    <p class="mt-3 text-xl font-semibold text-text-bold">{{ $data->weather->score }} / 100</p>
                    <p class="mt-2 text-xs text-text-main/50">{{ __(':label · :value % nuages', ['label' => $data->weather->label, 'value' => number_format($data->weather->averageCloudCoverPercent)]) }}</p>
                    <p class="mt-1 text-xs text-text-main/50">{{ __('Point de rosée · :value °C', ['value' => number_format($data->weather->averageDewPointCelsius, 1, ',')]) }}</p>
                    @if($data->weather->averageSeeingIndex !== null || $data->weather->averageTransparencyIndex !== null)
                        <p class="mt-1 text-xs text-text-main/50">
                            @if($data->weather->averageSeeingIndex !== null)
                                {{ __('Seeing :value/8', ['value' => number_format($data->weather->averageSeeingIndex, 1)]) }}
                            @endif
                            @if($data->weather->averageSeeingIndex !== null && $data->weather->averageTransparencyIndex !== null)
                                ·
                            @endif
                            @if($data->weather->averageTransparencyIndex !== null)
                                {{ __('Transp. :value/8', ['value' => number_format($data->weather->averageTransparencyIndex, 1)]) }}
                            @endif
                        </p>
                    @endif
                @else
                    <p class="mt-3 text-sm text-text-main/50">{{ __('Prévision indisponible') }}</p>
                @endif
                <a wire:navigate href="{{ route('admin.nightscope.forecast', ['date' => $data->date->format('Y-m-d')]) }}" class="mt-3 inline-flex text-xs text-accent hover:text-sky-300">{{ __('Voir les 7 nuits →') }}</a>
            </article>
        </section>

        <section id="recommendations" class="mt-6">
            @php
                $recommendationStep = (int) config('nightscope.tonight.recommendation_step', 12);
                $continueFrom = max(0, $data->recommendationLimit - $recommendationStep);
            @endphp
            <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
                <div><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Meilleures cibles') }}</p><h2 class="mt-1 text-lg font-semibold text-text-bold sm:text-xl">{{ __('À photographier cette nuit') }}</h2></div>
                <span class="text-xs text-text-main/45">{{ __(':value recommandations', ['value' => count($data->targets)]) }}</span>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @forelse ($data->targets as $result)
                    <div class="space-y-2">
                    <a wire:navigate
                        href="{{ route('admin.nightscope.targets.show', ['celestialObject' => $result->target, 'date' => $data->date->format('Y-m-d'), 'observing_location_id' => $data->location?->id, 'equipment_setup_id' => $data->equipmentSetup?->id ?? '']) }}"
                        @if($continueFrom > 0 && $loop->index === $continueFrom) id="recommendation-continue" @endif
                        class="group block overflow-hidden rounded-2xl border border-border bg-card-bg transition active:scale-[0.99] hover:-translate-y-0.5 hover:border-accent/35"
                    >
                        <div class="relative aspect-[16/10] overflow-hidden border-b border-border bg-bg sm:aspect-video">
                            <div class="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_center,rgba(88,166,255,.16),transparent_55%)] text-2xl text-accent/35" aria-hidden="true">✦</div>
                            <img src="{{ $result->previewImageUrl }}" alt="{{ __('Aperçu de :target', ['target' => $result->target->displayName()]) }}" class="relative size-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy" decoding="async" referrerpolicy="no-referrer" x-on:error="$el.classList.add('opacity-0')">
                            <div class="absolute inset-0 bg-gradient-to-t from-bg/75 via-transparent to-transparent"></div>
                            <span class="absolute bottom-3 left-3 rounded-full border border-white/10 bg-bg/70 px-2.5 py-1 font-[var(--font-mono)] text-[10px] text-text-main/75 backdrop-blur-sm">{{ $result->target->designation() }}</span>
                            @if($result->framing?->mosaicRecommended)
                                <span class="absolute right-3 top-3 rounded-full border border-violet-300/30 bg-violet-500/20 px-2.5 py-1 text-[10px] font-medium uppercase tracking-wide text-violet-100 backdrop-blur-sm">
                                    {{ __('Mosaïque') }}
                                    @if($result->framing->mosaicSuggestion())
                                        {{ __(':columns×:rows', ['columns' => $result->framing->mosaicSuggestion()->columns, 'rows' => $result->framing->mosaicSuggestion()->rows]) }}
                                    @endif
                                </span>
                            @endif
                        </div>
                        <div class="p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0"><p class="truncate font-[var(--font-mono)] text-sm font-semibold text-accent">{{ $result->target->name ?: $result->target->designation() }}</p><h3 class="mt-1 truncate text-sm font-medium text-text-bold">{{ ucfirst($result->target->type ?? __('Objet céleste')) }}</h3></div>
                                <span class="grid size-11 shrink-0 place-items-center rounded-full border border-accent/25 bg-accent/10 text-sm font-bold text-accent sm:size-12">{{ $result->score }}</span>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-3 text-xs sm:mt-5 sm:grid-cols-4">
                                <div>
                                    <dt class="text-text-main/40">{{ __('Maximum') }}</dt>
                                    <dd class="mt-1 font-semibold text-text-bold">{{ number_format($result->visibility->maxAltitudeDeg, 0) }}°</dd>
                                </div>
                                <div>
                                    <dt class="text-text-main/40">{{ __('Direction') }}</dt>
                                    <dd class="mt-1 flex items-center gap-1.5 font-semibold text-text-bold">
                                        <span class="grid size-6 shrink-0 place-items-center rounded-full border border-border bg-bg text-[10px] text-accent"><span style="transform: rotate({{ $result->pointingAzimuthDeg ?? 0 }}deg)">↑</span></span>
                                        <span class="truncate">{{ $result->pointingDirection ?? '—' }} <small class="hidden text-text-main/40 sm:inline">{{ $result->pointingAzimuthDeg !== null ? number_format($result->pointingAzimuthDeg, 0).'°' : '' }}</small></span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-text-main/40">{{ __('Fenêtre') }}</dt>
                                    <dd class="mt-1 font-semibold text-text-bold">{{ $result->visibility->rise?->format('H:i') }}–{{ $result->visibility->set?->format('H:i') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-text-main/40">{{ __('Lune') }}</dt>
                                    <dd class="mt-1 font-semibold text-text-bold">{{ number_format($result->moonSeparationDeg, 0) }}°</dd>
                                </div>
                            </dl>
                            @if($result->acquisition)
                                <p class="mt-3 font-[var(--font-mono)] text-[11px] leading-relaxed text-accent/85">
                                    @if($result->acquisition->gain !== null)
                                        {{ __('Gain :gain', ['gain' => $result->acquisition->gain]) }}
                                    @endif
                                    @if($result->acquisition->gain !== null && $result->acquisition->subExposureSeconds !== null)
                                        ·
                                    @endif
                                    @if($result->acquisition->subExposureSeconds !== null)
                                        {{ __(':formatted_sub_exposure / sub', ['formatted_sub_exposure' => $result->acquisition->formattedSubExposure()]) }}
                                    @endif
                                </p>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if($result->framing)
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-[10px] uppercase tracking-wide',
                                        'bg-violet-400/15 text-violet-200' => $result->framing->mosaicRecommended,
                                        'bg-white/[.04] text-text-main/60' => ! $result->framing->mosaicRecommended,
                                    ])>
                                        {{ $result->framing->rating->label() }}
                                        · {{ number_format($result->framing->frameFillPercent) }}%
                                        @if($result->framing->mosaicSuggestion())
                                            {{ __('· :columns×:rows', ['columns' => $result->framing->mosaicSuggestion()->columns, 'rows' => $result->framing->mosaicSuggestion()->rows]) }}
                                        @endif
                                    </span>
                                @endif
                                @if($result->neverPhotographed)
                                    <span class="rounded-full bg-green-400/10 px-2.5 py-1 text-[10px] uppercase tracking-wide text-green-300">{{ __('Jamais photographié') }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                    <x-admin.wishlist-button :target="$result->target" :favorite="$favorites->get($result->target->id)" />
                    </div>
                @empty
                    <p class="rounded-2xl border border-border bg-card-bg p-8 text-sm text-text-main/50 md:col-span-2">{{ $data->search !== '' ? __('Aucune cible ne correspond à cette recherche avec les filtres et la nuit sélectionnés.') : __('Aucune cible ne franchit les critères cette nuit.') }}</p>
                @endforelse
            </div>

            @if($data->hasMoreRecommendations)
                <div class="mt-5 flex justify-center">
                    <a wire:navigate
                        href="{{ route('admin.nightscope.tonight', [
                            'date' => $data->date->format('Y-m-d'),
                            'types' => $data->objectTypes,
                            'search' => $data->search,
                            'observing_location_id' => $data->location?->id,
                            'equipment_setup_id' => $data->equipmentSetup?->id ?? '',
                            'favorites_only' => $data->favoritesOnly ? 1 : null,
                            'never_photographed' => $data->neverPhotographedOnly ? 1 : null,
                            'limit' => $data->nextRecommendationLimit(),
                        ]) }}#recommendation-continue"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-accent/30 bg-accent/10 px-5 py-2.5 text-sm font-medium text-accent transition hover:bg-accent/20"
                    >
                        {{ __('Charger plus de recommandations') }}
                        <span class="ml-2 font-[var(--font-mono)] text-[11px] text-accent/70">→ {{ $data->nextRecommendationLimit() }}</span>
                    </a>
                </div>
            @endif
        </section>
    @endif
</x-admin.layout>
