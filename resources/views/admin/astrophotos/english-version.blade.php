@php($hasEnglish = filled(old('title_en', $photo?->title_en)) || filled(old('description_en', $photo?->description_en)) || filled(old('object_description_en', $photo?->object_description_en)) || $errors->hasAny(['title_en', 'description_en', 'object_description_en']))
<details class="group mt-5 rounded-xl border border-border bg-bg/40 p-4" x-data="astrophotoTranslation" data-translate-url="{{ route('admin.astrophotos.translate') }}" @if($hasEnglish) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-text-bold">
        <span><span class="mr-2 rounded-md border border-border px-1.5 py-0.5 font-[var(--font-mono)] text-[10px] text-accent">EN</span>{{ __('Version anglaise') }}</span>
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4 text-text-main/50 transition-transform group-open:rotate-180" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg>
    </summary>
    <p class="mt-3 text-[11px] text-text-main/50">{{ __('Affichée aux visiteurs qui consultent le site en anglais. Un champ laissé vide reprend le texte français.') }}</p>
    <div class="mt-4 grid gap-4">
        <label class="{{ $label }}">{{ __('Titre (anglais)') }}<input name="title_en" value="{{ old('title_en', $photo?->title_en) }}" class="{{ $input }}" placeholder="Bode’s Galaxy">@error('title_en')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
        <label class="{{ $label }}">{{ __('Description (anglais)') }}<textarea name="description_en" rows="4" class="{{ $input }}">{{ old('description_en', $photo?->description_en) }}</textarea>@error('description_en')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
        <label class="{{ $label }}">{{ __('Présentation de l’objet (anglais)') }}<textarea name="object_description_en" rows="5" maxlength="10000" class="{{ $input }}">{{ old('object_description_en', $photo?->object_description_en) }}</textarea>@error('object_description_en')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <button type="button" @click="translate" :disabled="loading" class="rounded-lg border border-accent/40 px-3 py-2 text-xs font-medium text-accent disabled:cursor-wait disabled:opacity-40" x-text="loading ? @js(__('Traduction en cours…')) : @js(__('Traduire depuis le français avec Gemini'))">{{ __('Traduire depuis le français avec Gemini') }}</button>
        <span x-show="done" x-cloak class="text-xs text-green-300">{{ __('Traduction insérée : relis-la puis enregistre.') }}</span>
    </div>
    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-3 text-xs text-red-300"></p>
</details>
