<x-admin.layout title="{{ __('Lieux d’observation') }}" eyebrow="{{ __('NightScope') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.observing-locations.create') }}" class="rounded-xl bg-accent px-3 py-2 text-xs font-semibold text-slate-950 hover:bg-sky-300 sm:px-4 sm:text-sm">{{ __('+ Ajouter') }}</a></x-slot:actions>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 2xl:grid-cols-3">
        @forelse($locations as $location)
            <article class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-text-bold">{{ $location->name }}</h2>
                            @if($location->is_default)<span class="rounded-md border border-green-400/25 bg-green-400/10 px-2 py-1 text-[10px] text-green-300">{{ __('Par défaut') }}</span>@endif
                        </div>
                        <p class="mt-2 font-[var(--font-mono)] text-xs text-accent">{{ number_format($location->latitude, 5) }}°, {{ number_format($location->longitude, 5) }}°</p>
                        @if($location->region)<p class="mt-1 text-xs text-text-main/50">{{ __('Région publique · :region', ['region' => $location->region]) }}</p>@endif
                    </div>
                    <span class="text-xs text-text-main/40">{{ $location->altitude !== null ? number_format($location->altitude).' m' : '—' }}</span>
                </div>
                <div class="mt-5 rounded-xl border border-border bg-bg/50 p-4">
                    <p class="text-xs text-text-main/45">{{ __('Fuseau horaire') }}</p>
                    <p class="mt-1 font-[var(--font-mono)] text-sm text-text-bold">{{ $location->timezone }}</p>
                </div>
                <div class="mt-5 flex gap-2">
                    <a wire:navigate href="{{ route('admin.observing-locations.edit', $location) }}" class="flex-1 rounded-lg border border-border px-3 py-2 text-center text-xs text-text-bold hover:border-accent/40 hover:text-accent">{{ __('Éditer') }}</a>
                    <form action="{{ route('admin.observing-locations.destroy', $location) }}" method="POST" data-confirm="{{ __('Le lieu d’observation et son masque d’horizon seront supprimés.') }}" data-confirm-title="{{ __('Supprimer le lieu ?') }}" data-confirm-label="{{ __('Supprimer') }}" data-confirm-tone="danger">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-border px-3 py-2 text-xs text-text-main/50 hover:border-red-400/40 hover:text-red-300">{{ __('Supprimer') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-border p-12 text-center text-sm text-text-main/45">{{ __('Aucun lieu d’observation.') }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $locations->links('pagination.deepsky') }}</div>
</x-admin.layout>
