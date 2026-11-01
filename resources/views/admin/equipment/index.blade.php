<x-admin.layout title="{{ __('Équipements') }}" eyebrow="{{ __('Configuration optique') }}">
    <x-slot:actions>
        <a wire:navigate href="{{ route('admin.equipment.create') }}" class="rounded-xl bg-accent px-3 py-2 text-xs font-semibold text-slate-950 hover:bg-sky-300 sm:px-4 sm:text-sm">{{ __('+ Ajouter') }}</a>
    </x-slot:actions>

    <form method="GET" class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_220px_auto]">
        <input name="search" value="{{ request('search') }}" placeholder="{{ __('Rechercher une marque, un modèle…') }}" class="rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
        <select name="type" class="rounded-xl border border-border bg-card-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent">
            <option value="">{{ __('Toutes les catégories') }}</option>
            @foreach($typeOptions as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-xl border border-accent/30 bg-accent/10 px-5 py-3 text-sm text-accent">{{ __('Filtrer') }}</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-border bg-card-bg">
        <div class="hidden grid-cols-[1fr_1.2fr_.8fr_120px_120px] gap-4 border-b border-border px-6 py-3 font-[var(--font-mono)] text-[9px] uppercase tracking-[.16em] text-text-main/40 md:grid">
            <span>{{ __('Marque') }}</span><span>{{ __('Modèle') }}</span><span>{{ __('Catégorie') }}</span><span>{{ __('Usages') }}</span><span></span>
        </div>
        <div class="divide-y divide-border">
            @forelse($items as $item)
                @php($photoUses = (int) $item->photo_uses_count)
                <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-[1fr_1.2fr_.8fr_120px_120px] md:items-center md:gap-4 md:px-6">
                    <div><span class="text-[10px] uppercase text-text-main/35 md:hidden">{{ __('Marque ·') }} </span><span class="text-sm text-text-main/70">{{ $item->brand ?: '—' }}</span></div>
                    <div class="font-medium text-text-bold">{{ $item->name }}</div>
                    <div><span class="rounded-md border border-accent/20 bg-accent/10 px-2 py-1 text-[10px] text-accent">{{ $item->type->label() }}</span></div>
                    <div class="font-[var(--font-mono)] text-xs text-text-main/50">{{ __(':photo_uses photo:value · :equipment_setups_count rig:value2', ['photo_uses' => $photoUses, 'value' => $photoUses > 1 ? 's' : '', 'equipment_setups_count' => $item->equipment_setups_count, 'value2' => $item->equipment_setups_count > 1 ? 's' : '']) }}</div>
                    <div class="flex gap-2">
                        <a wire:navigate href="{{ route('admin.equipment.edit', $item) }}" class="rounded-lg border border-border px-3 py-2 text-xs text-text-bold hover:border-accent/40 hover:text-accent">{{ __('Éditer') }}</a>
                        <form action="{{ route('admin.equipment.destroy', $item) }}" method="POST" data-confirm="{{ __('L’équipement sera supprimé de ton inventaire.') }}" data-confirm-title="{{ __('Supprimer l’équipement ?') }}" data-confirm-label="{{ __('Supprimer') }}" data-confirm-tone="danger">
                            @csrf @method('DELETE')
                            <button class="rounded-lg border border-border px-3 py-2 text-xs text-text-main/50 hover:border-red-400/40 hover:text-red-300">✕</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="p-10 text-center text-sm text-text-main/45">{{ __('Aucun équipement trouvé.') }}</p>
            @endforelse
        </div>
    </div>
    <div class="mt-6">{{ $items->links('pagination.deepsky') }}</div>
</x-admin.layout>
