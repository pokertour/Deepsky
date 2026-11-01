<x-admin.layout :title="$result->target->displayName()" eyebrow="{{ __('NightScope · Fiche cible') }}">
    <x-slot:actions>
        <x-admin.wishlist-button :target="$result->target" :favorite="$favorite" />
        <a wire:navigate href="{{ route('admin.nightscope.tonight', ['date' => $date->format('Y-m-d'), 'observing_location_id' => $data->location->id, 'equipment_setup_id' => $data->equipmentSetup?->id ?? '']) }}" class="inline-flex min-h-11 items-center rounded-xl border border-border bg-card-bg px-3 py-2 text-xs text-text-main/70 transition hover:border-accent/40 hover:text-accent">{{ __('← Tonight') }}</a>
    </x-slot:actions>

    <form method="GET" class="mb-5 grid grid-cols-1 gap-3 rounded-2xl border border-border bg-card-bg p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto]">
        <label class="grid gap-1.5 text-xs text-text-main/50">
            {{ __('Date') }}
            <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
        </label>
        <label class="grid gap-1.5 text-xs text-text-main/50">
            {{ __('Lieu') }}
            <select name="observing_location_id" class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                @foreach ($data->locations as $location)
                    <option value="{{ $location->id }}" @selected((int) $data->location->id === (int) $location->id)>{{ $location->name }}{{ $location->is_default ? __(' · défaut') : '' }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1.5 text-xs text-text-main/50">
            {{ __('Rig') }}
            <select name="equipment_setup_id" class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                <option value="" @selected($data->equipmentSetup === null)>{{ __('Aucun Rig') }}</option>
                @foreach ($data->equipmentSetups as $setup)
                    <option value="{{ $setup->id }}" @selected((int) $data->equipmentSetup?->id === (int) $setup->id)>{{ $setup->name }}{{ $setup->is_default ? __(' · défaut') : '' }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1.5 text-xs text-text-main/50">
            {{ __('Mosaïque') }}
            <select name="mosaic" class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                @foreach (App\Enums\MosaicDisplayMode::cases() as $mode)
                    <option value="{{ $mode->value }}" @selected($data->mosaicMode === $mode)>{{ $mode->label() }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end sm:col-span-2 xl:col-span-1">
            <button class="min-h-11 w-full rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ __('Recalculer') }}</button>
        </div>
    </form>

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(300px,.7fr)]">
        <div class="grid content-start gap-5">
            <article class="overflow-hidden rounded-2xl border border-border bg-card-bg">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:p-6">
                    <div class="min-w-0">
                        <p class="font-[var(--font-mono)] text-xs text-accent">{{ __('Aperçu du cadrage') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-text-bold sm:text-lg">{{ __('Champ réellement couvert par ton Rig') }}</h2>
                    </div>
                    @if($data->framingPreview)
                        <span class="w-fit shrink-0 rounded-full border border-accent/25 bg-accent/10 px-3 py-1 text-[10px] uppercase tracking-wide text-accent">{{ __(':value° × :value2°', ['value' => number_format($data->framingPreview->rigHorizontalFieldOfViewDeg, 2), 'value2' => number_format($data->framingPreview->rigVerticalFieldOfViewDeg, 2)]) }}</span>
                    @endif
                </div>
                <div class="relative aspect-[16/10] overflow-hidden border-y border-border bg-bg sm:aspect-video">
                    <div class="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_center,rgba(88,166,255,.16),transparent_55%)] text-3xl text-accent/35" aria-hidden="true">✦</div>
                    <img src="{{ $data->framingPreview?->imageUrl ?? $result->previewImageUrl }}" alt="{{ __('Champ céleste autour de :target', ['target' => $result->target->displayName()]) }}" class="relative size-full object-cover" referrerpolicy="no-referrer" x-on:error="$el.classList.add('opacity-0')">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-bg/45 via-transparent to-bg/20"></div>
                    @if($data->framingPreview)
                        <div class="absolute left-1/2 top-1/2 grid -translate-x-1/2 -translate-y-1/2 place-items-center border-2 border-accent shadow-[0_0_18px_rgba(88,166,255,.8),inset_0_0_18px_rgba(88,166,255,.12)]" style="width: {{ $data->framingPreview->renderedWidthPercent }}%; height: {{ $data->framingPreview->renderedHeightPercent }}%">
                            @if($data->mosaicOverlay)
                                <div class="absolute inset-0 grid opacity-70" style="grid-template-columns: repeat({{ $data->mosaicOverlay->columns }}, minmax(0, 1fr)); grid-template-rows: repeat({{ $data->mosaicOverlay->rows }}, minmax(0, 1fr));">
                                    @foreach (range(1, $data->mosaicOverlay->totalPanels()) as $panel)
                                        <div class="border border-accent/50"></div>
                                    @endforeach
                                </div>
                            @endif
                            <span class="relative z-10 rounded bg-bg/80 px-2 py-1 font-[var(--font-mono)] text-[9px] uppercase tracking-wider text-accent">
                                @if($data->mosaicOverlay)
                                    {{ __('Mosaïque :columns×:rows', ['columns' => $data->mosaicOverlay->columns, 'rows' => $data->mosaicOverlay->rows]) }}
                                @else
                                    {{ __('Capteur · :rotation_deg°', ['rotation_deg' => $data->framingPreview->rotationDeg]) }}
                                @endif
                            </span>
                        </div>
                        <span class="absolute bottom-3 left-3 max-w-[calc(100%-1.5rem)] truncate rounded-full border border-white/10 bg-bg/75 px-2.5 py-1 font-[var(--font-mono)] text-[10px] text-text-main/75 backdrop-blur-sm">{{ __('Image :value° × :value2°', ['value' => number_format($data->framingPreview->imageHorizontalFieldOfViewDeg, 2), 'value2' => number_format($data->framingPreview->imageVerticalFieldOfViewDeg, 2)]) }}</span>
                    @endif
                </div>
                <p class="p-4 text-xs text-text-main/50">
                    @if(! $data->framingPreview)
                        {{ $data->framingUnavailableReason ?? __('Le cadrage du Rig est indisponible.') }}
                    @elseif($data->mosaicOverlay)
                        @if($data->mosaicMode === App\Enums\MosaicDisplayMode::Auto)
                            {{ __('L’objet dépasse le champ du capteur : grille mosaïque conseillée :label (chevauchement :value %).', ['label' => $data->mosaicOverlay->label(), 'value' => number_format($data->mosaicOverlay->overlapPercent)]) }}
                        @else
                            {{ __('Grille mosaïque :label affichée sur le champ total (chevauchement :value % · panneau :value2° × :value3°).', ['label' => $data->mosaicOverlay->label(), 'value' => number_format($data->mosaicOverlay->overlapPercent), 'value2' => number_format($data->framingPreview->rigHorizontalFieldOfViewDeg, 2), 'value3' => number_format($data->framingPreview->rigVerticalFieldOfViewDeg, 2)]) }}
                        @endif
                    @elseif($data->framingPreview->fitsInPreview)
                        {{ __('Le rectangle représente le champ du capteur centré sur la cible.') }}
                    @else
                        {{ __('Le champ dépasse cette vue : le rectangle est ramené aux limites pour rester lisible.') }}
                    @endif
                </p>
            </article>

            <article class="overflow-hidden rounded-2xl border border-border bg-card-bg p-4 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-[var(--font-mono)] text-xs text-accent">{{ __('Courbe d’altitude') }}</p>
                        <h2 class="mt-1 text-base font-semibold text-text-bold sm:text-lg">{{ __('Visibilité pendant la nuit astronomique') }}</h2>
                        @if($data->night->hasAstronomicalNight())
                            <p class="mt-1 text-xs text-text-main/45">{{ __('Nuit :value → :value2 · :name', ['value' => $data->night->astronomicalNightStart->format('H:i'), 'value2' => $data->night->astronomicalNightEnd->format('H:i'), 'name' => $data->location->name]) }}</p>
                        @endif
                    </div>
                    <span class="grid size-12 shrink-0 place-items-center rounded-full border border-accent/25 bg-accent/10 text-sm font-bold text-accent sm:size-14 sm:text-base">{{ $result->score }}</span>
                </div>

                @php($chart = $data->chart)

                <div class="mt-5 overflow-x-auto rounded-xl border border-border bg-bg/60 p-2 sm:mt-6 sm:p-3">
                    <svg viewBox="0 0 1000 240" class="h-44 w-full min-w-[320px] sm:h-56" role="img" aria-label="{{ __('Altitude de la cible et de la Lune au cours de la nuit') }}">
                        <rect x="0" y="0" width="1000" height="240" fill="rgba(88,166,255,0.04)"/>
                        @if($chart->recommendedWindowStartX !== null && $chart->recommendedWindowWidth !== null && $chart->recommendedWindowWidth > 0)
                            <rect x="{{ $chart->recommendedWindowStartX }}" y="0" width="{{ $chart->recommendedWindowWidth }}" height="240" fill="rgba(63,185,80,0.12)"/>
                        @endif
                        @foreach ([20, 40, 60, 80] as $altitude)
                            <line x1="0" y1="{{ 220 - (($altitude + 10) / 100 * 200) }}" x2="1000" y2="{{ 220 - (($altitude + 10) / 100 * 200) }}" stroke="currentColor" class="text-border" stroke-dasharray="5 8"/>
                            <text x="8" y="{{ 214 - (($altitude + 10) / 100 * 200) }}" fill="currentColor" class="text-[24px] text-text-main/40">{{ $altitude }}°</text>
                        @endforeach
                        <line x1="0" y1="{{ 220 - ((30 + 10) / 100 * 200) }}" x2="1000" y2="{{ 220 - ((30 + 10) / 100 * 200) }}" stroke="#3fb950" stroke-width="2" stroke-dasharray="4 10" opacity="0.55"/>
                        @if($chart->transitX !== null)
                            <line x1="{{ $chart->transitX }}" y1="12" x2="{{ $chart->transitX }}" y2="228" stroke="#f0c674" stroke-width="2" stroke-dasharray="6 8"/>
                            <text x="{{ min(960, $chart->transitX + 8) }}" y="28" fill="#f0c674" class="text-[22px]">{{ __('Transit') }}</text>
                        @endif
                        @if($chart->moonPolyline !== '')
                            <polyline points="{{ $chart->moonPolyline }}" fill="none" stroke="#c9d1d9" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="8 10" opacity="0.75"/>
                        @endif
                        @if($chart->targetPolyline !== '')
                            <polyline points="{{ $chart->targetPolyline }}" fill="none" stroke="#58a6ff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                        @endif
                    </svg>
                    <div class="mt-3 flex flex-col gap-2 font-[var(--font-mono)] text-[10px] text-text-main/45 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3">
                        <span>{{ $chart->startLabel }}</span>
                        <span class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2 rounded-full bg-[#58a6ff]"></span>{{ __('Cible') }}</span>
                            <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2 rounded-full bg-[#c9d1d9]"></span>{{ __('Lune') }}</span>
                            <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2 rounded-sm bg-green-400/40"></span>{{ __('Fenêtre ≥ 30°') }}</span>
                            @if($chart->transitLabel)
                                <span class="text-amber-200/80">{{ __('Transit :transit_label', ['transit_label' => $chart->transitLabel]) }}</span>
                            @endif
                        </span>
                        <span>{{ $chart->endLabel }}</span>
                    </div>
                </div>
            </article>
            <x-admin.horizon-trajectory :location="$data->location" :visibility="$result->visibility" />
        </div>

        <aside class="grid content-start gap-4">
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Acquisition conseillée') }}</p>
                @if($result->acquisition)
                    <dl class="mt-4 grid gap-3 text-sm">
                        @if($result->acquisition->filters !== [])
                            <div class="grid gap-2">
                                <dt class="text-text-main/50">{{ __('Filtres') }}</dt>
                                <dd class="grid gap-2">
                                    @foreach ($result->acquisition->filters as $index => $filter)
                                        <div class="rounded-xl border border-border bg-bg/50 px-3 py-2">
                                            <div class="flex items-start justify-between gap-3">
                                                <p class="font-medium text-text-bold">
                                                    {{ $filter->displayName() }}
                                                    <span class="ml-1 font-[var(--font-mono)] text-[10px] font-normal text-text-main/40">{{ $filter->filterType->label() }}</span>
                                                </p>
                                                <span @class([
                                                    'shrink-0 rounded-full px-2 py-0.5 font-[var(--font-mono)] text-[9px] uppercase tracking-wide',
                                                    'bg-accent/15 text-accent' => $index === 0,
                                                    'bg-white/[.04] text-text-main/45' => $index > 0,
                                                ])>{{ $index === 0 ? __('Prioritaire') : __('Alt.') }}</span>
                                            </div>
                                            <p class="mt-1 text-[11px] leading-relaxed text-text-main/45">{{ $filter->reason }}</p>
                                            @if($filter->availabilityLabel())
                                                <p class="mt-1 font-[var(--font-mono)] text-[10px] text-accent/80">{{ $filter->availabilityLabel() }}</p>
                                            @elseif(! $filter->inInventory)
                                                <p class="mt-1 font-[var(--font-mono)] text-[10px] text-amber-200/70">{{ __('Pas trouvé · :product_hint', ['product_hint' => $filter->filterType->productHint()]) }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4">
                            <dt class="text-text-main/50">{{ __('Gain') }}</dt>
                            <dd class="text-right">
                                <span class="font-medium text-text-bold">{{ $result->acquisition->gain ?? '—' }}</span>
                                @if($result->acquisition->gain !== null)
                                    <span class="mt-0.5 block font-[var(--font-mono)] text-[10px] text-text-main/40">{{ $result->acquisition->gainSource->label() }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-text-main/50">{{ __('Sous-pose') }}</dt>
                            <dd class="text-right">
                                <span class="font-medium text-text-bold">{{ $result->acquisition->formattedSubExposure() ?? '—' }}</span>
                                @if($result->acquisition->subExposureSeconds !== null)
                                    <span class="mt-0.5 block font-[var(--font-mono)] text-[10px] text-text-main/40">
                                        {{ $result->acquisition->exposureSource->label() }}
                                        @if($result->acquisition->primaryFilter() && $result->acquisition->exposureSource === App\Enums\AcquisitionSuggestionSource::Heuristic)
                                            · {{ $result->acquisition->primaryFilter()->filterType->label() }}
                                        @endif
                                    </span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-[11px] text-text-main/40">{{ __('Point de départ selon cible, Lune et filtres du Rig — ajuster selon histogramme et seeing.') }}</p>
                @else
                    <p class="mt-3 text-sm text-text-main/50">{{ __('Configure un Rig avec tube + caméra pour une suggestion.') }}</p>
                @endif
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Night Planner') }}</p>
                <p class="mt-2 text-xs text-text-main/45">{{ __('Ajoute cette cible à la proposition du :value ou à un plan déjà sauvegardé.', ['value' => $date->format('d/m/Y')]) }}</p>

                <form method="POST" action="{{ route('admin.nightscope.planner.draft.targets') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                    <input type="hidden" name="celestial_object_id" value="{{ $result->target->id }}">
                    <button class="min-h-11 w-full rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ __('Ajouter à la proposition') }}</button>
                </form>

                @if($data->editablePlans->isNotEmpty())
                    <div class="mt-4 grid gap-2 border-t border-border pt-4">
                        <p class="text-[11px] text-text-main/45">{{ __('Plans sauvegardés ce jour') }}</p>
                        @foreach ($data->editablePlans as $plan)
                            <form method="POST" action="{{ route('admin.nightscope.plans.targets.store', $plan) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="celestial_object_id" value="{{ $result->target->id }}">
                                <button class="min-h-10 flex-1 rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-left text-xs font-medium text-accent transition hover:bg-accent/20">
                                    {{ __('Plan #:id · :targets_count cible(s)', ['id' => $plan->id, 'targets_count' => $plan->targets_count]) }}
                                </button>
                                <a wire:navigate href="{{ route('admin.nightscope.plans.show', $plan) }}" class="grid size-10 shrink-0 place-items-center rounded-lg border border-border text-text-main/45 hover:text-accent" title="{{ __('Voir le plan') }}">→</a>
                            </form>
                        @endforeach
                    </div>
                @endif
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Fenêtre utile') }}</p>
                <p class="mt-3 text-2xl font-semibold text-text-bold">{{ $result->visibility->rise?->format('H:i') ?? '—' }} → {{ $result->visibility->set?->format('H:i') ?? '—' }}</p>
                <p class="mt-2 text-xs text-text-main/50">{{ __('Maximum :value°', ['value' => number_format($result->visibility->maxAltitudeDeg ?? 0, 1)]) }}</p>
                <p class="mt-3 text-xs text-text-main/50">
                    {{ __('Recommandée (≥ 30°) :') }}
                    @if($data->chart->recommendedWindowStart && $data->chart->recommendedWindowEnd)
                        {{ $data->chart->recommendedWindowStart->format('H:i') }} → {{ $data->chart->recommendedWindowEnd->format('H:i') }}
                    @else
                        {{ __('indisponible') }}
                    @endif
                </p>
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Pointage') }}</p>
                <div class="mt-3 flex items-center gap-3">
                    <span class="grid size-12 place-items-center rounded-full border border-accent/25 bg-accent/10 text-xl text-accent"><span style="transform: rotate({{ $result->pointingAzimuthDeg ?? 0 }}deg)">↑</span></span>
                    <p class="text-3xl font-semibold text-text-bold">{{ $result->pointingDirection ?? '—' }}</p>
                </div>
                <p class="mt-2 text-xs text-text-main/50">{{ __('Azimut au meilleur moment :value', ['value' => $result->pointingAzimuthDeg !== null ? number_format($result->pointingAzimuthDeg, 1).'°' : 'indisponible']) }}</p>
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Informations cible') }}</p>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Catalogue') }}</dt><dd class="font-[var(--font-mono)] font-medium text-accent">{{ $result->target->designation() }}</dd></div>
                    @if($result->target->name)
                        <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Nom') }}</dt><dd class="font-medium text-text-bold">{{ $result->target->name }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Type') }}</dt><dd class="font-medium text-text-bold">{{ ucfirst($result->target->type ?? '—') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Constellation') }}</dt><dd class="font-medium text-text-bold">{{ $result->target->constellation ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Coordonnées') }}</dt><dd class="text-right font-[var(--font-mono)] text-xs text-text-bold">{{ __('AD :value°', ['value' => number_format($result->target->ra_deg, 3)]) }}<br>{{ __('DEC :value°', ['value' => number_format($result->target->dec_deg, 3)]) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Magnitude') }}</dt><dd class="font-medium text-text-bold">{{ $result->target->magnitude ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Brillance surfacique') }}</dt><dd class="font-medium text-text-bold">{{ $result->target->surface_brightness ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Dimensions') }}</dt><dd class="font-medium text-text-bold">{{ $result->target->angular_width_arcmin ? number_format($result->target->angular_width_arcmin, 1).'′ × '.number_format($result->target->angular_height_arcmin, 1).'′' : '—' }}</dd></div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-text-main/50">{{ __('Distance') }}</dt>
                        <dd class="text-right">
                            @if($result->target->distance_ly)
                                <span class="font-medium text-text-bold">{{ __(':value al', ['value' => number_format($result->target->distance_ly, 0, ',', ' ')]) }}</span>
                            @else
                                <span class="font-medium text-text-main/45">{{ __('Non renseignée') }}</span>
                            @endif
                            <form method="POST" action="{{ route('admin.nightscope.targets.distance', $result->target) }}" class="mt-2">
                                @csrf
                                <button class="text-[11px] font-medium text-accent hover:text-sky-300">
                                    {{ $result->target->distance_ly ? __('Rafraîchir via SIMBAD') : __('Récupérer via SIMBAD') }}
                                </button>
                            </form>
                        </dd>
                    </div>
                </dl>
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Contraintes') }}</p>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Séparation Lune') }}</dt><dd class="font-medium text-text-bold">{{ number_format($result->moonSeparationDeg, 1) }}°</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Au-dessus de 30°') }}</dt><dd class="font-medium text-text-bold">{{ __(':value h :value2', ['value' => intdiv($result->visibility->minutesAboveAltitude[30], 60), 'value2' => $result->visibility->minutesAboveAltitude[30] % 60]) }}</dd></div>
                    @if($result->framing)
                        <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Cadrage du rig') }}</dt><dd class="font-medium text-text-bold">{{ $result->framing->rating->label() }} · {{ number_format($result->framing->frameFillPercent) }} %</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Rotation conseillée') }}</dt><dd class="font-medium text-text-bold">{{ $result->framing->rotated ? '90°' : '0°' }}</dd></div>
                        @if($data->mosaicOverlay)
                            <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Mosaïque') }}</dt><dd class="text-right font-medium text-violet-200">{{ $data->mosaicOverlay->label() }}<span class="mt-0.5 block font-[var(--font-mono)] text-[10px] text-text-main/40">{{ __('chevauchement :value %:value2:value3', ['value' => number_format($data->mosaicOverlay->overlapPercent), 'value2' => $data->mosaicOverlay->rotated ? __(' · caméra 90°') : '', 'value3' => $data->mosaicMode !== App\Enums\MosaicDisplayMode::Auto ? __(' · forcée') : '']) }}</span></dd></div>
                        @elseif($result->framing->mosaicSuggestion())
                            <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Mosaïque') }}</dt><dd class="text-right font-medium text-text-main/45">{{ $result->framing->mosaicSuggestion()->label() }}<span class="mt-0.5 block font-[var(--font-mono)] text-[10px] text-text-main/40">{{ __('masquée') }}</span></dd></div>
                        @endif
                    @endif
                </dl>
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Ton équipement') }}</p>
                @if($data->equipmentSetup)
                    <p class="mt-3 font-semibold text-text-bold">{{ $data->equipmentSetup->name }}</p>
                    <dl class="mt-4 grid gap-3 text-sm">
                        @if($result->optics)
                            <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Focale effective') }}</dt><dd class="font-medium text-text-bold">{{ __(':value mm', ['value' => number_format($result->optics->effectiveFocalLengthMm, 0)]) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Échantillonnage') }}</dt><dd class="font-medium text-text-bold">{{ __(':value″/px', ['value' => number_format($result->optics->pixelScaleArcsec, 2)]) }}</dd></div>
                        @endif
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($data->equipmentSetup->enabledSetupItems as $item)
                            <span class="rounded-full bg-white/[.04] px-2.5 py-1 text-[10px] text-text-main/65">{{ $item->brand }} {{ $item->name }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 text-sm text-text-main/50">{{ __('Aucun Rig configuré.') }}</p>
                @endif
            </article>
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Ton historique') }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-text-main/50">{{ __('Photos') }}</dt><dd class="mt-1 text-xl font-semibold text-text-bold">{{ $data->history->photoCount }}</dd></div>
                    <div><dt class="text-text-main/50">{{ __('Nuits') }}</dt><dd class="mt-1 text-xl font-semibold text-text-bold">{{ $data->history->sessionCount }}</dd></div>
                    <div><dt class="text-text-main/50">{{ __('Intégration') }}</dt><dd class="mt-1 font-medium text-text-bold">{{ __(':value h :value2', ['value' => intdiv($data->history->totalIntegrationSeconds, 3600), 'value2' => intdiv($data->history->totalIntegrationSeconds % 3600, 60)]) }}</dd></div>
                    <div><dt class="text-text-main/50">{{ __('Dernière capture') }}</dt><dd class="mt-1 font-medium text-text-bold">{{ $data->history->lastCapturedAt?->format('d/m/Y') ?? '—' }}</dd></div>
                </dl>
                @if($data->history->recentPhotos !== [])
                    <ul class="mt-4 divide-y divide-border border-t border-border">
                        @foreach($data->history->recentPhotos as $photo)
                            <li>
                                <a wire:navigate href="{{ route('admin.astrophotos.edit', $photo) }}" class="flex items-center justify-between gap-3 py-3 text-sm transition hover:text-accent">
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-text-bold">{{ $photo->title ?: ($photo->target ?: __('Sans titre')) }}</span>
                                        <span class="mt-0.5 block font-[var(--font-mono)] text-[10px] text-text-main/45">{{ $photo->captured_at?->format('d/m/Y') ?? __('Date inconnue') }}@if($photo->exposure) {{ __('· :value h :value2', ['value' => intdiv($photo->exposure, 3600), 'value2' => intdiv($photo->exposure % 3600, 60)]) }}@endif</span>
                                    </span>
                                    <span class="text-text-main/30">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </article>
            @php($optics = $result->optics)
            <article class="rounded-2xl border border-violet-300/20 bg-card-bg p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-violet-200">{{ __('Références externes') }}</p>
                        <p class="mt-2 text-xs text-text-main/45">{{ __('Exemples Wikimedia Commons de cette cible, plus une recherche AstroBin préremplie avec ta focale.') }}</p>
                    </div>
                    <a href="{{ $data->astroBinSearchUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 shrink-0 items-center rounded-xl border border-violet-300/30 bg-violet-500/10 px-3 py-2 text-xs font-medium text-violet-100 transition hover:bg-violet-500/20">
                        {{ __('AstroBin') }}
                        @if($optics)
                            {{ __('· ~:value mm', ['value' => number_format($optics->effectiveFocalLengthMm, 0)]) }}
                        @endif
                        ↗
                    </a>
                </div>

                @if($data->externalReferencePhotos !== [])
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        @foreach($data->externalReferencePhotos as $reference)
                            <a href="{{ $reference->pageUrl }}" target="_blank" rel="noopener noreferrer" class="group overflow-hidden rounded-xl border border-border bg-bg/50 transition hover:border-accent/35">
                                <div class="relative aspect-square overflow-hidden bg-bg">
                                    <img src="{{ $reference->thumbUrl }}" alt="{{ $reference->title }}" class="size-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy" referrerpolicy="no-referrer">
                                </div>
                                <div class="p-2.5">
                                    <p class="truncate text-xs font-medium text-text-bold">{{ $reference->title }}</p>
                                    <p class="mt-0.5 truncate font-[var(--font-mono)] text-[10px] text-text-main/45">{{ $reference->author }}</p>
                                    <p class="mt-1 font-[var(--font-mono)] text-[10px] text-accent/80">{{ $reference->matchLabel }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-sm text-text-main/50">{{ __('Aucune image Commons trouvée pour cette désignation. Utilise le bouton AstroBin pour explorer des captures amateurs avec une focale proche.') }}</p>
                @endif
            </article>
        </aside>
    </section>
</x-admin.layout>
