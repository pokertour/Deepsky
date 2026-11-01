@props(['location', 'visibility'])
@php
    $samples = array_map(fn ($point) => [
        'time' => $point->at->format('d/m H:i'),
        'azimuth' => $point->azimuthDeg,
        'altitude' => $point->altitudeDeg,
        'horizon' => $point->horizonAltitudeDeg,
        'visible' => $point->visible,
    ], $visibility->timeline);
@endphp
<article x-data="horizonTrajectory" data-mask="{{ json_encode((object) ($location->horizon_mask ?? [])) }}" data-samples="{{ json_encode($samples) }}" class="min-w-0 overflow-hidden rounded-2xl border border-border bg-card-bg p-4 sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="font-[var(--font-mono)] text-xs text-accent">{{ __('Trajectoire sur l’horizon') }}</p>
            <h2 class="mt-1 text-base font-semibold text-text-bold sm:text-lg">{{ __('Passage de la cible devant les obstacles') }}</h2>
            <p class="mt-1 text-xs text-text-main/50">{{ __(':name · directions nord, est, sud et ouest', ['name' => $location->name]) }}</p>
        </div>
        <a wire:navigate href="{{ route('admin.observing-locations.edit', $location) }}" class="inline-flex min-h-11 items-center text-xs text-accent">{{ __('Modifier l’horizon →') }}</a>
    </div>
    <p x-show="warning" x-cloak x-text="warning" role="alert" class="mt-3 text-xs text-amber-200"></p>
    @if($samples === [])
        <p class="mt-5 text-sm text-text-main/60">{{ __('Aucune trajectoire disponible : ce lieu n’a pas de nuit astronomique pour cette date.') }}</p>
    @else
        <div class="relative mt-5 overflow-hidden rounded-xl border border-border bg-bg">
            <svg viewBox="0 0 800 250" preserveAspectRatio="none" class="h-56 w-full sm:h-64" role="img" aria-label="{{ __('Trajectoire de la cible par direction et hauteur, superposée au masque d’horizon') }}">
                <defs><clipPath id="horizon-trajectory-clip"><rect x="40" y="24" width="720" height="180" /></clipPath></defs>
                @foreach ([0, 30, 60, 90] as $height)
                    <line x1="40" y1="{{ 204 - $height * 2 }}" x2="760" y2="{{ 204 - $height * 2 }}" stroke="currentColor" class="text-border" stroke-dasharray="4 6" />
                    <text x="32" y="{{ 208 - $height * 2 }}" text-anchor="end" fill="currentColor" class="hidden text-[12px] text-text-main/60 sm:block">{{ $height }}°</text>
                @endforeach
                @foreach ([0 => 'N · 0°', 90 => 'E · 90°', 180 => 'S · 180°', 270 => 'O · 270°', 360 => 'N · 360°'] as $azimuth => $direction)
                    <text x="{{ 40 + $azimuth * 2 }}" y="228" text-anchor="middle" fill="currentColor" class="hidden text-[12px] text-text-main/70 sm:block">{{ $direction }}</text>
                @endforeach
                <g clip-path="url(#horizon-trajectory-clip)">
                    <polygon :points="area" fill="#f0c674" opacity="0.16" />
                    <polyline :points="line" fill="none" stroke="#f0c674" stroke-width="2" />
                    <line x1="40" y1="164" x2="760" y2="164" stroke="#58a6ff" stroke-dasharray="4 8" opacity="0.4" />
                    <g x-ref="segments" x-effect="renderSegments()"></g>
                    <circle :cx="x(current.azimuth)" :cy="y(current.altitude)" r="6" fill="#ffffff" stroke="#58a6ff" stroke-width="2" />
                </g>
            </svg>
            <x-admin.horizon-mobile-labels />
        </div>
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-[11px] text-text-main/65">
            <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-[#3fb950]"></span>{{ __('Dégagée ≥ 20°') }}</span>
            <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-[#58a6ff]"></span>{{ __('Dégagée < 20°') }}</span>
            <span class="inline-flex items-center gap-2"><span class="size-2 rounded-full bg-[#f0883e]"></span>{{ __('Masquée') }}</span>
            <span class="inline-flex items-center gap-2"><span class="size-2 rounded-sm bg-[#f0c674]/60"></span>{{ __('Obstacles') }}</span>
        </div>
        <label class="mt-5 grid gap-3 text-xs text-text-main/65">
            {{ __('Parcourir la nuit') }}
            <input type="range" min="0" max="{{ count($samples) - 1 }}" step="1" x-model="selected" class="min-h-11 w-full accent-sky-400">
        </label>
        <p class="text-sm font-medium text-text-bold" x-text="current.time + ' · ' + state"></p>
        <p class="mt-2 text-xs text-text-main/60" x-text="@js(__('Azimut')) + ' ' + current.azimuth.toFixed(1) + @js(__('° · hauteur')) + ' ' + current.altitude.toFixed(1) + @js(__('° · obstacle')) + ' ' + current.horizon.toFixed(1) + '°'"></p>
        <p class="mt-3 text-[11px] leading-relaxed text-text-main/45">{{ __('Trajectoire échantillonnée toutes les 10 minutes pendant la nuit astronomique. Le seuil de visibilité est de 20° ; le Planner applique son propre seuil d’acquisition, généralement 30°. La précision dépend du masque renseigné.') }}</p>
    @endif
</article>
