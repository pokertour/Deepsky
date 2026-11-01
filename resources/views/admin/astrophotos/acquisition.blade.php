<section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6" x-data="acquisitionSessions"
    data-sessions="{{ json_encode(old('acquisition_sessions', $photo?->acquisition_sessions) ?: []) }}"
    data-initial-exposure="{{ old('exposure', $photo?->exposure) }}">
    <div class="mb-6">
        <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('03 · Acquisition') }}</p>
        <h2 class="mt-1 font-semibold text-text-bold">{{ __('Sessions et images de calibration') }}</h2>
        <p class="mt-2 text-sm text-text-main/50">{{ __('Une session par nuit ou configuration. Ajoute un lot pour chaque filtre et durée de pose. Seuls les lights retenus dans l’empilement comptent dans l’intégration.') }}</p>
    </div>
    <label class="mb-5 flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed border-accent/40 bg-accent/[.04] px-4 py-5 text-center transition hover:border-accent hover:bg-accent/10"
        x-data="{ dragging: false }" :class="dragging && 'border-accent bg-accent/10'"
        @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="dragging = false; importFiles($event.dataTransfer.files)">
        <span class="text-sm font-semibold text-text-bold" x-text="importing ? @js(__('Lecture des en-têtes…')) : @js(__('Importer depuis les fichiers FITS / XISF'))">{{ __('Importer depuis les fichiers FITS / XISF') }}</span>
        <span class="max-w-xl text-xs text-text-main/55">{{ __('Glisse ou choisis tes lights (et flats, darks, bias). Seuls les en-têtes sont lus, dans le navigateur : rien n’est envoyé. Sessions, poses, filtres, gain, offset, température, focale et cible sont remplis automatiquement (NINA, ASIAIR, SharpCap, Ekos, PixInsight).') }}</span>
        <input type="file" multiple accept=".fit,.fits,.fts,.xisf" class="sr-only" @change="importFiles($event.target.files); $event.target.value = ''">
    </label>
    <p x-cloak x-show="importReport" class="mb-4 rounded-lg border border-green-400/25 bg-green-400/10 px-3 py-2 text-xs text-green-300" role="status" x-text="importReport"></p>
    <template x-for="error in importErrors" :key="error"><p class="mb-2 text-xs text-amber-200" role="alert" x-text="error"></p></template>
    <input type="hidden" name="acquisition_sessions" value="">
    @foreach($errors->getMessages() as $field => $messages)
        @if(str_starts_with($field, 'acquisition_sessions'))
            @foreach($messages as $message)<p role="alert" class="mb-2 text-xs text-red-300">{{ $message }}</p>@endforeach
        @endif
    @endforeach
    <div class="grid gap-4">
        <template x-for="(session, sessionIndex) in sessions" :key="session.key">
            <fieldset class="min-w-0 rounded-xl border border-border bg-bg/50 p-4">
                <legend class="px-2 text-sm font-semibold text-text-bold" x-text="@js(__('Session')) + ' ' + (sessionIndex + 1)"></legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="{{ $label }}">{{ __('Date (facultative)') }}<input type="date" x-model="session.date" :name="`acquisition_sessions[${sessionIndex}][date]`" class="{{ $input }}"></label>
                    <label class="{{ $label }}">{{ __('Capteur') }}<select x-model="session.camera_mode" :name="`acquisition_sessions[${sessionIndex}][camera_mode]`" class="{{ $input }}">@foreach(\App\Support\AcquisitionData::CAMERA_MODES as $mode => $name)<option value="{{ $mode }}">{{ $name }}</option>@endforeach</select></label>
                </div>
                <div class="mt-4 grid gap-3">
                    <template x-for="(frame, frameIndex) in session.frames" :key="frame.key">
                        <div class="grid grid-cols-1 gap-3 rounded-xl border border-border p-3 sm:grid-cols-2">
                            <label class="{{ $label }}">{{ __('Type d’image') }}<select x-model="frame.type" :name="`acquisition_sessions[${sessionIndex}][frames][${frameIndex}][type]`" class="{{ $input }}">@foreach(\App\Support\AcquisitionData::FRAME_TYPES as $type => $name)<option value="{{ $type }}">{{ $name }}</option>@endforeach</select></label>
                            <label class="{{ $label }}">{{ __('Filtre (facultatif)') }}<input x-model="frame.filter" :name="`acquisition_sessions[${sessionIndex}][frames][${frameIndex}][filter]`" maxlength="100" list="acquisition-filters" placeholder="{{ __('Sans filtre, L, R, G, B, Ha…') }}" class="{{ $input }}"></label>
                            <label class="{{ $label }}">{{ __('Nombre d’images') }}<input type="number" min="1" max="100000" step="1" required x-model="frame.count" :name="`acquisition_sessions[${sessionIndex}][frames][${frameIndex}][count]`" class="{{ $input }}"></label>
                            <label class="{{ $label }}">{{ __('Durée par image (s)') }}<input type="number" :min="frame.type === 'light' ? 0.001 : 0" max="86400" step="0.001" :required="frame.type === 'light'" x-model="frame.seconds" :name="`acquisition_sessions[${sessionIndex}][frames][${frameIndex}][seconds]`" class="{{ $input }}" :placeholder="frame.type === 'light' ? @js(__('Ex. 120')) : @js(__('Facultative'))"></label>
                            <div class="flex flex-wrap items-center justify-between gap-2 sm:col-span-2">
                                <span class="text-xs text-text-main/50" x-text="frame.type === 'light' ? formatDuration((Number(frame.count) || 0) * (Number(frame.seconds) || 0)) + ' ' + @js(__('d’intégration')) : @js(__('Calibration · hors intégration'))"></span>
                                <button type="button" @click="session.frames.splice(frameIndex, 1)" :disabled="session.frames.length === 1" class="text-xs text-red-300 disabled:opacity-30">{{ __('Supprimer le lot') }}</button>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-4 text-xs">
                    <button type="button" @click="addFrame(session)" :disabled="session.frames.length >= 100" class="text-accent disabled:opacity-30">{{ __('+ Ajouter un lot') }}</button>
                    <span class="text-text-bold" x-text="formatDuration(sessionTotal(session))"></span>
                    <button type="button" @click="duplicateSession(sessionIndex)" :disabled="sessions.length >= 100" class="text-text-main/60 disabled:opacity-30">{{ __('Dupliquer la session') }}</button>
                    <button type="button" @click="removeSession(sessionIndex)" class="text-red-300">{{ __('Supprimer la session') }}</button>
                </div>
            </fieldset>
        </template>
    </div>
    <datalist id="acquisition-filters">@foreach(['L', 'R', 'G', 'B', 'Ha', 'OIII', 'SII', 'Hβ', 'LRGB', 'UV/IR-cut', 'L-Pro', 'L-eXtreme', 'L-Ultimate'] as $filter)<option value="{{ $filter }}">@endforeach</datalist>
    <button type="button" @click="addSession" :disabled="sessions.length >= 100" class="mt-4 rounded-xl border border-accent/40 px-4 py-3 text-sm text-accent disabled:opacity-30">{{ __('+ Ajouter une session') }}</button>
    <div x-show="sessions.length" x-cloak class="mt-5 rounded-xl border border-accent/20 bg-accent/5 p-4" aria-live="polite">
        <p class="text-sm font-semibold text-text-bold">{{ __('Intégration totale :') }} <span x-text="formatDuration(total)"></span></p>
        <div class="mt-3 grid gap-2">
            <template x-for="item in summary" :key="item.filter"><p class="flex flex-wrap justify-between gap-2 text-xs text-text-main/60"><span x-text="item.filter"></span><span x-text="item.count + ' ' + @js(__('lights ·')) + ' ' + formatDuration(item.seconds)"></span></p></template>
        </div>
    </div>
    <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
        <label class="{{ $label }}">{{ __('Pose totale (s)') }}<input name="exposure" value="{{ old('exposure', $photo?->exposure) }}" :value="sessions.length ? total : manualExposure" @input="manualExposure = $event.target.value" :readonly="sessions.length > 0" class="{{ $input }}"><span class="text-[11px] font-normal text-text-main/40" x-text="sessions.length ? @js(__('Calculée à partir des lights de toutes les sessions.')) : @js(__('Saisie manuelle pour les acquisitions sans détail.'))"></span>@error('exposure')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
        <label class="{{ $label }}">{{ __('Gain') }}<input name="gain" type="number" min="0" value="{{ old('gain', $photo?->gain) }}" class="{{ $input }}"></label>
        <label class="{{ $label }}">{{ __('Offset') }}<input name="offset" type="number" min="0" value="{{ old('offset', $photo?->offset) }}" class="{{ $input }}"></label>
        <label class="{{ $label }}">{{ __('Température') }}<input name="sensor_temp" value="{{ old('sensor_temp', $photo?->sensor_temp) }}" class="{{ $input }}" placeholder="{{ __('-10°C') }}"></label>
    </div>
</section>
