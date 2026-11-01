@props(['celestialObject' => null])

@php
    $input = 'w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text-bold outline-none transition focus:border-accent focus:ring-4 focus:ring-accent/10';
    $label = 'grid gap-2 text-sm font-medium text-text-bold';
    $alternativeNames = old('alternative_names_text', $celestialObject?->alternative_names ? implode(', ', $celestialObject->alternative_names) : '');
@endphp

<div class="mx-auto grid max-w-5xl gap-6">
    <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-8">
        <div class="mb-7">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Catalogue céleste') }}</p>
            <h2 class="mt-1 text-lg font-semibold text-text-bold">{{ __('Identité et coordonnées') }}</h2>
            <p class="mt-2 text-sm text-text-main/50">{{ __('Les coordonnées sont stockées en degrés décimaux pour rester compatibles avec l’astrométrie existante.') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <label class="{{ $label }}">
                {{ __('Catalogue') }}
                <input name="catalog" value="{{ old('catalog', $celestialObject?->catalog) }}" required class="{{ $input }}" placeholder="{{ __('M, NGC, IC') }}">
                @error('catalog')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="{{ $label }}">
                {{ __('Numéro') }}
                <input name="catalog_number" value="{{ old('catalog_number', $celestialObject?->catalog_number) }}" required class="{{ $input }}" placeholder="31">
                @error('catalog_number')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="{{ $label }} sm:col-span-2">
                {{ __('Nom commun') }}
                <input name="name" value="{{ old('name', $celestialObject?->name) }}" class="{{ $input }}" placeholder="{{ __('Galaxie d’Andromède') }}">
            </label>
            <label class="{{ $label }} sm:col-span-2">
                {{ __('Noms alternatifs') }}
                <input name="alternative_names_text" value="{{ $alternativeNames }}" class="{{ $input }}" placeholder="{{ __('Andromeda Galaxy, M31') }}">
            </label>
            <label class="{{ $label }}">
                {{ __('Type') }}
                <input name="type" value="{{ old('type', $celestialObject?->type) }}" class="{{ $input }}" placeholder="{{ __('galaxy') }}">
            </label>
            <label class="{{ $label }}">
                {{ __('Constellation') }}
                <input name="constellation" value="{{ old('constellation', $celestialObject?->constellation) }}" class="{{ $input }}" placeholder="{{ __('Andromeda') }}">
            </label>
            <label class="{{ $label }}">
                {{ __('Ascension droite') }}
                <div class="relative"><input name="ra_deg" type="number" min="0" max="359.9999999" step="0.0000001" value="{{ old('ra_deg', $celestialObject?->ra_deg) }}" required class="{{ $input }} pr-12"><span class="pointer-events-none absolute inset-y-0 right-4 grid place-items-center text-xs text-text-main/35">{{ __('deg') }}</span></div>
                @error('ra_deg')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="{{ $label }}">
                {{ __('Déclinaison') }}
                <div class="relative"><input name="dec_deg" type="number" min="-90" max="90" step="0.0000001" value="{{ old('dec_deg', $celestialObject?->dec_deg) }}" required class="{{ $input }} pr-12"><span class="pointer-events-none absolute inset-y-0 right-4 grid place-items-center text-xs text-text-main/35">{{ __('deg') }}</span></div>
                @error('dec_deg')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
        </div>
    </section>

    <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-8">
        <div class="mb-5"><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Photométrie et dimensions') }}</p></div>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['magnitude', __('Magnitude'), null, '0.01'],
                ['surface_brightness', __('Brillance de surface'), null, '0.01'],
                ['angular_width_arcmin', __('Largeur angulaire'), 'arcmin', '0.001'],
                ['angular_height_arcmin', __('Hauteur angulaire'), 'arcmin', '0.001'],
                ['distance_ly', __('Distance'), __('années-lumière'), '0.001'],
            ] as [$name, $fieldLabel, $suffix, $step])
                <label class="{{ $label }}">
                    {{ $fieldLabel }}
                    <div class="relative"><input name="{{ $name }}" type="number" step="{{ $step }}" value="{{ old($name, $celestialObject?->{$name}) }}" class="{{ $input }} {{ $suffix ? 'pr-28' : '' }}">@if($suffix)<span class="pointer-events-none absolute inset-y-0 right-4 grid place-items-center text-xs text-text-main/35">{{ $suffix }}</span>@endif</div>
                    @error($name)<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                </label>
            @endforeach
        </div>
    </section>

    <button class="w-full rounded-xl bg-accent px-5 py-3.5 text-sm font-semibold text-slate-950 hover:bg-sky-300">{{ $celestialObject ? __('Enregistrer l’objet') : __('Ajouter au catalogue') }}</button>
</div>
