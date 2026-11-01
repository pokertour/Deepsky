<x-admin.layout title="{{ __('Newsletter') }}" eyebrow="{{ __('Audience') }}">
    <form method="GET" class="mb-5 grid grid-cols-[minmax(0,1fr)_auto] gap-2 sm:grid-cols-[minmax(0,1fr)_200px_auto] sm:gap-3">
        <input name="search" value="{{ request('search') }}" placeholder="{{ __('Rechercher une adresse email…') }}" class="col-span-2 min-w-0 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent sm:col-span-1">
        <select name="status" aria-label="{{ __('Statut') }}" class="min-w-0 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Tous les statuts') }}</option>
            <option value="confirmed" @selected(request('status') === 'confirmed')>{{ __('Confirmés') }}</option>
            <option value="pending" @selected(request('status') === 'pending')>{{ __('En attente') }}</option>
        </select>
        <button class="rounded-xl border border-accent/30 bg-accent/10 px-5 py-3 text-sm text-accent">{{ __('Filtrer') }}</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-border bg-card-bg">
        <div class="hidden grid-cols-[minmax(0,1fr)_140px_170px_84px] gap-4 border-b border-border px-6 py-3 font-[var(--font-mono)] text-[9px] uppercase tracking-[.16em] text-text-main/40 md:grid">
            <span>{{ __('Adresse email') }}</span><span>{{ __('Statut') }}</span><span>{{ __('Inscription') }}</span><span class="sr-only">{{ __('Actions') }}</span>
        </div>
        <div class="divide-y divide-border">
            @forelse ($subscribers as $subscriber)
                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 px-4 py-3 md:grid-cols-[minmax(0,1fr)_140px_170px_84px] md:gap-4 md:px-6 md:py-4">
                    <p class="min-w-0 truncate font-[var(--font-mono)] text-sm text-text-bold">{{ $subscriber->email }}</p>
                    <div class="row-span-2 flex justify-end gap-1.5 md:order-last md:row-span-1">
                        @unless ($subscriber->isConfirmed())
                            <form action="{{ route('admin.newsletter.resend', $subscriber) }}" method="POST">@csrf
                                <button class="grid size-9 place-items-center rounded-lg border border-border text-text-main/60 transition hover:border-accent/40 hover:text-accent" title="{{ __('Renvoyer') }}" aria-label="{{ __('Renvoyer l’email de confirmation à :email', ['email' => $subscriber->email]) }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.6L20 9M20 4v5h-5M20 12a8 8 0 0 1-13.7 5.6L4 15M4 20v-5h5"/></svg>
                                </button>
                            </form>
                        @endunless
                        <form action="{{ route('admin.newsletter.destroy', $subscriber) }}" method="POST" data-confirm="{{ __('L’adresse ne recevra plus la newsletter.') }}" data-confirm-title="{{ __('Supprimer l’abonné ?') }}" data-confirm-label="{{ __('Supprimer') }}" data-confirm-tone="danger">@csrf @method('DELETE')
                            <button class="grid size-9 place-items-center rounded-lg border border-border text-red-400/70 transition hover:border-red-400/40 hover:text-red-300" title="{{ __('Supprimer') }}" aria-label="{{ __('Supprimer l’abonné :email', ['email' => $subscriber->email]) }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                            </button>
                        </form>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 md:contents">
                        <div>
                            @if ($subscriber->isConfirmed())
                                <span class="rounded-md border border-green-400/20 bg-green-400/10 px-2 py-0.5 text-[10px] text-green-300">{{ __('● Confirmé') }}</span>
                            @else
                                <span class="rounded-md border border-amber-400/20 bg-amber-400/10 px-2 py-0.5 text-[10px] text-amber-300">{{ __('◷ En attente') }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-text-main/50">{{ $subscriber->created_at->timezone(config('admin.timezone'))->format(__('d/m/Y à H:i')) }}</p>
                    </div>
                </div>
            @empty
                <p class="p-10 text-center text-sm text-text-main/45">{{ __('Aucun abonné trouvé.') }}</p>
            @endforelse
        </div>
    </div>
    <div class="mt-6">{{ $subscribers->links('pagination.deepsky') }}</div>
</x-admin.layout>
