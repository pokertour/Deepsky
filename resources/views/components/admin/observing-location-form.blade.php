@props(['observingLocation' => null, 'timezones'])

@php
    $input = 'w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text-bold outline-none transition focus:border-accent focus:ring-4 focus:ring-accent/10';
    $selectedTimezone = old('timezone', $observingLocation?->timezone ?? 'Europe/Paris');
    $horizonMask = old('horizon_mask', $observingLocation?->horizon_mask ? json_encode($observingLocation->horizon_mask, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
@endphp

<div x-data="observingLocationMap" @horizon-pick-reference="startReference()" data-elevation-url="{{ route('admin.observing-locations.elevation') }}" class="mx-auto grid max-w-4xl gap-6">
    <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-8">
        <div class="mb-7">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Site d’observation') }}</p>
            <h2 class="mt-1 text-lg font-semibold text-text-bold">{{ __('Coordonnées du lieu') }}</h2>
            <p class="mt-2 text-sm text-text-main/50">{{ __('Le fuseau horaire sera utilisé pour afficher correctement la nuit astronomique.') }}</p>
        </div>

        <div class="mb-6 grid gap-3">
            <div x-ref="map" role="region" aria-label="{{ __('Carte du lieu d’observation') }}" class="relative isolate z-0 h-64 overflow-hidden rounded-xl border border-border bg-bg sm:h-80"></div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-text-main/55">{{ __('Appuie sur la carte ou déplace le repère pour choisir ton lieu.') }}</p>
                <button type="button" @click="locate()" :disabled="locating" class="min-h-11 rounded-xl border border-accent/40 px-4 py-2 text-xs text-accent disabled:opacity-50" x-text="locating ? @js(__('Localisation…')) : @js(__('Ma position'))">{{ __('Ma position') }}</button>
            </div>
            <button type="button" x-show="referenceMode" x-cloak @click="referenceMode = false; status = ''" class="min-h-11 w-fit text-xs text-accent">{{ __('Annuler la sélection du repère') }}</button>
            <p x-show="mapError" x-cloak x-text="mapError" class="text-xs text-amber-200" role="status"></p>
            <p x-show="status" x-cloak x-text="status" class="text-xs text-text-main/65" role="status"></p>
            <p class="text-[11px] text-text-main/45">{{ __('Carte OpenStreetMap · L’altitude du terrain est estimée par Open-Meteo à partir des coordonnées choisies. Les coordonnées restent privées dans Deepsky.') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <label class="grid gap-2 text-sm font-medium text-text-bold sm:col-span-2">
                {{ __('Nom') }}
                <input name="name" value="{{ old('name', $observingLocation?->name) }}" required class="{{ $input }}" placeholder="{{ __('Maison — Nantes') }}">
                @error('name')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2 text-sm font-medium text-text-bold sm:col-span-2">
                {{ __('Région publique') }}
                <input name="region" value="{{ old('region', $observingLocation?->region) }}" class="{{ $input }}" placeholder="{{ __('Loire-Atlantique, Bretagne, Alpes…') }}">
                <span class="text-[11px] font-normal text-text-main/45">{{ __('Seule cette information générale pourra être affichée dans la galerie publique. Le nom et les coordonnées du lieu restent privés.') }}</span>
                @error('region')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2 text-sm font-medium text-text-bold">
                {{ __('Latitude') }}
                <input @change="syncFromFields()" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $observingLocation?->latitude) }}" required class="{{ $input }}" placeholder="47.218371">
                @error('latitude')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2 text-sm font-medium text-text-bold">
                {{ __('Longitude') }}
                <input @change="syncFromFields()" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $observingLocation?->longitude) }}" required class="{{ $input }}" placeholder="-1.553621">
                @error('longitude')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2 text-sm font-medium text-text-bold">
                {{ __('Altitude') }}
                <div class="relative">
                    <input name="altitude" type="number" min="-500" max="10000" step="0.1" value="{{ old('altitude', $observingLocation?->altitude) }}" class="{{ $input }} pr-12">
                    <span class="pointer-events-none absolute inset-y-0 right-4 grid place-items-center text-xs text-text-main/35">{{ __('m') }}</span>
                </div>
                @error('altitude')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                <button type="button" @click="fetchElevation()" class="w-fit min-h-11 text-left text-xs font-normal text-accent">{{ __('Rechercher l’altitude du terrain') }}</button>
            </label>
            <label class="grid gap-2 text-sm font-medium text-text-bold">
                {{ __('Classe de Bortle') }}
                <select name="bortle_class" class="{{ $input }}">
                    <option value="" @selected(old('bortle_class', $observingLocation?->bortle_class) === null)>{{ __('Non renseignée') }}</option>
                    @foreach (App\Enums\BortleClass::cases() as $bortle)
                        <option value="{{ $bortle->value }}" @selected((int) old('bortle_class', $observingLocation?->bortle_class) === $bortle->value)>{{ $bortle->label() }}</option>
                    @endforeach
                </select>
                <span class="text-[11px] font-normal text-text-main/45">{{ __('Sert aux reco de filtres (ciel urbain → dual-band type L-Ultimate pour les nébuleuses).') }}</span>
                @error('bortle_class')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2 text-sm font-medium text-text-bold">
                {{ __('Fuseau horaire') }}
                <select name="timezone" required class="{{ $input }}">
                    @foreach($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected($selectedTimezone === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
                @error('timezone')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="flex items-center gap-3 rounded-xl border border-border bg-bg/50 p-4 text-sm text-text-bold sm:col-span-2">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $observingLocation?->is_default ?? false)) class="size-5 rounded border-border bg-bg text-accent focus:ring-accent/30">
                {{ __('Utiliser ce lieu par défaut dans NightScope') }}
            </label>
        </div>
    </section>

    <x-admin.horizon-editor :mask="$horizonMask" />

    <button class="w-full rounded-xl bg-accent px-5 py-3.5 text-sm font-semibold text-slate-950 hover:bg-sky-300">{{ $observingLocation ? __('Enregistrer le lieu') : __('Ajouter le lieu') }}</button>
</div>
