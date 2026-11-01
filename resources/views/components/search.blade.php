@props(['constellations', 'years', 'rigs', 'equipment'])
<form action="{{ route('welcome') }}" method="GET" class="grid w-full grid-cols-1 gap-4 rounded-xl border border-border bg-card-bg p-5 sm:grid-cols-2 lg:grid-cols-4">
    <label class="flex min-w-0 flex-col gap-2 sm:col-span-2">
        <span class="text-xs font-mono uppercase tracking-widest text-accent">{{ __('Recherche') }}</span>
        <input id="gallery-search" type="search" name="search" value="{{ request('search') }}" maxlength="200" placeholder="{{ __('Nom, M31, constellation, matériel…') }}" class="w-full rounded border border-border bg-bg px-3 py-2 text-sm text-text-main focus:border-accent focus:ring-accent">
    </label>
    <div class="col-span-full grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <label class="flex min-w-0 flex-col gap-2">
        <span class="text-xs font-mono uppercase tracking-widest text-accent">{{ __('Type d’objet') }}</span>
        <select name="type" class="w-full min-w-0 max-w-full rounded border border-border bg-bg px-3 py-2 text-sm text-text-main">
            <option value="">{{ __('Tous les objets') }}</option>
            @foreach(['galaxy' => __('Galaxies'), 'nebula' => __('Nébuleuses'), 'cluster' => __('Amas')] as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="flex min-w-0 flex-col gap-2">
        <span class="text-xs font-mono uppercase tracking-widest text-accent">{{ __('Constellation') }}</span>
        <select name="constellation" class="w-full min-w-0 max-w-full rounded border border-border bg-bg px-3 py-2 text-sm text-text-main">
            <option value="">{{ __('Toutes') }}</option>
            @foreach($constellations as $constellation)
                <option value="{{ $constellation }}" @selected(request('constellation') === $constellation)>{{ $constellation }}</option>
            @endforeach
        </select>
    </label>
    <label class="flex min-w-0 flex-col gap-2">
        <span class="text-xs font-mono uppercase tracking-widest text-accent">{{ __('Année de capture') }}</span>
        <select name="year" class="w-full min-w-0 max-w-full rounded border border-border bg-bg px-3 py-2 text-sm text-text-main">
            <option value="">{{ __('Toutes les années') }}</option>
            @foreach($years as $year)
                <option value="{{ $year }}" @selected((string) request('year') === (string) $year)>{{ $year }}</option>
            @endforeach
        </select>
    </label>
    <label class="flex min-w-0 flex-col gap-2">
        <span class="text-xs font-mono uppercase tracking-widest text-accent">{{ __('Matériel') }}</span>
        <select name="equipment" class="w-full min-w-0 max-w-full rounded border border-border bg-bg px-3 py-2 text-sm text-text-main">
            <option value="">{{ __('Tout le matériel') }}</option>
            <optgroup label="{{ __('Rigs') }}">
                @foreach($rigs as $rig)
                    <option value="rig:{{ $rig['id'] }}" @selected(request('equipment') === 'rig:'.$rig['id'])>{{ $rig['name'] }}</option>
                @endforeach
            </optgroup>
            <optgroup label="{{ __('Équipements') }}">
                @foreach($equipment as $item)
                    <option value="item:{{ $item['id'] }}" @selected(request('equipment') === 'item:'.$item['id'])>{{ $item['label'] }}</option>
                @endforeach
            </optgroup>
        </select>
    </label>
    <label class="flex min-w-0 flex-col gap-2">
        <span class="text-xs font-mono uppercase tracking-widest text-accent">{{ __('Tri') }}</span>
        <select name="sort" class="w-full min-w-0 max-w-full rounded border border-border bg-bg px-3 py-2 text-sm text-text-main">
            <option value="">{{ __('Plus récentes') }}</option>
            <option value="views" @selected(request('sort') === 'views')>{{ __('Plus vues') }}</option>
            <option value="likes" @selected(request('sort') === 'likes')>{{ __('Plus aimées') }}</option>
        </select>
    </label>
    </div>
    <div class="col-span-full flex items-end gap-3">
        <button type="submit" class="rounded border border-accent px-4 py-2 text-sm text-accent hover:bg-accent hover:text-bg">{{ __('Filtrer') }}</button>
        <a wire:navigate href="{{ route('welcome') }}" class="py-2 text-sm text-text-main hover:text-accent">{{ __('Tout réinitialiser') }}</a>
    </div>
</form>
