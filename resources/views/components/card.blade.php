@props(['photo', 'versions' => null, 'priority' => false])

@php
    $likedIds = json_decode(request()->cookie('liked_photos') ?? '[]', true) ?: [];
    $isLiked = in_array($photo->id, array_map('intval', $likedIds), true);

    $rigName = $photo->equipmentSetup?->name;
    $publicRegion = $photo->observingLocation?->region;
    $celestial = $photo->celestialObject;
    $equipmentRows = $photo->equipmentSetup
        ? $photo->equipmentSetup->enabledSetupItems->map(fn ($item) => [
            'label' => $item->pivot->role ?: $item->type->label(),
            'value' => trim(($item->brand ?? '') . ' ' . $item->name),
        ])->values()
        : collect([
            ['label' => __('Tube optique'), 'value' => trim(($photo->opticalTube?->brand ?? '') . ' ' . ($photo->opticalTube?->name ?? ''))],
            ['label' => __('Monture'), 'value' => trim(($photo->mount?->brand ?? '') . ' ' . ($photo->mount?->name ?? ''))],
            ['label' => __('Caméra'), 'value' => trim(($photo->camera?->brand ?? '') . ' ' . ($photo->camera?->name ?? ''))],
        ])->filter(fn ($row) => $row['value'] !== '')->values();

    $modalPayload = [
        'id' => $photo->id,
        'title' => $photo->localized('title'),
        'subtitle' => $photo->target,
        'img' => $photo->getFirstMediaUrl('astros', 'optimized'),
        'starless' => $photo->getFirstMediaUrl('starless', 'optimized') ?: null,
        'share_url' => route('astrophoto.show', $photo),
        'description' => $photo->localized('description'),
        'date' => $photo->captured_at?->format('d/m/Y'),
        'integ' => $photo->formatted_integ,
        'gain' => $photo->gain,
        'offset' => $photo->offset,
        'temp' => $photo->sensor_temp,
        'focal' => $photo->focal_length,
        'rig' => $rigName,
        'equipment' => $equipmentRows,
        'filters' => $photo->filters,
        'region' => $publicRegion,
        'celestial' => $celestial ? [
            'designation' => $celestial->designation(),
            'name' => $celestial->name,
            'type' => $celestial->type ? ucfirst($celestial->type) : null,
            'constellation' => $celestial->constellation,
            'magnitude' => $celestial->magnitude !== null ? number_format($celestial->magnitude, 1, ',', ' ') : null,
            'distance' => $celestial->formattedDistance(),
            'size' => $celestial->formattedAngularSize(),
            'physical_size' => $celestial->formattedPhysicalSize(),
        ] : null,
        'versions' => ($versions ?? collect())
            ->reject(fn ($version) => $version->id === $photo->id)
            ->map(fn ($version) => [
                'id' => $version->id,
                'img' => $version->getFirstMediaUrl('astros', 'optimized'),
                'date' => $version->captured_at?->format('d/m/Y'),
                'integ' => $version->formatted_integ,
            ])->values(),
        'astro' => $photo->astrometry_status === App\Enums\AstrometryStatus::Solved ? [
            'ra' => $photo->formatted_ra,
            'dec' => $photo->formatted_dec,
            'scale' => number_format($photo->pixel_scale, 2) . '″/px',
            'radius' => number_format($photo->field_radius, 2) . '°',
            'objects' => $photo->objects_in_field ?? [],
            'annotations' => $photo->annotations ?? [],
        ] : null,
    ];
@endphp

