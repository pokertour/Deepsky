<x-admin.layout title="{{ __('Mes favoris') }}" eyebrow="{{ __('NightScope · Liste de cibles') }}">
    <x-slot:actions>
        <a wire:navigate href="{{ route('admin.nightscope.tonight', ['favorites_only' => 1, 'date' => $date->format('Y-m-d')]) }}" class="inline-flex min-h-11 items-center rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950">{{ __('Mes favoris ce soir') }}</a>
    </x-slot:actions>
    <form method="GET" class="mb-5 flex flex-wrap gap-3">
        <label class="text-sm text-text-main">{{ __('Nuit à préparer') }} <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="ml-2 min-h-11 rounded-xl border border-border bg-card-bg px-3 text-text-bold"></label>
        <button class="min-h-11 rounded-xl border border-border px-4 py-2 text-sm text-accent">{{ __('Choisir') }}</button>
    </form>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($items as $item)
            <article class="rounded-2xl border border-border bg-card-bg p-5">
                <a wire:navigate href="{{ route('admin.nightscope.targets.show', ['celestialObject' => $item->celestialObject, 'date' => $date->format('Y-m-d')]) }}" class="text-lg font-semibold text-text-bold hover:text-accent">{{ $item->celestialObject->displayName() }}</a>
                <p class="mt-1 text-xs text-text-main/55">{{ $item->celestialObject->constellation }} · {{ $item->celestialObject->type }}</p>
                <form method="POST" action="{{ route('admin.nightscope.wishlist.update', $item->celestialObject) }}" class="mt-4 space-y-3">
                    @csrf @method('PUT')
                    <label class="block text-sm text-text-main">{{ __('Priorité') }}
                        <select name="priority" class="mt-1 min-h-11 w-full rounded-xl border border-border bg-bg px-3 text-text-bold">
                            @foreach([1 => __('Haute'), 2 => __('Normale'), 3 => __('Basse')] as $value => $label)
                                <option value="{{ $value }}" @selected($item->priority == $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm text-text-main">{{ __('Note personnelle') }}
                        <textarea name="note" rows="3" maxlength="2000" class="mt-1 w-full rounded-xl border border-border bg-bg p-3 text-text-bold">{{ $item->note }}</textarea>
                    </label>
                    <button class="min-h-11 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-slate-950">{{ __('Enregistrer') }}</button>
                    @error('priority')<p class="text-xs text-red-300">{{ $message }}</p>@enderror
                    @error('note')<p class="text-xs text-red-300">{{ $message }}</p>@enderror
                </form>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <x-admin.wishlist-button :target="$item->celestialObject" :favorite="$item" />
                    <form method="POST" action="{{ route('admin.nightscope.planner.draft.targets') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                        <input type="hidden" name="celestial_object_id" value="{{ $item->celestial_object_id }}">
                        <button class="min-h-11 rounded-xl border border-border px-3 py-2 text-xs text-accent">{{ __('Ajouter au Planner') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="rounded-2xl border border-border bg-card-bg p-6 text-text-main">{{ __('Ajoute des cibles depuis Tonight ou le catalogue pour préparer ta liste.') }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $items->links('pagination.deepsky') }}</div>
</x-admin.layout>
