<x-admin.layout :title="__('Plan du :date', ['date' => $plan->date->format('d/m/Y')])" eyebrow="{{ __('NightScope · Plan d’imagerie') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.nightscope.planner') }}" class="inline-flex min-h-11 items-center rounded-xl border border-border bg-card-bg px-3 py-2 text-xs text-text-main/70 transition hover:border-accent/40 hover:text-accent">{{ __('← Planner') }}</a></x-slot:actions>

    @php
        $offlinePlan = [
            'id' => $plan->id,
            'date' => $plan->date->format('d/m/Y'),
            'timezone' => $plan->observingLocation?->timezone,
            'location' => $plan->observingLocation?->name,
            'rig' => $plan->equipmentSetup?->name,
            'status' => $plan->status->label(),
            'notes' => $plan->notes,
            'targets' => $plan->targets->map(function ($target) use ($acquisitions) {
                $suggestion = $acquisitions[$target->id] ?? null;

                return [
                    'name' => $target->celestialObject->displayName().' · '.$target->celestialObject->designation(),
                    'start' => $target->planned_start_at->format('d/m H:i'),
                    'end' => $target->planned_end_at->format('d/m H:i'),
                    'score' => $target->score_snapshot,
                    'coordinates' => __('AD').' '.number_format($target->celestialObject->ra_deg, 4).'° · '.__('Déc').' '.number_format($target->celestialObject->dec_deg, 4).'°',
                    'acquisition' => $suggestion ? collect([
                        $suggestion->primaryFilter()?->displayName(),
                        $suggestion->gain !== null ? 'Gain '.$suggestion->gain : null,
                        $suggestion->formattedSubExposure(),
                    ])->filter()->implode(' · ') : null,
                ];
            })->values()->all(),
        ];
    @endphp
    <section class="mb-5 rounded-2xl border border-border bg-card-bg p-4">
        <div class="flex flex-wrap gap-3">
            <button type="button" data-offline-plan="{{ json_encode($offlinePlan) }}" class="min-h-11 rounded-xl border border-accent/30 bg-accent/10 px-4 py-2 text-sm text-accent">{{ __('Enregistrer hors connexion') }}</button>
            <a href="/offline.html" class="inline-flex min-h-11 items-center rounded-xl border border-border px-4 py-2 text-sm text-text-main">{{ __('Plans hors connexion') }}</a>
        </div>
        <p class="mt-2 text-xs text-text-main/50" data-offline-status role="status">{{ __('Enregistre une copie du programme sur cet appareil avant de partir. Consultation seule, sans actualisation météo. Les copies sont effacées à la déconnexion.') }}</p>
    </section>

    @session('success')
        <p class="mb-4 rounded-xl border border-green-400/25 bg-green-400/10 px-4 py-3 text-sm text-green-200">{{ $value }}</p>
    @endsession
    @session('error')
        <p class="mb-4 rounded-xl border border-red-400/25 bg-red-400/10 px-4 py-3 text-sm text-red-300">{{ $value }}</p>
    @endsession

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(280px,.65fr)]">
        <article class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Programme') }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-text-bold">{{ __(':count cibles planifiées', ['count' => $plan->targets->count()]) }}</h2>
                </div>
                <span class="rounded-full border border-accent/20 bg-accent/10 px-3 py-1.5 text-xs text-accent">{{ $plan->status->label() }}</span>
            </div>

            <div class="mt-6 grid gap-3">
                @foreach($plan->targets as $target)
                    <div class="grid gap-3 rounded-xl border border-border bg-bg/55 p-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                            <div class="min-w-0">
                                <a wire:navigate href="{{ route('admin.nightscope.targets.show', ['celestialObject' => $target->celestialObject, 'date' => $plan->date->format('Y-m-d')]) }}" class="block truncate font-semibold text-text-bold hover:text-accent">{{ $target->celestialObject->displayName() }}</a>
                                <p class="mt-1 text-xs text-text-main/45">{{ ucfirst($target->celestialObject->type ?? __('Objet céleste')) }} · {{ $target->celestialObject->designation() }}</p>
                                @php($suggestion = $acquisitions[$target->id] ?? null)
                                @if($suggestion)
                                    <p class="mt-1 font-[var(--font-mono)] text-[11px] leading-relaxed text-accent/90">
                                        @if($suggestion->primaryFilter())
                                            {{ $suggestion->primaryFilter()->displayName() }}
                                            @if($suggestion->gain !== null || $suggestion->subExposureSeconds !== null)
                                                ·
                                            @endif
                                        @endif
                                        @if($suggestion->gain !== null)
                                            {{ __('Gain :gain', ['gain' => $suggestion->gain]) }}
                                        @endif
                                        @if($suggestion->gain !== null && $suggestion->subExposureSeconds !== null)
                                            ·
                                        @endif
                                        @if($suggestion->subExposureSeconds !== null)
                                            {{ __(':formatted_sub_exposure / sub', ['formatted_sub_exposure' => $suggestion->formattedSubExposure()]) }}
                                        @endif
                                    </p>
                                    @if($suggestion->primaryFilter())
                                        <p class="mt-0.5 text-[11px] text-text-main/40">{{ $suggestion->primaryFilter()->reason }}</p>
                                    @endif
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="grid size-10 place-items-center rounded-full bg-white/[.04] text-xs font-bold text-accent">{{ $target->score_snapshot }}</span>
                                @if($plan->status !== App\Enums\ImagingPlanStatus::Active)
                                    <form method="POST" action="{{ route('admin.nightscope.plans.targets.up', [$plan, $target]) }}">@csrf<button class="grid size-10 place-items-center rounded-lg border border-border text-sm text-text-main/50 hover:text-accent" title="{{ __('Monter') }}">↑</button></form>
                                    <form method="POST" action="{{ route('admin.nightscope.plans.targets.down', [$plan, $target]) }}">@csrf<button class="grid size-10 place-items-center rounded-lg border border-border text-sm text-text-main/50 hover:text-accent" title="{{ __('Descendre') }}">↓</button></form>
                                    <form method="POST" action="{{ route('admin.nightscope.plans.targets.destroy', [$plan, $target]) }}" data-confirm="{{ __('La cible sera retirée de ce plan de nuit.') }}" data-confirm-title="{{ __('Retirer la cible ?') }}" data-confirm-label="{{ __('Retirer') }}" data-confirm-tone="danger">@csrf @method('DELETE')<button class="grid size-10 place-items-center rounded-lg border border-border text-sm text-text-main/50 hover:border-red-400/40 hover:text-red-300">✕</button></form>
                                @endif
                            </div>
                        </div>

                        @if($plan->status !== App\Enums\ImagingPlanStatus::Active)
                            <form method="POST" action="{{ route('admin.nightscope.plans.targets.update', [$plan, $target]) }}" class="grid grid-cols-1 gap-2 border-t border-border pt-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                @csrf @method('PUT')
                                <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Début') }}
                                    <input type="time" name="planned_start" value="{{ old('planned_start', $target->planned_start_at->format('H:i')) }}" required class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                                </label>
                                <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Fin') }}
                                    <input type="time" name="planned_end" value="{{ old('planned_end', $target->planned_end_at->format('H:i')) }}" required class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                                </label>
                                <button class="min-h-11 rounded-xl border border-accent/30 bg-accent/10 px-4 py-2 text-xs font-medium text-accent hover:bg-accent/20">{{ __('Sauver horaires') }}</button>
                            </form>
                        @else
                            <p class="border-t border-border pt-3 font-[var(--font-mono)] text-sm text-accent">{{ $target->planned_start_at->format('H:i') }} <span class="text-text-main/40">→ {{ $target->planned_end_at->format('H:i') }}</span></p>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($plan->status !== App\Enums\ImagingPlanStatus::Active)
                <form
                    method="POST"
                    action="{{ route('admin.nightscope.plans.targets.store', $plan) }}"
                    class="mt-5 rounded-xl border border-dashed border-border bg-bg/40 p-4"
                    x-data="celestialObjectLookup"
                    data-search-url="{{ route('admin.celestial-objects.search') }}"
                    @click.outside="close"
                >
                    @csrf
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Ajouter une cible') }}</p>
                    <div class="relative mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto] lg:items-end">
                        <label class="grid gap-1.5 text-xs text-text-main/50 sm:col-span-2 lg:col-span-1">
                            {{ __('Catalogue') }}
                            <input type="hidden" name="celestial_object_id" x-model="selectedId">
                            <input x-model="query" @input.debounce.250ms="search" @focus="search" class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent" placeholder="{{ __('M 31, NGC 7000…') }}" autocomplete="off">
                            <div x-show="open" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 max-h-56 overflow-y-auto rounded-xl border border-border bg-card-bg p-2 shadow-2xl lg:col-span-4">
                                <template x-for="result in results" :key="result.id">
                                    <button type="button" @click="choose(result)" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm text-text-bold hover:bg-accent/10 hover:text-accent" x-text="result.label"></button>
                                </template>
                                <p x-show="!loading && results.length === 0" class="px-3 py-3 text-xs text-text-main/40">{{ __('Aucun objet trouvé.') }}</p>
                            </div>
                        </label>
                        <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Début (opt.)') }}
                            <input type="time" name="planned_start" value="{{ old('planned_start') }}" class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                        </label>
                        <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Fin (opt.)') }}
                            <input type="time" name="planned_end" value="{{ old('planned_end') }}" class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                        </label>
                        <button class="min-h-11 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-300 sm:col-span-2 lg:col-span-1">{{ __('Ajouter') }}</button>
                    </div>
                    <p class="mt-2 text-[11px] text-text-main/40">{{ __('Sans horaires, NightScope propose un créneau automatique après la dernière cible.') }}</p>
                    @error('celestial_object_id')<p class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror
                </form>
            @endif
        </article>

        <aside class="grid content-start gap-4">
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Configuration') }}</p>
                @if($plan->status !== App\Enums\ImagingPlanStatus::Active)
                    <form method="POST" action="{{ route('admin.nightscope.plans.update', $plan) }}" class="mt-4 grid gap-3">
                        @csrf @method('PUT')
                        <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Date') }}
                            <input type="date" name="date" value="{{ old('date', $plan->date->format('Y-m-d')) }}" required class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                        </label>
                        <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Lieu') }}
                            <select name="observing_location_id" required class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) old('observing_location_id', $plan->observing_location_id) === (int) $location->id)>{{ $location->name }}{{ $location->is_default ? __(' · défaut') : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Rig') }}
                            <select name="equipment_setup_id" class="min-h-11 rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                                <option value="" @selected(old('equipment_setup_id', $plan->equipment_setup_id) === null)>{{ __('Aucun Rig') }}</option>
                                @foreach ($equipmentSetups as $setup)
                                    <option value="{{ $setup->id }}" @selected((int) old('equipment_setup_id', $plan->equipment_setup_id) === (int) $setup->id)>{{ $setup->name }}{{ $setup->is_default ? __(' · défaut') : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Notes du plan') }}
                            <textarea name="notes" rows="3" maxlength="5000" class="rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">{{ old('notes', $plan->notes) }}</textarea>
                        </label>
                        <button class="min-h-11 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ __('Enregistrer le plan') }}</button>
                        <p class="text-[11px] text-text-main/40">{{ __('Changer la date décale automatiquement les horaires des cibles.') }}</p>
                    </form>
                @else
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Date') }}</dt><dd class="text-right font-medium text-text-bold">{{ $plan->date->format('d/m/Y') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Lieu') }}</dt><dd class="text-right font-medium text-text-bold">{{ $plan->observingLocation?->name ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-text-main/50">{{ __('Rig') }}</dt><dd class="text-right font-medium text-text-bold">{{ $plan->equipmentSetup?->name ?? '—' }}</dd></div>
                    </dl>
                    @if($plan->notes)
                        <p class="mt-4 border-t border-border pt-4 text-sm text-text-main/65">{{ $plan->notes }}</p>
                    @endif
                @endif
            </article>

            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Dupliquer') }}</p>
                <form method="POST" action="{{ route('admin.nightscope.plans.duplicate', $plan) }}" class="mt-4 grid gap-3">
                    @csrf
                    <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Nouvelle date') }}
                        <input type="date" name="date" value="{{ $plan->date->toImmutable()->addDay()->format('Y-m-d') }}" class="rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">
                    </label>
                    <button class="rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-xs font-medium text-accent hover:bg-accent/20">{{ __('Dupliquer sur cette date') }}</button>
                </form>
            </article>

            @if(! $plan->imagingSession)
                <form action="{{ route('admin.nightscope.plans.start', $plan) }}" method="POST">@csrf<button class="min-h-12 w-full rounded-xl bg-accent px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ __('Démarrer la session') }}</button></form>
            @elseif(! $plan->imagingSession->ended_at)
                <article class="rounded-2xl border border-green-400/25 bg-green-400/10 p-5">
                    <p class="font-semibold text-green-200">{{ __('Session en cours') }}</p>
                    <p class="mt-2 text-xs text-text-main/55">{{ __('Démarrée à :value', ['value' => $plan->imagingSession->started_at->format('H:i')]) }}</p>
                    @if($plan->imagingSession->weather_data)
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-[11px] text-text-main/60">
                            <div>{{ __('Score météo') }} <span class="font-semibold text-text-bold">{{ $plan->imagingSession->weather_data['score'] ?? '—' }}</span></div>
                            <div>{{ __('Nuages') }} <span class="font-semibold text-text-bold">{{ isset($plan->imagingSession->weather_data['clouds']) ? number_format($plan->imagingSession->weather_data['clouds']).' %' : '—' }}</span></div>
                            @if(isset($plan->imagingSession->weather_data['seeing']))
                                <div>{{ __('Seeing') }} <span class="font-semibold text-text-bold">{{ number_format($plan->imagingSession->weather_data['seeing'], 1) }}/8</span></div>
                            @endif
                            @if(isset($plan->imagingSession->weather_data['transparency']))
                                <div>{{ __('Transp.') }} <span class="font-semibold text-text-bold">{{ number_format($plan->imagingSession->weather_data['transparency'], 1) }}/8</span></div>
                            @endif
                        </dl>
                    @endif
                    <form method="POST" action="{{ route('admin.nightscope.sessions.update', $plan->imagingSession) }}" class="mt-4">
                        @csrf @method('PUT')
                        <label class="grid gap-1.5 text-xs text-text-main/50">{{ __('Notes de session') }}
                            <textarea name="notes" rows="2" maxlength="5000" class="rounded-xl border border-border bg-bg/80 px-3 py-2 text-sm text-text-bold outline-none focus:border-accent">{{ old('notes', $plan->imagingSession->notes) }}</textarea>
                        </label>
                        <button class="mt-2 text-xs text-accent hover:text-sky-300">{{ __('Sauver les notes') }}</button>
                    </form>
                    <form action="{{ route('admin.nightscope.sessions.finish', $plan->imagingSession) }}" method="POST" class="mt-4">@csrf<button class="min-h-11 w-full rounded-xl border border-green-300/30 px-4 py-2 text-sm font-medium text-green-200 hover:bg-green-300/10">{{ __('Terminer la session') }}</button></form>
                </article>
            @else
                <article class="rounded-2xl border border-border bg-card-bg p-5">
                    <p class="font-semibold text-text-bold">{{ __('Session terminée') }}</p>
                    <p class="mt-2 text-xs text-text-main/50">{{ $plan->imagingSession->started_at->format('d/m H:i') }} → {{ $plan->imagingSession->ended_at->format('d/m H:i') }}</p>
                    @if($plan->imagingSession->notes)
                        <p class="mt-3 text-sm text-text-main/65">{{ $plan->imagingSession->notes }}</p>
                    @endif
                </article>
            @endif

            @if($plan->imagingSession)
                @php($photos = $plan->imagingSession->astrophotos)
                <article class="rounded-2xl border border-border bg-card-bg p-5">
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Photos de session') }}</p>
                    <p class="mt-2 text-sm text-text-main/50">{{ __(':count photo(s) liée(s)', ['count' => $photos->count()]) }}</p>
                    <ul class="mt-3 divide-y divide-border">
                        @forelse($photos as $photo)
                            <li>
                                <a wire:navigate href="{{ route('admin.astrophotos.edit', $photo) }}" class="flex items-center justify-between gap-3 py-2.5 text-sm hover:text-accent">
                                    <span class="truncate">{{ $photo->title ?: ($photo->target ?: __('Sans titre')) }}</span>
                                    <span class="text-text-main/30">→</span>
                                </a>
                            </li>
                        @empty
                            <li class="py-2 text-xs text-text-main/40">{{ __('Aucune photo rattachée pour l’instant.') }}</li>
                        @endforelse
                    </ul>
                </article>
            @endif

            @if($plan->status !== App\Enums\ImagingPlanStatus::Active)
                <form method="POST" action="{{ route('admin.nightscope.plans.destroy', $plan) }}" data-confirm="{{ __('Le plan et ses cibles seront supprimés définitivement.') }}" data-confirm-title="{{ __('Supprimer le plan ?') }}" data-confirm-label="{{ __('Supprimer') }}" data-confirm-tone="danger">
                    @csrf @method('DELETE')
                    <button class="w-full rounded-xl border border-red-400/25 px-4 py-3 text-sm text-red-300 hover:bg-red-400/10">{{ __('Supprimer le plan') }}</button>
                </form>
            @endif
        </aside>
    </section>
</x-admin.layout>
