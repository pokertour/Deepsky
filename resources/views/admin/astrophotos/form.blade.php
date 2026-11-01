@php
    $input = 'w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text-bold outline-none transition placeholder:text-text-main/30 focus:border-accent focus:ring-4 focus:ring-accent/10';
    $label = 'grid gap-2 text-sm font-medium text-text-bold';
    $tags = ['galaxy' => __('Galaxie'), 'nebula' => __('Nébuleuse'), 'cluster' => __('Amas'), 'supernova' => __('Supernova'), 'planets' => __('Planètes'), 'widefield' => __('Grand champ')];
    $selectedTags = old('tags', $photo?->tags ?? []);
@endphp
<div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,.75fr)]">
    <div class="grid gap-6">
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <label class="{{ $label }}">{{ __('Publication') }}
                <select name="is_published" class="{{ $input }}">
                    <option value="0" @selected(! old('is_published', $photo?->is_published ?? false))>{{ __('Brouillon — visible uniquement par l’administration') }}</option>
                    <option value="1" @selected(old('is_published', $photo?->is_published ?? false)) @disabled(! $photo || (! $photo->is_published && $photo->conversion_status !== App\Enums\ConversionStatus::Ready))>{{ __('Publié dans la galerie') }}</option>
                </select>
                @error('is_published')<span class="text-xs font-normal text-red-300">{{ $message }}</span>@enderror
            </label>
            <p class="mt-3 text-xs text-text-main/50">{{ __('Enregistre d’abord le brouillon. Lorsque les conversions sont prêtes, vérifie l’aperçu puis publie. La newsletter part à la première publication.') }}</p>
            @unless($photo?->is_published)
                <label class="{{ $label }} mt-4">{{ __('Programmer la publication (facultatif)') }}
                    <input name="publish_at" type="datetime-local" min="{{ now(config('admin.timezone'))->format('Y-m-d\TH:i') }}"
                        value="{{ old('publish_at', $photo?->publish_at?->timezone(config('admin.timezone'))->format('Y-m-d\TH:i')) }}" class="{{ $input }}">
                    <span class="text-[11px] font-normal text-text-main/40">{{ __('Heure de :value. La photo sera publiée automatiquement à cette heure (et la newsletter envoyée), dès que les conversions seront prêtes. Laisse vide pour publier à la main.', ['value' => str(config('admin.timezone'))->afterLast('/')->replace('_', ' ')]) }}</span>
                    @error('publish_at')<span class="text-xs font-normal text-red-300">{{ $message }}</span>@enderror
                </label>
            @endunless
            @if($photo)
                <div data-processing-status data-status-url="{{ route('admin.astrophotos.processing-status', $photo) }}" class="mt-3 flex flex-wrap gap-3 text-xs text-text-main/60"><span data-conversion-label>{{ $photo->conversion_status_label }}</span><span data-astrometry-label>{{ __('Astrométrie : :value', ['value' => $photo->astrometry_status?->getLabel() ?? __('Non résolue')]) }}</span>@if($photo->celestialObject)<span>{{ __('Distance : :value', ['value' => $photo->celestialObject->distance_ly !== null ? number_format($photo->celestialObject->distance_ly, 0, ',', ' ').' al' : 'inconnue']) }}</span>@endif @if($photo->annotations)<span>{{ __('Champ : :value/:value2 distances', ['value' => collect($photo->annotations)->whereNotNull('distance_ly')->count(), 'value2' => count($photo->annotations)]) }}</span>@endif</div>
            @endif
        </section>
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <div class="mb-6"><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('01 · Capture') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Identité céleste') }}</h2></div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div
                    class="relative grid gap-2 text-sm font-medium text-text-bold sm:col-span-2"
                    x-data="celestialObjectLookup"
                    data-search-url="{{ route('admin.celestial-objects.search') }}"
                    data-initial-id="{{ old('celestial_object_id', $photo?->celestial_object_id) }}"
                    data-initial-label="{{ $photo?->celestialObject?->displayName() }}"
                    @click.outside="close"
                >
                    <span>{{ __('Objet du catalogue') }}</span>
                    <input type="hidden" name="celestial_object_id" x-model="selectedId">
                    <div class="flex gap-2">
                        <input x-model="query" @input.debounce.250ms="search" @focus="search" class="{{ $input }} min-w-0 flex-1" placeholder="{{ __('Rechercher M 31, NGC 7000…') }}" autocomplete="off">
                        <button type="button" x-show="selectedId" @click="clear" class="rounded-xl border border-border px-4 text-xs text-text-main/55 hover:text-red-300">{{ __('Effacer') }}</button>
                    </div>
                    <p class="text-[11px] font-normal normal-case tracking-normal text-text-main/40">{{ __('Remplit automatiquement l’identifiant, le titre (s’il est vide) et la catégorie.') }}</p>
                    <div x-show="open" x-cloak class="absolute inset-x-0 top-full z-20 mt-2 max-h-64 overflow-y-auto rounded-xl border border-border bg-card-bg p-2 shadow-2xl">
                        <template x-for="result in results" :key="result.id">
                            <button type="button" @click="choose(result)" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-text-bold hover:bg-accent/10 hover:text-accent" x-text="result.label"></button>
                        </template>
                        <p x-show="!loading && results.length === 0" class="px-3 py-4 text-xs font-normal text-text-main/40">{{ __('Aucun objet trouvé.') }}</p>
                        <p x-show="loading" class="px-3 py-4 text-xs font-normal text-text-main/40">{{ __('Recherche…') }}</p>
                    </div>
                    @error('celestial_object_id')<span class="text-xs font-normal text-red-300">{{ $message }}</span>@enderror
                </div>
                <label class="{{ $label }}">{{ __('Titre') }}<input name="title" value="{{ old('title', $photo?->title) }}" required class="{{ $input }}" placeholder="{{ __('La galaxie de Bode') }}">@error('title')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
                <label class="{{ $label }}">{{ __('Identifiant affiché') }}
                    <input name="target" value="{{ old('target', $photo?->target) }}" class="{{ $input }}" placeholder="{{ __('M 81, NGC 7000…') }}">
                    <span class="text-[11px] font-normal normal-case tracking-normal text-text-main/40">{{ __('Optionnel hors catalogue (grand champ, mosaïque…).') }}</span>
                    @error('target')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                </label>
                <label class="{{ $label }}">{{ __('Date de capture') }}<input name="captured_at" type="date" value="{{ old('captured_at', $photo?->captured_at?->format('Y-m-d')) }}" required class="{{ $input }}"></label>
                <label class="{{ $label }}">{{ __('Lieu d’observation') }}
                    <select name="observing_location_id" class="{{ $input }}"><option value="">{{ __('Aucun lieu renseigné') }}</option>@foreach($observingLocations as $location)<option value="{{ $location->id }}" @selected((string) old('observing_location_id', $photo?->observing_location_id ?? ($photo ? null : $observingLocations->firstWhere('is_default', true)?->id)) === (string) $location->id)>{{ $location->name }}{{ $location->region ? ' · '.$location->region : '' }}{{ $location->is_default ? __(' · Par défaut') : '' }}</option>@endforeach</select>
                    @if($photo?->location_tag && ! $photo?->observing_location_id)<span class="text-[11px] font-normal text-amber-200/70">{{ __('Ancien lieu texte conservé en privé : :location_tag', ['location_tag' => $photo->location_tag]) }}</span>@endif
                    @error('observing_location_id')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                </label>
                <label class="{{ $label }} sm:col-span-2">{{ __('Session NightScope') }}
                    <select name="imaging_session_id" class="{{ $input }}"><option value="">{{ __('Aucune session liée') }}</option>@foreach($imagingSessions as $session)<option value="{{ $session->id }}" @selected((string) old('imaging_session_id', $photo?->imaging_session_id) === (string) $session->id)>{{ $session->started_at->format('d/m/Y H:i') }} · {{ $session->observingLocation?->name ?? __('Lieu supprimé') }} · {{ $session->equipmentSetup?->name ?? __('Rig supprimé') }}{{ $session->ended_at ? '' : __(' · En cours') }}</option>@endforeach</select>
                    <span class="text-[11px] font-normal normal-case tracking-normal text-text-main/40">{{ __('Optionnel : rattache la publication à la nuit planifiée correspondante.') }}</span>
                    @error('imaging_session_id')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                </label>
            </div>
            <label class="{{ $label }} mt-5">{{ __('Description') }}<textarea name="description" rows="5" class="{{ $input }}" placeholder="{{ __('Notes de session, conditions de capture…') }}">{{ old('description', $photo?->description) }}</textarea></label>
            @include('admin.astrophotos.object-description')
            @include('admin.astrophotos.english-version')
            <fieldset class="mt-5"><legend class="mb-3 text-sm font-medium text-text-bold">{{ __('Catégories') }}</legend><div class="flex flex-wrap gap-2">@foreach($tags as $value => $tagLabel)<label class="cursor-pointer"><input class="peer sr-only" type="checkbox" name="tags[]" value="{{ $value }}" @checked(in_array($value, $selectedTags, true))><span class="block rounded-lg border border-border bg-bg px-3 py-2 text-xs text-text-main/55 transition peer-checked:border-accent/50 peer-checked:bg-accent/10 peer-checked:text-accent">{{ $tagLabel }}</span></label>@endforeach</div></fieldset>
        </section>

        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <div class="mb-6"><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('02 · Optique') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Rig utilisé') }}</h2><p class="mt-2 text-sm text-text-main/50">{{ __('Choisis la configuration complète employée pour cette capture.') }}</p></div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <label class="{{ $label }} sm:col-span-2">{{ __('Configuration matérielle') }}
                    <select name="equipment_setup_id" class="{{ $input }}">
                        <option value="">{{ __('Aucun rig renseigné') }}</option>
                        @foreach($equipmentSetups as $setup)
                            <option value="{{ $setup->id }}" @selected((string) old('equipment_setup_id', $photo?->equipment_setup_id ?? ($photo ? null : $equipmentSetups->firstWhere('is_default', true)?->id)) === (string) $setup->id)>{{ $setup->name }}{{ $setup->is_default ? __(' · Par défaut') : '' }} — {{ $setup->enabledSetupItems->pluck('name')->join(', ') }}</option>
                        @endforeach
                    </select>
                    @error('equipment_setup_id')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                </label>
                <label class="{{ $label }}">{{ __('Focale mesurée (mm)') }}<input name="focal_length" type="number" min="0" value="{{ old('focal_length', $photo?->focal_length) }}" class="{{ $input }}"><span class="text-[11px] font-normal normal-case tracking-normal text-text-main/40">{{ __('Optionnel : surcharge la focale calculée du rig.') }}</span></label>
                <label class="{{ $label }}">{{ __('Filtres utilisés') }}<input name="filters" value="{{ old('filters', $photo?->filters) }}" class="{{ $input }}" placeholder="{{ __('L-RGB / SHO') }}"></label>
            </div>
        </section>

        @include('admin.astrophotos.acquisition')
    </div>

    <aside class="xl:sticky xl:top-26 xl:self-start"><section class="rounded-2xl border border-border bg-card-bg p-5" x-data="{ preview: null, tiff: false, pick(event) { const f = event.target.files[0]; this.tiff = !!f && (f.type === 'image/tiff' || /\.tiff?$/i.test(f.name)); this.preview = f && !this.tiff ? URL.createObjectURL(f) : null } }"><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Signal principal') }}</p><div class="mt-4 aspect-square overflow-hidden rounded-xl border border-dashed border-border bg-bg"><template x-if="preview && !tiff"><img :src="preview" class="size-full object-cover"></template><template x-if="tiff"><div class="grid size-full place-items-center p-6 text-center font-[var(--font-mono)] text-xs text-text-main/50">{{ __('TIFF · aperçu navigateur indisponible') }}<br>{{ __('Conversion après l’envoi') }}</div></template>@if($photo?->thumbnailUrl())<img x-show="!preview && !tiff" src="{{ $photo->thumbnailUrl() }}" alt="" class="size-full object-cover">@elseif($photo?->hasMedia('astros'))<div x-show="!preview && !tiff" class="grid size-full place-items-center p-6 text-center font-[var(--font-mono)] text-xs text-text-main/50">{{ __('Miniature en cours de conversion') }}</div>@else<div x-show="!preview && !tiff" class="grid size-full place-items-center p-6 text-center font-[var(--font-mono)] text-xs text-text-main/30">{{ __('GLISSEZ LE SIGNAL ICI') }}</div>@endif</div><label class="mt-4 block cursor-pointer rounded-xl border border-border px-4 py-3 text-center text-sm font-medium text-text-bold transition hover:border-accent/40 hover:text-accent"><input class="sr-only" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/tiff,.tif,.tiff" {{ $photo ? '' : 'required' }} @change="pick($event)">{{ $photo ? __('Remplacer l’image') : __('Sélectionner l’image') }}</label>@error('image')<p class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror<p class="mt-3 text-center text-[10px] leading-relaxed text-text-main/35">{{ __('JPG, PNG, WEBP ou TIFF · 300 Mo max') }}<br>{{ __('Les conversions sont générées automatiquement.') }}</p></section>
        <section class="mt-4 rounded-2xl border border-border bg-card-bg p-5" x-data="{ preview: null, tiff: false, remove: false, pick(event) { const f = event.target.files[0]; this.remove = false; this.tiff = !!f && (f.type === 'image/tiff' || /\.tiff?$/i.test(f.name)); this.preview = f && !this.tiff ? URL.createObjectURL(f) : null } }">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Version starless') }}</p>
            <p class="mt-1 text-[11px] text-text-main/40">{{ __('Optionnel · même cadrage que le signal principal.') }}</p>
            <div class="mt-4 aspect-square overflow-hidden rounded-xl border border-dashed border-border bg-bg" :class="remove && 'opacity-30'">
                <template x-if="preview && !tiff"><img :src="preview" class="size-full object-cover"></template>
                <template x-if="tiff"><div class="grid size-full place-items-center p-6 text-center font-[var(--font-mono)] text-xs text-text-main/50">{{ __('TIFF · aperçu navigateur indisponible') }}<br>{{ __('Conversion après l’envoi') }}</div></template>
                @if($photo?->thumbnailUrl('starless'))
                    <img x-show="!preview && !tiff" src="{{ $photo->thumbnailUrl('starless') }}" alt="" class="size-full object-cover">
                @elseif($photo?->hasMedia('starless'))
                    <div x-show="!preview && !tiff" class="grid size-full place-items-center p-6 text-center font-[var(--font-mono)] text-xs text-text-main/50">{{ __('Miniature en cours de conversion') }}</div>
                @else
                    <div x-show="!preview && !tiff" class="grid size-full place-items-center p-6 text-center font-[var(--font-mono)] text-xs text-text-main/30">{{ __('AUCUNE VERSION STARLESS') }}</div>
                @endif
            </div>
            <label class="mt-4 block cursor-pointer rounded-xl border border-border px-4 py-3 text-center text-sm font-medium text-text-bold transition hover:border-accent/40 hover:text-accent">
                <input class="sr-only" name="starless_image" type="file" accept="image/jpeg,image/png,image/webp,image/tiff,.tif,.tiff" @change="pick($event)">
                {{ $photo?->hasMedia('starless') ? __('Remplacer la version starless') : __('Ajouter une version starless') }}
            </label>
            @if($photo?->hasMedia('starless'))
                <label class="mt-3 flex items-center gap-2 text-xs text-text-main/55" x-show="!preview && !tiff">
                    <input type="checkbox" name="remove_starless" value="1" x-model="remove" class="accent-red-400">
                    {{ __('Supprimer la version starless') }}
                </label>
            @endif
            @error('starless_image')<p class="mt-2 text-xs text-red-300">{{ $message }}</p>@enderror
        </section>
        <div data-upload-status hidden class="mt-4 rounded-xl border border-border bg-bg p-4 text-xs text-text-main/70" role="status" aria-live="polite"><span data-upload-message>{{ __('Transfert en cours…') }}</span><progress data-upload-progress max="100" value="0" class="mt-2 h-2 w-full accent-sky-400" aria-label="{{ __('Progression du transfert') }}"></progress></div>
        <button class="mt-4 w-full rounded-xl bg-accent px-5 py-3.5 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ $photo ? __('Enregistrer les modifications') : __('Enregistrer le brouillon') }}</button></aside>
</div>
