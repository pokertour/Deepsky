<x-admin.layout title="{{ __('Rigs') }}" eyebrow="{{ __('Configurations matérielles') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.equipment-setups.create') }}" class="rounded-xl bg-accent px-3 py-2 text-xs font-semibold text-slate-950 hover:bg-sky-300 sm:px-4 sm:text-sm">{{ __('+ Nouveau rig') }}</a></x-slot:actions>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse($equipmentSetups as $equipmentSetup)
            <article class="flex flex-col rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-text-bold">{{ $equipmentSetup->name }}</h2>
                            @if($equipmentSetup->is_default)<span class="rounded-md border border-green-400/25 bg-green-400/10 px-2 py-1 text-[10px] text-green-300">{{ __('Par défaut') }}</span>@endif
                        </div>
                        @if($equipmentSetup->description)<p class="mt-2 text-sm leading-relaxed text-text-main/55">{{ $equipmentSetup->description }}</p>@endif
                    </div>
                    <span class="font-[var(--font-mono)] text-xs text-accent">{{ __(':count items', ['count' => $equipmentSetup->setupItems->count()]) }}</span>
                </div>

                <div class="mt-5 grid gap-2">
                    @forelse($equipmentSetup->setupItems as $item)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-border bg-bg/50 px-3 py-2">
                            <span class="min-w-0 truncate text-sm text-text-bold">{{ $item->brand }} {{ $item->name }}</span>
                            <span class="shrink-0 text-[10px] text-text-main/45">{{ $item->pivot->role ?: $item->type->label() }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-text-main/40">{{ __('Rig vide.') }}</p>
                    @endforelse
                </div>

                <div class="mt-auto flex gap-2 pt-6">
                    <a wire:navigate href="{{ route('admin.equipment-setups.edit', $equipmentSetup) }}" class="flex-1 rounded-lg border border-border px-3 py-2 text-center text-xs text-text-bold hover:border-accent/40 hover:text-accent">{{ __('Éditer') }}</a>
                    <form action="{{ route('admin.equipment-setups.destroy', $equipmentSetup) }}" method="POST" data-confirm="{{ __('La configuration sera supprimée. Les équipements qui la composent sont conservés.') }}" data-confirm-title="{{ __('Supprimer le rig ?') }}" data-confirm-label="{{ __('Supprimer') }}" data-confirm-tone="danger">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-border px-3 py-2 text-xs text-text-main/50 hover:border-red-400/40 hover:text-red-300">{{ __('Supprimer') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-2xl border border-dashed border-border p-12 text-center text-sm text-text-main/45">{{ __('Aucun rig configuré.') }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $equipmentSetups->links('pagination.deepsky') }}</div>
</x-admin.layout>
