<x-admin.layout title="{{ __('Night Planner') }}" eyebrow="{{ __('NightScope · Planifier → Photographier') }}">
    <x-slot:actions>
        <form method="GET" class="flex w-full items-center gap-2 sm:w-auto">
            <input type="date" name="date" value="{{ $planner->tonight->date->format('Y-m-d') }}" class="min-h-11 min-w-0 flex-1 rounded-xl border border-border bg-card-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent sm:flex-none">
            <button class="min-h-11 shrink-0 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ __('Générer') }}</button>
        </form>
    </x-slot:actions>

    @session('success')
        <p class="mb-4 rounded-xl border border-green-400/25 bg-green-400/10 px-4 py-3 text-sm text-green-200">{{ $value }}</p>
    @endsession
    @session('error')
        <p class="mb-4 rounded-xl border border-red-400/25 bg-red-400/10 px-4 py-3 text-sm text-red-300">{{ $value }}</p>
    @endsession

    @if(! $planner->tonight->location)
        <section class="rounded-2xl border border-amber-400/25 bg-amber-400/10 p-6"><p class="font-semibold text-amber-200">{{ __('Lieu d’observation requis') }}</p><p class="mt-2 text-sm text-text-main/65">{{ __('Ajoute un lieu avant de générer un planning.') }}</p></section>
    @else
        <section class="rounded-2xl border border-accent/25 bg-gradient-to-br from-accent/10 to-card-bg p-5 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Proposition automatique') }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-text-bold sm:text-xl">{{ __('Nuit du :value', ['value' => $planner->tonight->date->format('d/m/Y')]) }}</h2>
                    <p class="mt-2 text-sm text-text-main/55">{{ $planner->tonight->location->name }} · {{ $planner->tonight->equipmentSetup?->name ?? __('Aucun Rig') }}</p>
                    <p class="mt-1 text-xs text-text-main/40">{{ __('Tu peux retirer ou ajouter des cibles avant de sauvegarder.') }}</p>
                </div>
                <div class="flex flex-col items-stretch gap-2 sm:items-end">
                    <div class="rounded-xl border border-border bg-bg/40 px-4 py-3 sm:border-0 sm:bg-transparent sm:p-0 sm:text-right">
                        <p class="text-2xl font-semibold text-text-bold sm:text-3xl">{{ __(':value h :value2', ['value' => intdiv($planner->usefulImagingMinutes(), 60), 'value2' => $planner->usefulImagingMinutes() % 60]) }}</p>
                        <p class="text-xs text-text-main/45">{{ __('temps d’imagerie utile') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.nightscope.planner.draft.reset') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $planner->tonight->date->format('Y-m-d') }}">
                        <button class="text-xs text-text-main/50 hover:text-accent">{{ __('Réinitialiser la proposition') }}</button>
                    </form>
                </div>
            </div>

            <div class="mt-6 grid gap-3">
                @forelse($planner->slots as $slot)
                    <article class="grid grid-cols-1 gap-3 rounded-xl border border-border bg-bg/55 p-4 sm:grid-cols-[90px_minmax(0,1fr)_auto] sm:items-center">
                        <div class="flex items-center justify-between gap-3 sm:block">
                            <p class="font-[var(--font-mono)] text-sm font-semibold text-accent">{{ $slot->startsAt->format('H:i') }} <span class="text-text-main/40">→ {{ $slot->endsAt->format('H:i') }}</span></p>
                            <span class="grid size-10 place-items-center rounded-full border border-accent/25 bg-accent/10 text-sm font-bold text-accent sm:hidden">{{ $slot->score }}</span>
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold text-text-bold">{{ $slot->target->displayName() }}</h3>
                            <p class="mt-1 text-xs text-text-main/45">{{ __(':value · :duration_minutes min · :designation', ['value' => ucfirst($slot->target->type ?? __('Objet céleste')), 'duration_minutes' => $slot->durationMinutes(), 'designation' => $slot->target->designation()]) }}</p>
                            @if($slot->acquisition)
                                <p class="mt-1 font-[var(--font-mono)] text-[11px] leading-relaxed text-accent/90">
                                    @if($slot->acquisition->primaryFilter())
                                        {{ $slot->acquisition->primaryFilter()->displayName() }}
                                        @if($slot->acquisition->gain !== null || $slot->acquisition->subExposureSeconds !== null)
                                            ·
                                        @endif
                                    @endif
                                    @if($slot->acquisition->gain !== null)
                                        {{ __('Gain :gain', ['gain' => $slot->acquisition->gain]) }}
                                    @endif
                                    @if($slot->acquisition->gain !== null && $slot->acquisition->subExposureSeconds !== null)
                                        ·
                                    @endif
                                    @if($slot->acquisition->subExposureSeconds !== null)
                                        {{ __(':formatted_sub_exposure / sub', ['formatted_sub_exposure' => $slot->acquisition->formattedSubExposure()]) }}
                                    @endif
                                </p>
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-2 sm:justify-end">
                            <span class="hidden size-11 place-items-center rounded-full border border-accent/25 bg-accent/10 text-sm font-bold text-accent sm:grid">{{ $slot->score }}</span>
                            <form method="POST" action="{{ route('admin.nightscope.planner.draft.exclude') }}">
                                @csrf
                                <input type="hidden" name="date" value="{{ $planner->tonight->date->format('Y-m-d') }}">
                                <input type="hidden" name="celestial_object_id" value="{{ $slot->target->id }}">
                                <button class="grid size-10 place-items-center rounded-lg border border-border text-sm text-text-main/50 transition hover:border-red-400/40 hover:text-red-300" title="{{ __('Retirer de la proposition') }}">✕</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="rounded-xl border border-border bg-bg/55 p-5 text-sm text-text-main/50">{{ __('Aucun créneau dans la proposition. Ajoute une cible ou réinitialise.') }}</p>
                @endforelse
            </div>

            <form
                method="POST"
                action="{{ route('admin.nightscope.planner.draft.targets') }}"
                class="mt-5 rounded-xl border border-dashed border-border bg-bg/40 p-4"
                x-data="celestialObjectLookup"
                data-search-url="{{ route('admin.celestial-objects.search') }}"
                @click.outside="close"
            >
                @csrf
                <input type="hidden" name="date" value="{{ $planner->tonight->date->format('Y-m-d') }}">
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
                    <button class="min-h-11 rounded-xl border border-accent/30 bg-accent/10 px-4 py-2 text-sm font-medium text-accent transition hover:bg-accent/20 sm:col-span-2 lg:col-span-1">{{ __('Ajouter') }}</button>
                </div>
                <p class="mt-2 text-[11px] text-text-main/40">{{ __('Sans horaires, NightScope propose un créneau après la dernière cible.') }}</p>
                @error('celestial_object_id')<p class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror
            </form>

            @if($planner->slots !== [])
                <form action="{{ route('admin.nightscope.planner.store') }}" method="POST" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    @csrf
                    <input type="hidden" name="date" value="{{ $planner->tonight->date->format('Y-m-d') }}">
                    <label class="grid gap-1.5 text-xs text-text-main/55">{{ __('Notes') }}
                        <textarea name="notes" rows="2" maxlength="5000" class="rounded-xl border border-border bg-bg px-3 py-2 text-sm text-text-bold outline-none focus:border-accent" placeholder="{{ __('Objectifs, filtres, contraintes…') }}"></textarea>
                    </label>
                    <button class="min-h-11 w-full rounded-xl bg-accent px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-300 sm:w-auto">{{ __('Sauvegarder ce plan') }}</button>
                </form>
            @endif
        </section>
    @endif

    <section class="mt-8">
        <div class="mb-4"><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Plans sauvegardés') }}</p><h2 class="mt-1 text-xl font-semibold text-text-bold">{{ __('Tes prochaines sessions') }}</h2></div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($plans as $plan)
                <a wire:navigate href="{{ route('admin.nightscope.plans.show', $plan) }}" class="rounded-2xl border border-border bg-card-bg p-5 transition hover:-translate-y-0.5 hover:border-accent/35"><div class="flex items-start justify-between gap-3"><div><p class="font-[var(--font-mono)] text-xs text-accent">{{ $plan->date->format('d/m/Y') }}</p><h3 class="mt-1 font-semibold text-text-bold">{{ $plan->observingLocation?->name ?? __('Lieu supprimé') }}</h3></div><span class="rounded-full bg-white/[.04] px-2.5 py-1 text-[10px] uppercase tracking-wide text-text-main/60">{{ $plan->status->label() }}</span></div><p class="mt-4 text-sm text-text-main/55">{{ __(':targets_count cibles · :value', ['targets_count' => $plan->targets_count, 'value' => $plan->equipmentSetup?->name ?? __('Rig supprimé')]) }}</p></a>
            @empty<p class="rounded-2xl border border-border bg-card-bg p-6 text-sm text-text-main/50">{{ __('Aucun plan sauvegardé.') }}</p>@endforelse
        </div>
        <div class="mt-5">{{ $plans->links('pagination.deepsky') }}</div>
    </section>
</x-admin.layout>
