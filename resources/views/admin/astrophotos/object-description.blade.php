<div class="mt-5 rounded-xl border border-border bg-bg/40 p-4" x-data="objectDescription"
    data-initial-description="{{ old('object_description', $photo?->object_description) }}"
    data-generate-url="{{ route('admin.astrophotos.object-description') }}">
    <label class="{{ $label }}">{{ __('Présentation de l’objet') }}
        <textarea name="object_description" rows="7" maxlength="10000" x-model="text" class="{{ $input }}" placeholder="{{ __('Un texte accessible sur la nébuleuse, la galaxie ou l’amas…') }}">{{ old('object_description', $photo?->object_description) }}</textarea>
        <span class="text-[11px] font-normal text-text-main/45">{{ __('Affichée sur la fiche publique, séparément de ton récit de capture. Tu peux aussi la rédiger toi-même.') }}</span>
        @error('object_description')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
    </label>
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <button type="button" @click="generate" :disabled="loading" class="rounded-lg border border-accent/40 px-3 py-2 text-xs font-medium text-accent disabled:cursor-wait disabled:opacity-40" x-text="loading ? @js(__('Génération en cours…')) : @js(__('Proposer un texte avec Gemini'))">{{ __('Proposer un texte avec Gemini') }}</button>
        <a wire:navigate href="{{ route('admin.settings.edit') }}" class="text-xs text-text-main/50 hover:text-accent">{{ __('Configurer la clé Gemini') }}</a>
    </div>
    <p class="mt-2 text-[11px] text-text-main/45">{{ __('La proposition utilise la fiche du catalogue sélectionné. Relis les faits avant de l’insérer, puis enregistre la photo.') }}</p>
    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-3 text-xs text-red-300"></p>
    <div x-show="suggestion" x-cloak class="mt-4 rounded-lg border border-accent/20 bg-accent/5 p-4" aria-live="polite">
        <h3 class="text-sm font-semibold text-text-bold">{{ __('Proposition à relire') }}</h3>
        <p x-text="suggestion" class="mt-3 whitespace-pre-line text-sm leading-relaxed text-text-main"></p>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" @click="accept" class="rounded-lg bg-accent px-3 py-2 text-xs font-semibold text-slate-950">{{ __('Utiliser ce texte') }}</button>
            <button type="button" @click="suggestion = ''" class="rounded-lg border border-border px-3 py-2 text-xs text-text-main/60">{{ __('Écarter') }}</button>
        </div>
    </div>
</div>
