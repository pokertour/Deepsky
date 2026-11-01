<x-admin.layout title="{{ __('À propos') }}" eyebrow="{{ __('Deepsky') }}">
    <section class="relative overflow-hidden rounded-2xl border border-border bg-card-bg p-6 sm:p-8">
        <div class="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full bg-accent/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center">
            <img src="{{ asset('assets/images/logo/deepsky.svg') }}" alt="" class="size-16 shrink-0 rounded-2xl shadow-[0_0_40px_rgba(88,166,255,.25)]">
            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-bold tracking-tight text-text-bold">Deepsky</h2>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-text-main/70">{{ __('Portfolio d’astrophotographie auto-hébergé qui pense comme un observatoire : galerie résolue par astrométrie, ciel en direct, réalité augmentée, et NightScope pour préparer les nuits d’imagerie.') }}</p>
            </div>
            @if ($updateAvailable)
                <span class="inline-flex shrink-0 items-center gap-2 self-start rounded-full border border-accent/40 bg-accent/10 px-3 py-1.5 text-xs font-medium text-accent sm:self-center"><span class="size-2 animate-pulse rounded-full bg-accent"></span>{{ __('Nouvelle version disponible') }}</span>
            @elseif ($isUpToDate)
                <span class="inline-flex shrink-0 items-center gap-2 self-start rounded-full border border-green-400/30 bg-green-400/10 px-3 py-1.5 text-xs font-medium text-green-300 sm:self-center"><span class="size-2 rounded-full bg-green-400"></span>{{ __('À jour') }}</span>
            @endif
        </div>
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-text-main/45">{{ __('Version installée') }}</p>
            <p class="mt-3 font-[var(--font-mono)] text-3xl font-semibold text-text-bold">{{ $currentVersion }}</p>
            @if ($currentVersion === 'local-dev')
                <p class="mt-2 text-xs text-text-main/50">{{ __('Version de développement : la comparaison avec les releases est désactivée.') }}</p>
            @endif
        </section>

        <section @class(['rounded-2xl border p-5 sm:p-6', 'border-accent/40 bg-accent/[.06]' => $updateAvailable, 'border-border bg-card-bg' => ! $updateAvailable])>
            <div class="flex items-start justify-between gap-3">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-text-main/45">{{ __('Dernière version publiée') }}</p>
                <form method="POST" action="{{ route('admin.about.refresh') }}">@csrf
                    <button class="rounded-lg border border-border px-2.5 py-1.5 text-[11px] text-text-main/70 transition hover:border-accent/40 hover:text-accent">{{ __('⟳ Vérifier') }}</button>
                </form>
            </div>
            @if ($latest && $latest['tag'])
                <p class="mt-3 font-[var(--font-mono)] text-3xl font-semibold text-text-bold">{{ $latest['tag'] }}</p>
                <p class="mt-2 text-xs text-text-main/55">
                    @if ($latest['published_at'])
                        {{ __('Publiée le :date', ['date' => \Illuminate\Support\Carbon::parse($latest['published_at'])->timezone(config('admin.timezone'))->translatedFormat('j F Y')]) }} ·
                    @endif
                    <a href="{{ $latest['url'] }}" target="_blank" rel="noopener" class="text-accent hover:underline">{{ __('Voir sur GitHub ↗') }}</a>
                </p>
            @elseif ($latest)
                <p class="mt-3 text-sm text-text-main/60">{{ __('Aucune version publiée pour le moment.') }}</p>
            @else
                <p class="mt-3 text-sm text-text-main/60">{{ __('Impossible de joindre GitHub pour le moment.') }}</p>
            @endif
            @if ($latest)
                <p class="mt-3 text-[11px] text-text-main/40">{{ __('Vérifié :time', ['time' => \Illuminate\Support\Carbon::parse($latest['checked_at'])->diffForHumans()]) }}</p>
            @endif
        </section>
    </div>

    @if ($updateAvailable)
        <section class="mt-6 rounded-2xl border border-accent/30 bg-card-bg p-5 sm:p-6">
            <h2 class="font-semibold text-text-bold">{{ __('Mettre à jour') }}</h2>
            <p class="mt-1 text-sm text-text-main/60">{{ __('Sur le serveur, dans le dossier du docker-compose.yml :') }}</p>
            <pre class="mt-3 overflow-x-auto rounded-xl border border-border bg-bg px-4 py-3 font-[var(--font-mono)] text-xs text-text-bold">docker compose pull
docker compose up -d</pre>
            <p class="mt-2 text-xs text-text-main/50">{{ __('Les migrations de la base sont appliquées automatiquement au démarrage du conteneur.') }}</p>
        </section>
    @endif

    @if ($releaseNotes)
        <details class="group mt-6 rounded-2xl border border-border bg-card-bg p-5 sm:p-6" @if($updateAvailable) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-semibold text-text-bold">
                {{ __('Notes de version :version', ['version' => $latest['tag']]) }}
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4 text-text-main/50 transition-transform group-open:rotate-180" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg>
            </summary>
            <div class="release-notes mt-4">{!! $releaseNotes !!}</div>
        </details>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <h2 class="font-semibold text-text-bold">{{ __('Créateur et liens') }}</h2>
            <div class="mt-4 flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-full bg-accent/15 text-sm font-bold text-accent" aria-hidden="true">PT</span>
                <div>
                    <p class="font-medium text-text-bold">pokertour</p>
                    <p class="text-xs text-text-main/55">{{ __('Astrophotographe amateur nantais') }}</p>
                </div>
            </div>
            <ul class="mt-5 grid gap-1 text-sm">
                @foreach ([
                    ['https://deepsky.nexus', __('Site public'), 'deepsky.nexus'],
                    ['https://github.com/'.$repository, __('Code source'), 'github.com/'.$repository],
                    ['https://pokertour.github.io/Deepsky/', __('Page du projet'), 'pokertour.github.io/Deepsky'],
                    ['https://github.com/'.$repository.'/issues', __('Signaler un problème'), __('Issues GitHub')],
                    ['https://github.com/'.$repository.'/releases', __('Historique des versions'), __('Releases GitHub')],
                ] as [$url, $label, $display])
                    <li><a href="{{ $url }}" target="_blank" rel="noopener" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 transition hover:bg-white/[.04]"><span class="text-text-main/70">{{ $label }}</span><span class="truncate font-[var(--font-mono)] text-xs text-accent">{{ $display }} ↗</span></a></li>
                @endforeach
            </ul>
            <p class="mt-4 border-t border-border pt-4 text-xs leading-relaxed text-text-main/50">{{ __('Code sous licence MIT. Les astrophotos restent © pokertour, tous droits réservés.') }}</p>
        </section>

        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <h2 class="font-semibold text-text-bold">{{ __('Système') }}</h2>
            <dl class="mt-4 grid gap-1 text-sm">
                @foreach ($system as $label => $value)
                    <div class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 odd:bg-white/[.02]"><dt class="text-text-main/60">{{ $label }}</dt><dd class="truncate font-[var(--font-mono)] text-xs text-text-bold">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </section>
    </div>
</x-admin.layout>
