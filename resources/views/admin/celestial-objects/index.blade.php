<x-admin.layout title="{{ __('Catalogue céleste') }}" eyebrow="{{ __('NightScope · Cibles') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.celestial-objects.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-sky-300">{{ __('+ Ajouter') }}</a></x-slot:actions>

    <form method="GET" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
        <input name="search" value="{{ request('search') }}" placeholder="{{ __('M 31, C 20, Sh2-155, Andromède…') }}" class="min-h-11 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent sm:col-span-2">
        <select name="catalog" class="min-h-11 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Catalogue') }}</option>
            @foreach($catalogs as $catalog)<option value="{{ $catalog }}" @selected(request('catalog') === $catalog)>{{ $catalog }}</option>@endforeach
        </select>
        <select name="type" class="min-h-11 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Type') }}</option>
            @foreach($types as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>@endforeach
        </select>
        <select name="constellation" class="min-h-11 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Constellation') }}</option>
            @foreach($constellations as $constellation)<option value="{{ $constellation }}" @selected(request('constellation') === $constellation)>{{ $constellation }}</option>@endforeach
        </select>
        <input name="magnitude_max" type="number" step="0.1" value="{{ request('magnitude_max') }}" placeholder="{{ __('Mag ≤') }}" class="min-h-11 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
        <input name="min_size" type="number" step="0.1" value="{{ request('min_size') }}" placeholder="{{ __('Taille ≥ ′') }}" class="min-h-11 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
        <select name="photographed" class="min-h-11 rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Photos') }}</option>
            <option value="yes" @selected(request('photographed') === 'yes')>{{ __('Photographié') }}</option>
            <option value="no" @selected(request('photographed') === 'no')>{{ __('Jamais') }}</option>
        </select>
        <div class="flex gap-2 sm:col-span-2 xl:col-span-4 2xl:col-auto">
            <button class="min-h-11 flex-1 rounded-xl border border-accent/30 bg-accent/10 px-5 py-3 text-sm text-accent">{{ __('Filtrer') }}</button>
            @if(request()->hasAny(['search', 'catalog', 'type', 'constellation', 'magnitude_max', 'min_size', 'photographed']))
                <a wire:navigate href="{{ route('admin.celestial-objects.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-border px-4 py-3 text-sm text-text-main/60 hover:text-text-bold">{{ __('Effacer') }}</a>
            @endif
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-border bg-card-bg">
        <div class="hidden grid-cols-[110px_1.3fr_1fr_1fr_90px_130px] gap-4 border-b border-border px-6 py-3 font-[var(--font-mono)] text-[9px] uppercase tracking-[.16em] text-text-main/40 lg:grid">
            <span>{{ __('ID') }}</span><span>{{ __('Nom') }}</span><span>{{ __('Type') }}</span><span>{{ __('Constellation') }}</span><span>{{ __('Photos') }}</span><span></span>
        </div>
        <div class="divide-y divide-border">
            @forelse($objects as $object)
                <div class="grid grid-cols-1 gap-3 p-4 lg:grid-cols-[110px_1.3fr_1fr_1fr_90px_130px] lg:items-center lg:gap-4 lg:px-6">
                    <div class="min-w-0 lg:contents">
                        <span class="font-[var(--font-mono)] text-sm text-accent">{{ $object->designation() }}</span>
                        <span class="mt-1 block font-medium text-text-bold lg:mt-0">{{ $object->name ?: '—' }}</span>
                    </div>
                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-text-main/55 lg:contents">
                        <span>{{ $object->type ?: '—' }}</span>
                        <span>{{ $object->constellation ?: '—' }}</span>
                        <span class="font-[var(--font-mono)] text-xs text-text-main/50">{{ __(':astrophotos_count photo(s)', ['astrophotos_count' => $object->astrophotos_count]) }}</span>
                    </div>
                    <div class="flex gap-2">
                        <x-admin.wishlist-button :target="$object" :favorite="$favorites->get($object->id)" />
                        <a wire:navigate href="{{ route('admin.celestial-objects.edit', $object) }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg border border-border px-3 py-2 text-xs text-text-bold hover:border-accent/40 hover:text-accent lg:flex-none">{{ __('Éditer') }}</a>
                        <form action="{{ route('admin.celestial-objects.destroy', $object) }}" method="POST" data-confirm="{{ __('L’objet sera retiré du catalogue. Les astrophotos liées sont conservées.') }}" data-confirm-title="{{ __('Supprimer l’objet ?') }}" data-confirm-label="{{ __('Supprimer') }}" data-confirm-tone="danger">@csrf @method('DELETE')<button class="grid size-10 place-items-center rounded-lg border border-border text-xs text-text-main/50 hover:border-red-400/40 hover:text-red-300">✕</button></form>
                    </div>
                </div>
            @empty
                <p class="p-10 text-center text-sm text-text-main/45">{{ __('Aucun objet ne correspond à ces filtres.') }}</p>
            @endforelse
        </div>
    </div>
    <div class="mt-6">{{ $objects->links('pagination.deepsky') }}</div>
</x-admin.layout>
