<x-admin.layout title="{{ __('Vue d’ensemble') }}" eyebrow="{{ __('Observatoire en ligne') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.astrophotos.create') }}" class="rounded-xl bg-accent px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-sky-300 sm:px-4 sm:text-sm">{{ __('+ Nouvelle photo') }}</a></x-slot:actions>

    <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ([
            ['Astrophotos', $stats['photos'], $stats['published'].__(' publiées · ').$stats['drafts'].__(' brouillons'), 'accent'],
            [__('Temps de pose'), floor($stats['exposure'] / 3600).'h '.str_pad((string) floor(($stats['exposure'] % 3600) / 60), 2, '0', STR_PAD_LEFT), __('cumulé'), 'amber'],
            [__('Sessions sur l’accueil'), number_format($stats['visits'], 0, ',', ' '), __('cumul depuis le début du suivi'), 'green'],
            [__('Abonnés'), $stats['newsletter_confirmed'], __(':count en attente', ['count' => $stats['newsletter_pending']]), 'violet'],
        ] as [$label, $value, $detail, $color])
            <article class="group relative overflow-hidden rounded-2xl border border-border bg-card-bg p-4 transition hover:-translate-y-0.5 sm:p-5 hover:border-accent/30">
                <div class="absolute -right-8 -top-8 size-24 rounded-full bg-accent/5 blur-2xl"></div><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-text-main/45">{{ $label }}</p><p class="mt-3 text-2xl font-semibold tracking-tight text-text-bold sm:text-3xl">{{ $value }}</p><p class="mt-2 text-xs text-text-main/50">{{ $detail }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 rounded-2xl border border-border bg-card-bg p-5 sm:p-6" aria-labelledby="dashboard-actions-title">
        <h2 id="dashboard-actions-title" class="font-semibold text-text-bold">{{ __('À faire') }}</h2>
        <p class="mt-1 text-xs text-text-main/60">{{ __('Accède directement aux éléments à vérifier.') }}</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($alerts as $alert)
                <a href="{{ $alert['url'] }}" class="flex items-center justify-between gap-3 rounded-xl border border-border bg-bg/40 px-4 py-4 transition hover:border-accent/40 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent">
                    <span class="text-sm text-text-main">{{ $alert['label'] }}</span>
                    <span class="font-mono text-xl font-semibold {{ $alert['count'] > 0 ? 'text-amber-300' : 'text-text-main/50' }}">{{ $alert['count'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($recentTaskFailures->isNotEmpty())
        <section class="mt-6 rounded-2xl border border-red-400/25 bg-red-400/[.04] p-5 sm:p-6" aria-labelledby="dashboard-failures-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="dashboard-failures-title" class="font-semibold text-red-300">{{ __('Échecs récents') }}</h2>
                    <p class="mt-1 text-xs text-text-main/60">{{ __('Tâches et traitements en échec ces dernières 24 heures.') }}</p>
                </div>
                <a wire:navigate href="{{ route('admin.tasks.index', ['status' => App\Enums\TaskRunStatus::Failed->value]) }}" class="rounded-lg border border-border px-3 py-2 text-xs text-text-main/70 transition hover:border-accent/40 hover:text-accent">{{ __('Voir l’historique →') }}</a>
            </div>
            <div class="mt-4 divide-y divide-border">
                @foreach ($recentTaskFailures as $failure)
                    <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-text-bold">{{ $failure->label() }}</p>
                            <p class="truncate text-xs text-text-main/55">{{ $failure->subject ?? $failure->message }}</p>
                        </div>
                        <span class="shrink-0 font-[var(--font-mono)] text-[10px] text-text-main/45" title="{{ $failure->started_at->format(__('d/m/Y à H:i:s')) }}">{{ $failure->started_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-6 rounded-2xl border border-border bg-card-bg p-5 sm:p-6" aria-labelledby="dashboard-period-title">
        <h2 id="dashboard-period-title" class="font-semibold text-text-bold">{{ __('Les 30 derniers jours') }}</h2>
        <p class="mt-1 text-xs text-text-main/60">{{ __('Aujourd’hui inclus · comparaison avec les 30 jours précédents.') }}</p>
        <dl class="mt-5 grid grid-cols-2 gap-5 xl:grid-cols-4">
            @foreach (['photos' => __('Photos ajoutées'), 'exposure' => __('Temps de capture'), 'subscribers' => __('Inscriptions newsletter'), 'confirmations' => __('Confirmations newsletter')] as $key => $label)
                @php
                    $current = $stats['periods']['current'][$key];
                    $previous = $stats['periods']['previous'][$key];
                    $delta = $current - $previous;
                @endphp
                <div>
                    <dt class="text-xs text-text-main/60">{{ $label }}</dt>
                    <dd class="mt-2 text-2xl font-semibold text-text-bold">{{ $key === 'exposure' ? \App\Support\AcquisitionData::formatDuration($current) : $current }}</dd>
                    <dd class="mt-1 text-xs {{ $delta > 0 ? 'text-green-300' : 'text-text-main/60' }}">{{ __(':value:value2 par rapport aux 30 jours précédents', ['value' => $delta > 0 ? '+' : ($delta < 0 ? '−' : ''), 'value2' => $key === 'exposure' ? \App\Support\AcquisitionData::formatDuration(abs($delta)) : abs($delta)]) }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-4 text-xs text-text-main/50">{{ __('Les photos ajoutées incluent les brouillons. Le temps de capture suit la date d’acquisition. Les chiffres newsletter concernent les abonnés encore inscrits.') }}</p>
    </section>

    <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(300px,.75fr)]">
        <article class="min-w-0 overflow-hidden rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('NightScope') }}</p>
                    <h2 class="mt-1 font-semibold text-text-bold">{{ __('Cette nuit') }}</h2>
                </div>
                <a wire:navigate href="{{ route('admin.nightscope.tonight') }}" class="shrink-0 text-xs text-accent hover:text-sky-300">{{ __('Tonight →') }}</a>
            </div>

            @if (! $tonight->location)
                <p class="rounded-xl border border-dashed border-border bg-bg/40 px-4 py-6 text-sm text-text-main/55">{{ __('Ajoute un lieu d’observation pour scorer la nuit, la Lune et les meilleures cibles.') }}</p>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-border bg-bg/40 p-4">
                        <p class="text-[10px] uppercase tracking-[.16em] text-text-main/45">{{ __('Night score') }}</p>
                        <p class="mt-2 text-3xl font-semibold text-text-bold">{{ $tonight->nightScore?->score ?? '—' }}<span class="text-sm font-normal text-text-main/40">/100</span></p>
                        @if ($tonight->nightScore)
                            <p class="mt-1 text-xs text-text-main/50">{{ __(':value h :value2 min', ['value' => intdiv($tonight->nightScore->darknessMinutes, 60), 'value2' => $tonight->nightScore->darknessMinutes % 60]) }}</p>
                        @endif
                    </div>
                    <div class="rounded-xl border border-border bg-bg/40 p-4">
                        <p class="text-[10px] uppercase tracking-[.16em] text-text-main/45">{{ __('Lune') }}</p>
                        <p class="mt-2 text-3xl font-semibold text-text-bold">{{ $tonight->moon ? round($tonight->moon->illuminationPercent).'%' : '—' }}</p>
                        @if($tonight->moon?->rise || $tonight->moon?->set)
                            <p class="mt-1 text-xs text-text-main/50">{{ $tonight->moon->rise?->format('H:i') ?? '—' }} → {{ $tonight->moon->set?->format('H:i') ?? '—' }}</p>
                        @else
                            <p class="mt-1 text-xs text-text-main/50">{{ __('illumination') }}</p>
                        @endif
                    </div>
                    <div class="rounded-xl border border-border bg-bg/40 p-4">
                        <p class="text-[10px] uppercase tracking-[.16em] text-text-main/45">{{ __('Météo') }}</p>
                        <p class="mt-2 text-3xl font-semibold text-text-bold">{{ $tonight->weather?->score ?? '—' }}<span class="text-sm font-normal text-text-main/40">/100</span></p>
                        <p class="mt-1 truncate text-xs text-text-main/50">{{ $tonight->weather?->label ?? __('Indisponible') }}</p>
                    </div>
                </div>

                <div class="mt-5 divide-y divide-border rounded-xl border border-border">
                    @forelse (array_slice($tonight->targets, 0, 3) as $result)
                        <a wire:navigate href="{{ route('admin.nightscope.targets.show', $result->target) }}" class="flex min-h-12 items-center gap-3 px-4 py-3 transition hover:bg-white/[.02]">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full border border-accent/25 bg-accent/10 font-[var(--font-mono)] text-xs font-bold text-accent">{{ $result->score }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-text-bold">{{ $result->target->displayName() }}</span>
                                <span class="block truncate font-[var(--font-mono)] text-[10px] text-text-main/45">{{ __(':value · mag :value2', ['value' => $result->target->constellation ?: '—', 'value2' => $result->target->magnitude !== null ? number_format($result->target->magnitude, 1, ',', ' ') : '—']) }}</span>
                            </span>
                            <span class="text-text-main/30">→</span>
                        </a>
                    @empty
                        <p class="px-4 py-5 text-sm text-text-main/45">{{ __('Aucune cible recommandée pour ce soir.') }}</p>
                    @endforelse
                </div>
            @endif
        </article>

        <div class="grid gap-6">
            <article class="min-w-0 overflow-hidden rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Planner') }}</p>
                        <h2 class="mt-1 font-semibold text-text-bold">{{ __('Prochaine session') }}</h2>
                    </div>
                    <a wire:navigate href="{{ route('admin.nightscope.planner') }}" class="shrink-0 text-xs text-accent hover:text-sky-300">{{ __('Plans →') }}</a>
                </div>
                @if ($nextPlan)
                    <a wire:navigate href="{{ route('admin.nightscope.plans.show', $nextPlan) }}" class="block rounded-xl border border-border bg-bg/40 p-4 transition hover:border-accent/30">
                        <p class="font-[var(--font-mono)] text-xs uppercase tracking-[.14em] text-accent">{{ $nextPlan->status->label() }}</p>
                        <p class="mt-2 text-lg font-semibold text-text-bold">{{ $nextPlan->date->translatedFormat('l d F') }}</p>
                        <p class="mt-1 text-xs text-text-main/50">{{ __(':value · :count cible:value2', ['value' => $nextPlan->equipmentSetup?->name ?? __('Rig non renseigné'), 'count' => $nextPlan->targets->count(), 'value2' => $nextPlan->targets->count() > 1 ? 's' : '']) }}</p>
                        @if ($nextPlan->targets->isNotEmpty())
                            <p class="mt-3 truncate font-[var(--font-mono)] text-[11px] text-text-main/60">{{ $nextPlan->targets->map(fn ($target) => $target->celestialObject?->designation())->filter()->join(' · ') }}</p>
                        @endif
                    </a>
                @else
                    <p class="text-sm text-text-main/50">{{ __('Aucun plan à venir. Génère une nuit depuis le planner.') }}</p>
                @endif
            </article>

            <article class="min-w-0 overflow-hidden rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Vault') }}</p>
                <h2 class="mt-1 font-semibold text-text-bold">{{ __('Santé du catalogue') }}</h2>
                <dl class="mt-5 grid gap-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-text-main/45">{{ __('Objets importés') }}</dt>
                        <dd class="font-[var(--font-mono)] text-sm text-text-bold">{{ number_format($stats['catalog_objects'], 0, ',', ' ') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-text-main/45">{{ __('Photos sans objet lié') }}</dt>
                        <dd class="font-[var(--font-mono)] text-sm {{ $stats['unlinked_photos'] > 0 ? 'text-amber-300' : 'text-text-bold' }}">{{ $stats['unlinked_photos'] }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-xs text-text-main/45">{{ __('Distances manquantes') }}</dt>
                        <dd class="font-[var(--font-mono)] text-sm text-text-bold">{{ number_format($stats['missing_distances'], 0, ',', ' ') }}</dd>
                    </div>
                </dl>
            </article>
        </div>
    </section>

    <section class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,.7fr)]">
        <article class="min-w-0 overflow-hidden rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <div class="mb-6 flex items-end justify-between"><div><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Intégration') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Temps de pose sur 12 mois') }}</h2></div><p class="text-xs text-text-main/45">{{ __('en heures') }}</p></div>
            @php($maxHours = max(1, collect($stats['chart'])->max('hours')))
            <div class="flex h-56 items-end gap-2 sm:gap-3">
                @foreach ($stats['chart'] as $month)
                    <div class="group flex min-w-0 flex-1 flex-col items-center gap-2"><span class="text-[10px] text-text-main/0 transition group-hover:text-text-main/70">{{ $month['hours'] }}</span><div class="w-full rounded-t bg-gradient-to-t from-accent/40 to-accent transition group-hover:from-accent/70" style="height: {{ max(3, ($month['hours'] / $maxHours) * 170) }}px"></div><span class="font-[var(--font-mono)] text-[9px] uppercase text-text-main/40">{{ $month['label'] }}</span></div>
                @endforeach
            </div>
        </article>
        <article class="min-w-0 overflow-hidden rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Signal céleste') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Activité du vault') }}</h2>
            <dl class="mt-6 grid gap-5"><div><dt class="text-xs text-text-main/45">{{ __('Cibles uniques') }}</dt><dd class="mt-1 text-2xl font-semibold text-text-bold">{{ $stats['targets'] }}</dd></div><div><dt class="text-xs text-text-main/45">{{ __('Cible favorite') }}</dt><dd class="mt-1 font-[var(--font-mono)] text-sm text-accent">{{ $stats['favorite_target'] ?? '—' }}</dd></div><div><dt class="text-xs text-text-main/45">{{ __('Nouvelles photos ce mois') }}</dt><dd class="mt-1 text-2xl font-semibold text-text-bold">{{ $stats['month_photos'] }}</dd></div><div class="rounded-xl border border-border bg-bg/50 p-4"><dt class="text-xs text-text-main/45">{{ __('Stockage R2') }}</dt>@if($storage)<dd class="mt-2 text-sm text-text-bold">{{ __(':value · :value2 fichiers', ['value' => \Illuminate\Support\Number::fileSize($storage['size']), 'value2' => number_format($storage['count'])]) }}</dd><div class="mt-3 h-1.5 overflow-hidden rounded-full bg-border"><div class="h-full rounded-full bg-green-400" style="width: {{ min(100, ($storage['size'] / (10 * 1024 ** 3)) * 100) }}%"></div></div>@else<dd class="mt-2 text-sm text-amber-300">{{ __('Statistiques momentanément indisponibles') }}</dd>@endif</div></dl>
        </article>
    </section>

    <section class="mt-6 rounded-2xl border border-border bg-card-bg">
        <div class="flex items-center justify-between border-b border-border p-5 sm:p-6"><div><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Dernières entrées') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Astrophotos récentes') }}</h2></div><a wire:navigate href="{{ route('admin.astrophotos.index') }}" class="text-xs text-accent hover:text-sky-300">{{ __('Tout voir →') }}</a></div>
        <div class="divide-y divide-border">@forelse ($recentPhotos as $photo)<a wire:navigate href="{{ route('admin.astrophotos.edit', $photo) }}" class="flex items-center gap-4 p-4 transition hover:bg-white/[.02] sm:px-6"><div class="size-12 overflow-hidden rounded-lg border border-border bg-bg">@if($photo->thumbnailUrl())<img src="{{ $photo->thumbnailUrl() }}" alt="" class="size-full object-cover">@endif</div><div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-text-bold">{{ $photo->title }}</p><p class="mt-0.5 truncate font-[var(--font-mono)] text-[10px] text-text-main/45">{{ $photo->target ?: __('Sans cible') }} · {{ $photo->captured_at?->format('d.m.Y') }}</p></div><span class="text-text-main/30">→</span></a>@empty<p class="p-8 text-center text-sm text-text-main/45">{{ __('Le vault est encore vide.') }}</p>@endforelse</div>
    </section>
</x-admin.layout>
