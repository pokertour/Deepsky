<x-admin.layout title="{{ __('Tâches') }}" eyebrow="{{ __('Historique des traitements') }}">
    <section class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4" aria-label="{{ __('Dernières 24 heures') }}">
        @foreach (App\Enums\TaskRunStatus::cases() as $status)
            <a wire:navigate href="{{ route('admin.tasks.index', ['status' => $status->value]) }}" class="rounded-2xl border border-border bg-card-bg p-4 transition hover:-translate-y-0.5 sm:p-5 hover:border-accent/30">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-text-main/45">{{ __(':label · 24 h', ['label' => $status->label()]) }}</p>
                <p @class(['mt-3 text-2xl font-semibold tracking-tight sm:text-3xl', 'text-red-300' => $status === App\Enums\TaskRunStatus::Failed && $lastDayCounts[$status->value] > 0, 'text-text-bold' => ! ($status === App\Enums\TaskRunStatus::Failed && $lastDayCounts[$status->value] > 0)])>{{ $lastDayCounts[$status->value] }}</p>
            </a>
        @endforeach
    </section>

    <section class="mt-6 rounded-2xl border border-border bg-card-bg p-5 sm:p-6" aria-labelledby="scheduled-title">
        <h2 id="scheduled-title" class="font-semibold text-text-bold">{{ __('Tâches planifiées') }}</h2>
        <p class="mt-1 text-xs text-text-main/60">{{ __('Dernier passage de chaque commande automatique. « Lancer » l’exécute tout de suite via la file d’attente.') }}</p>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($scheduledCommands as $name => $lastRun)
                <article class="flex flex-col rounded-xl border border-border bg-bg/40 px-4 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <a wire:navigate href="{{ route('admin.tasks.index', ['name' => $name]) }}" class="text-sm font-medium text-text-bold hover:text-accent">{{ App\Models\TaskRun::labelFor($name) }}</a>
                        @if ($lastRun)
                            <span class="shrink-0 rounded-md border px-2 py-1 text-[10px] {{ $lastRun->status->badgeClasses() }}">{{ $lastRun->status->label() }}</span>
                        @endif
                    </div>
                    <p class="mt-2 font-[var(--font-mono)] text-[10px] text-text-main/45">{{ $name }}</p>
                    <p class="mt-1 flex-1 text-xs text-text-main/60" @if($lastRun) title="{{ $lastRun->started_at->format(__('d/m/Y à H:i:s')) }}" @endif>
                        @if ($lastRun)
                            {{ $lastRun->started_at->diffForHumans() }}@if($lastRun->formattedDuration()) · {{ $lastRun->formattedDuration() }}@endif
                        @else
                            {{ __('Jamais exécutée depuis l’activation de l’historique') }}
                        @endif
                    </p>
                    @if (in_array($name, App\Support\RunnableCommands::names(), true))
                        <form method="POST" action="{{ route('admin.tasks.store') }}" class="mt-3" @if(App\Support\RunnableCommands::isDestructive($name)) data-confirm="{{ __('Cette tâche supprime des données. La lancer maintenant ?') }}" data-confirm-title="{{ __('Lancer une tâche destructive ?') }}" data-confirm-label="{{ __('Lancer') }}" data-confirm-tone="danger" @endif>
                            @csrf
                            <input type="hidden" name="command" value="{{ $name }}">
                            <button class="w-full rounded-lg border border-accent/30 bg-accent/10 px-3 py-2 text-xs font-medium text-accent transition hover:bg-accent/20">{{ __('▶ Lancer') }}</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <form method="GET" class="mt-6 mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_200px_200px_auto]">
        <select name="name" aria-label="{{ __('Tâche') }}" class="rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Toutes les tâches') }}</option>
            @foreach ($names as $name)
                <option value="{{ $name }}" @selected(request('name') === $name)>{{ App\Models\TaskRun::labelFor($name) }}</option>
            @endforeach
        </select>
        <select name="type" aria-label="{{ __('Type') }}" class="rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Tous les types') }}</option>
            @foreach (App\Enums\TaskRunType::cases() as $type)
                <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <select name="status" aria-label="{{ __('Statut') }}" class="rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Tous les statuts') }}</option>
            @foreach (App\Enums\TaskRunStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button class="flex-1 rounded-xl border border-accent/30 bg-accent/10 px-5 py-3 text-sm text-accent">{{ __('Filtrer') }}</button>
            @if (request()->hasAny(['name', 'type', 'status']))
                <a wire:navigate href="{{ route('admin.tasks.index') }}" class="rounded-xl border border-border px-4 py-3 text-center text-sm text-text-main/60 hover:text-text-bold">{{ __('Effacer') }}</a>
            @endif
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-border bg-card-bg">
        <div class="hidden grid-cols-[120px_1fr_170px_110px] gap-4 border-b border-border px-6 py-3 font-[var(--font-mono)] text-[9px] uppercase tracking-[.16em] text-text-main/40 md:grid">
            <span>{{ __('Statut') }}</span><span>{{ __('Tâche') }}</span><span>{{ __('Début') }}</span><span>{{ __('Durée') }}</span>
        </div>
        <div class="divide-y divide-border">
            @forelse ($runs as $run)
                <div class="grid grid-cols-1 gap-2 p-4 md:grid-cols-[120px_1fr_170px_110px] md:items-start md:gap-4 md:px-6">
                    <div><span class="rounded-md border px-2 py-1 text-[10px] {{ $run->status->badgeClasses() }}">{{ $run->status->label() }}</span></div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-text-bold">{{ $run->label() }}</p>
                        <p class="mt-1 truncate font-[var(--font-mono)] text-[10px] text-text-main/45">
                            {{ $run->type->label() }}@if($run->queue) {{ __('· file :queue', ['queue' => $run->queue]) }}@endif @if($run->attempts > 1) {{ __('· tentative :attempts', ['attempts' => $run->attempts]) }}@endif
                        </p>
                        @if ($run->subject)
                            <p class="mt-1 truncate text-xs text-text-main/65">{{ $run->subject }}</p>
                        @endif
                        @if ($run->message)
                            <details class="mt-2 text-xs">
                                <summary class="cursor-pointer text-red-300/90">{{ __('Détail de l’erreur') }}</summary>
                                <p class="mt-2 whitespace-pre-wrap break-words rounded-lg border border-border bg-bg/60 p-3 font-[var(--font-mono)] text-[11px] text-text-main/70">{{ $run->message }}</p>
                            </details>
                        @endif
                    </div>
                    <p class="text-xs text-text-main/55" title="{{ $run->started_at->diffForHumans() }}">{{ $run->started_at->format(__('d/m/Y à H:i:s')) }}</p>
                    <p class="font-[var(--font-mono)] text-xs text-text-main/55">{{ $run->formattedDuration() ?? '—' }}</p>
                </div>
            @empty
                <p class="p-10 text-center text-sm text-text-main/45">{{ __('Aucune exécution enregistrée.') }}</p>
            @endforelse
        </div>
    </div>
    <div class="mt-6">{{ $runs->links('pagination.deepsky') }}</div>
</x-admin.layout>
