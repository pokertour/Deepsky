@props(['sessions'])
<section class="mt-8 rounded-xl border border-border bg-card-bg p-5 sm:p-6">
    <h2 class="text-lg font-bold text-text-bold">{{ __('Sessions d’acquisition') }}</h2>
    <p class="mt-2 text-sm text-text-main/60">{{ __(':value session(s) · :value2 d’intégration (lights)', ['value' => count($sessions), 'value2' => \App\Support\AcquisitionData::formatDuration(\App\Support\AcquisitionData::integrationSeconds($sessions))]) }}</p>
    <div class="mt-4 flex flex-wrap gap-3">
        @foreach(\App\Support\AcquisitionData::filterSummary($sessions) as $summary)
            <span class="rounded-lg border border-accent/20 bg-accent/5 px-3 py-2 text-xs text-text-bold">{{ __(':value · :value2 lights · :value3', ['value' => $summary['filter'], 'value2' => $summary['count'], 'value3' => $summary['duration']]) }}</span>
        @endforeach
    </div>
    <div class="mt-5 grid gap-4">
        @foreach($sessions as $session)
            <div class="rounded-xl border border-border p-4">
                <h3 class="text-sm font-semibold text-text-bold">{{ __('Session :iteration · :value · :value2', ['iteration' => $loop->iteration, 'value' => filled($session['date'] ?? null) ? \Illuminate\Support\Carbon::parse($session['date'])->format('d/m/Y') : 'Date non renseignée', 'value2' => \App\Support\AcquisitionData::CAMERA_MODES[$session['camera_mode']]]) }}</h3>
                <p class="mt-1 text-xs text-accent">{{ __(':value d’intégration', ['value' => \App\Support\AcquisitionData::formatDuration(\App\Support\AcquisitionData::integrationSeconds([$session]))]) }}</p>
                <ul class="mt-3 grid gap-2 text-sm text-text-main/70">
                    @foreach($session['frames'] as $frame)
                        <li class="flex flex-wrap justify-between gap-2">
                            <span>{{ \App\Support\AcquisitionData::FRAME_TYPES[$frame['type']] }} · {{ ($frame['filter'] ?? null) ?: __('Sans filtre') }}</span>
                            <span>
                                {{ __(':value images', ['value' => $frame['count']]) }}
                                @if(($frame['seconds'] ?? null) !== null && $frame['seconds'] !== '')
                                    {{ __('× :value s', ['value' => $frame['seconds']]) }}
                                @endif
                                @if($frame['type'] === 'light')
                                    · {{ \App\Support\AcquisitionData::formatDuration($frame['count'] * $frame['seconds']) }}
                                @else
                                    {{ __('· calibration') }}
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</section>
