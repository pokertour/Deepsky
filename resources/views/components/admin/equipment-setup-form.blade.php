@props(['equipmentSetup' => null, 'setupItems'])

@php
    $input = 'w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text-bold outline-none transition focus:border-accent focus:ring-4 focus:ring-accent/10';
    $selectedIds = collect(old('setup_item_ids', $equipmentSetup?->setupItems->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
    $savedRoles = $equipmentSetup?->setupItems->mapWithKeys(fn ($item) => [$item->id => $item->pivot->role])->all() ?? [];
    $roles = old('roles', $savedRoles);
@endphp

<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,.55fr)]">
    <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-8">
        <div class="mb-7">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Configuration réutilisable') }}</p>
            <h2 class="mt-1 text-lg font-semibold text-text-bold">{{ __('Composition du rig') }}</h2>
            <p class="mt-2 text-sm text-text-main/50">{{ __('Sélectionne les Setup Items utilisés ensemble pendant une nuit.') }}</p>
        </div>

        <div class="grid gap-3">
            @forelse($setupItems as $item)
                <label class="grid grid-cols-1 cursor-pointer gap-3 rounded-xl border border-border bg-bg/50 p-4 transition hover:border-accent/30 sm:grid-cols-[auto_1fr_minmax(150px,.55fr)] sm:items-center">
                    <input type="checkbox" name="setup_item_ids[]" value="{{ $item->id }}" @checked($selectedIds->contains($item->id)) class="size-5 rounded border-border bg-bg text-accent focus:ring-accent/30">
                    <span>
                        <span class="block text-sm font-medium text-text-bold">{{ $item->brand }} {{ $item->name }}</span>
                        <span class="mt-1 block font-[var(--font-mono)] text-[10px] uppercase tracking-wider text-accent/70">{{ $item->type->label() }}</span>
                    </span>
                    <input name="roles[{{ $item->id }}]" value="{{ $roles[$item->id] ?? '' }}" class="{{ $input }}" placeholder="{{ __('Rôle optionnel') }}">
                </label>
            @empty
                <p class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-text-main/45">{{ __('Ajoute d’abord du matériel dans Setup Items.') }}</p>
            @endforelse
        </div>
    </section>

    <aside class="grid content-start gap-5 xl:sticky xl:top-26">
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <div class="grid gap-5">
                <label class="grid gap-2 text-sm font-medium text-text-bold">
                    {{ __('Nom du rig') }}
                    <input name="name" value="{{ old('name', $equipmentSetup?->name) }}" required class="{{ $input }}" placeholder="{{ __('Main DeepSky Rig') }}">
                    @error('name')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                </label>
                <label class="grid gap-2 text-sm font-medium text-text-bold">
                    {{ __('Description') }}
                    <textarea name="description" rows="5" class="{{ $input }}" placeholder="{{ __('Usage, contraintes, notes…') }}">{{ old('description', $equipmentSetup?->description) }}</textarea>
                </label>
                <label class="flex items-center gap-3 rounded-xl border border-border bg-bg/50 p-4 text-sm text-text-bold">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $equipmentSetup?->is_default ?? false)) class="size-5 rounded border-border bg-bg text-accent focus:ring-accent/30">
                    {{ __('Rig par défaut') }}
                </label>
            </div>
        </section>
        <button class="w-full rounded-xl bg-accent px-5 py-3.5 text-sm font-semibold text-slate-950 hover:bg-sky-300">{{ $equipmentSetup ? __('Enregistrer le rig') : __('Créer le rig') }}</button>
    </aside>
</div>
