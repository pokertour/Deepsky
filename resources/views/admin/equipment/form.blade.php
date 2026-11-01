@php
    $input = 'w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text-bold outline-none transition focus:border-accent focus:ring-4 focus:ring-accent/10';
    $specValues = old('specs', $item?->specs?->toArray() ?? []);
    $selectedType = old('type', $item?->type?->value ?? 'camera');
@endphp

<div class="mx-auto grid max-w-4xl gap-6" x-data="setupItemForm" data-initial-type="{{ $selectedType }}">
    <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-8">
        <div class="mb-7">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Référentiel matériel') }}</p>
            <h2 class="mt-1 text-lg font-semibold text-text-bold">{{ __('Détails de l’équipement') }}</h2>
            <p class="mt-2 text-sm text-text-main/50">{{ __('Ce matériel reste la source unique utilisée par les photos et les rigs.') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <label class="grid gap-2 text-sm font-medium text-text-bold">
                {{ __('Marque') }}
                <input name="brand" value="{{ old('brand', $item?->brand) }}" class="{{ $input }}" placeholder="{{ __('ZWO, Sky-Watcher…') }}">
            </label>
            <label class="grid gap-2 text-sm font-medium text-text-bold">
                {{ __('Modèle') }}
                <input name="name" value="{{ old('name', $item?->name) }}" required class="{{ $input }}" placeholder="{{ __('ASI2600MM Pro') }}">
                @error('name')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2 text-sm font-medium text-text-bold sm:col-span-2">
                {{ __('Catégorie') }}
                <select name="type" required class="{{ $input }}" x-model="type">
                    @foreach($typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
        </div>
    </section>

    <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-8">
        <div class="mb-7">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Caractéristiques techniques') }}</p>
            <h2 class="mt-1 text-lg font-semibold text-text-bold">{{ __('Optique et capteur') }}</h2>
            <p class="mt-2 text-sm text-text-main/50">{{ __('Seuls les champs utiles pour la catégorie sélectionnée sont affichés.') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            @foreach($specificationFields as $key => $field)
                <label class="grid gap-2 text-sm font-medium text-text-bold" data-spec-types="{{ implode(',', $field['types']) }}" x-show="shows($el)" x-cloak>
                    {{ $field['label'] }}
                    @if($field['type'] === 'select')
                        <select name="specs[{{ $key }}]" class="{{ $input }}" :disabled="!shows($el.parentElement)">
                            <option value="">{{ __('Non renseigné') }}</option>
                            @foreach($field['options'] as $value => $label)
                                <option value="{{ $value }}" @selected(($specValues[$key] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif($field['type'] === 'boolean')
                        <select name="specs[{{ $key }}]" class="{{ $input }}" :disabled="!shows($el.parentElement)">
                            <option value="">{{ __('Non renseigné') }}</option>
                            <option value="1" @selected((string) ($specValues[$key] ?? '') === '1')>{{ __('Oui') }}</option>
                            <option value="0" @selected((string) ($specValues[$key] ?? '') === '0')>{{ __('Non') }}</option>
                        </select>
                    @else
                        <div class="relative">
                            <input name="specs[{{ $key }}]" type="number" min="0" step="{{ $field['step'] }}" value="{{ $specValues[$key] ?? '' }}" class="{{ $input }} pr-14" :disabled="!shows($el.closest('[data-spec-types]'))">
                            <span class="pointer-events-none absolute inset-y-0 right-4 grid place-items-center text-xs text-text-main/35">{{ $field['suffix'] }}</span>
                        </div>
                    @endif
                    @error("specs.$key")<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                </label>
            @endforeach
        </div>

        <p class="mt-2 text-sm text-text-main/40" x-show="!hasSpecifications()" x-cloak>{{ __('Aucune caractéristique technique spécifique n’est requise pour cette catégorie.') }}</p>
    </section>

    <button class="w-full rounded-xl bg-accent px-5 py-3.5 text-sm font-semibold text-slate-950 hover:bg-sky-300">
        {{ $item ? __('Enregistrer les modifications') : __('Ajouter l’équipement') }}
    </button>
</div>