<article
    data-photo="{{ json_encode($modalPayload) }}"
    onclick="openModal(this)"
    tabindex="0"
    aria-label="{{ __('Ouvrir l’aperçu de :title', ['title' => $photo->localized('title')]) }}"
    class="group flex flex-col bg-card-bg border border-border rounded-xl overflow-hidden transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] cursor-pointer hover:-translate-y-2 hover:border-accent/70 hover:shadow-[0_18px_50px_-12px_rgba(88,166,255,0.25)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent motion-reduce:transition-none motion-reduce:hover:translate-y-0">
    <div class="relative aspect-[16/10] overflow-hidden">
        <div class="absolute top-3 left-3 flex flex-wrap gap-1.5 z-10">
            @foreach ($photo->tags ?? [] as $tag)
                <span
                    class="bg-[#010409]/70 backdrop-blur-sm text-accent px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-lg border border-accent/30">
                    {{ $tag }}
                </span>
            @endforeach
        </div>

        <button type="button" onclick="event.stopPropagation(); toggleLike(this, {{ $photo->id }})"
           
            data-liked="{{ $isLiked ? '1' : '0' }}"
            aria-pressed="{{ $isLiked ? 'true' : 'false' }}"
            aria-label="{{ __('J’aime :title', ['title' => $photo->localized('title')]) }}"
            class="absolute top-3 right-3 z-10 flex items-center gap-1.5 bg-[#010409]/70 backdrop-blur-sm border border-white/10 rounded-full px-2.5 py-1 text-[0.7rem] font-mono transition-colors hover:border-tech-red/60 {{ $isLiked ? 'text-tech-red' : 'text-white/70' }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                fill="{{ $isLiked ? 'currentColor' : 'none' }}" aria-hidden="true" class="w-3.5 h-3.5 like-icon">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
            </svg>
            <span class="like-count">{{ $photo->likes_count }}</span>
        </button>

        <img src="{{ $photo->getFirstMediaUrl('astros', 'thumb') }}" alt="{{ $photo->localized('title') }}"
            decoding="async" {{ $priority ? 'fetchpriority=high' : 'loading=lazy' }}
            class="w-full h-full object-cover transition-transform duration-[800ms] ease-[cubic-bezier(0.2,1,0.3,1)] group-hover:scale-[1.08]">

        <div
            class="absolute inset-x-0 bottom-0 p-4 bg-gradient-to-t from-[#010409]/90 to-transparent flex flex-wrap gap-x-6 gap-y-2 font-mono text-[0.7rem] opacity-0 transition-opacity duration-300 group-hover:opacity-100">
            @if ($photo->gain)
                <div class="flex gap-1.5"><span class="text-white/45">{{ __('GAIN') }}</span><b
                        class="text-accent font-medium">{{ $photo->gain }}</b></div>
            @endif
            @if ($photo->sensor_temp)
                <div class="flex gap-1.5"><span class="text-white/45">{{ __('TEMP') }}</span><b
                        class="text-tech-red font-medium">{{ __(':sensor_temp°C', ['sensor_temp' => $photo->sensor_temp]) }}</b></div>
            @endif
            @if ($photo->exposure)
                <div class="flex gap-1.5"><span class="text-white/45">{{ __('INTEG') }}</span><b
                        class="text-tech-green font-medium">{{ $photo->formatted_integ }}</b></div>
            @endif
            @if ($celestial?->formattedDistance())
                <div class="flex gap-1.5"><span class="text-white/45">{{ __('DIST') }}</span><b
                        class="text-accent font-medium">{{ $celestial->formattedDistance() }}</b></div>
            @endif
        </div>
    </div>

    <div class="p-6 flex-grow">
        <div class="flex justify-between items-center mb-4">
            <div
                class="inline-flex min-w-0 max-w-[65%] items-center truncate bg-border/50 border border-border px-3 py-1 rounded-full text-[0.75rem] text-text-main">
                {{ $rigName ?: ($photo->opticalTube?->name ?? __('Configuration non renseignée')) }}
            </div>
            <span class="text-[0.7rem] text-text-main/60 font-mono">
                {{ $photo->captured_at?->format('d/m/Y') }}
            </span>
        </div>

        @if($publicRegion)<p class="mb-3 font-mono text-[0.7rem] text-text-main/45">⌖ {{ $publicRegion }}</p>@endif

        @if($celestial)
            <p class="mb-3 font-mono text-[0.7rem] text-accent/80">
                {{ $celestial->designation() }}
                @if($celestial->constellation)
                    · {{ $celestial->constellation }}
                @endif
                @if($celestial->magnitude !== null)
                    {{ __('· mag :value', ['value' => number_format($celestial->magnitude, 1, ',', ' ')]) }}
                @endif
            </p>
        @endif

        <h2 class="text-[1.4rem] font-bold text-text-bold mb-2">
            <a wire:navigate href="{{ route('astrophoto.show', $photo) }}" onclick="event.stopPropagation()" class="transition-colors hover:text-accent">{{ $photo->localized('title') }}</a>
        </h2>

        <p class="text-[0.9rem] text-[#8b949e] line-clamp-2">
            {{ $photo->localized('description') }}
        </p>
    </div>
</article>
