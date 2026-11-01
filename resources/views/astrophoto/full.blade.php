<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="bg-[#010409]">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ __(':title - Full Resolution - :value', ['title' => $photo->localized('title'), 'value' => config('app.name', 'Deepsky')]) }}</title>
    <link rel="canonical" href="{{ route('astrophoto.show', $photo) }}">

    @php
        $defaultDescription = __("Portfolio d'astrophotographie amateur : galaxies, nébuleuses et amas du ciel profond capturés depuis le ciel nantais, avec leurs données techniques.");

        $celestial = $photo->celestialObject;

        $ogDescription = collect([
            $celestial?->displayName() ?: $photo->target,
            $celestial?->constellation,
            $celestial?->formattedDistance(),
            $photo->exposure ? $photo->formatted_integ . ' '.__('d’intégration') : null,
            $photo->captured_at?->format('d/m/Y'),
            $photo->observingLocation?->region,
            $photo->equipmentSetup?->name,
            ...($photo->equipmentSetup
                ? $photo->equipmentSetup->enabledSetupItems->pluck('name')->all()
                : collect([$photo->opticalTube?->name, $photo->camera?->name])->filter()->all()),
            $photo->filters ? 'filtres ' . $photo->filters : null,
        ])->filter()->implode(' · ') ?: $defaultDescription;
    @endphp

    @unless($photo->is_published)<meta name="robots" content="noindex, nofollow">@endunless
    <meta name="description" content="{{ $ogDescription }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $photo->localized('title') }} — Deepsky">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $photo->getFirstMediaUrl('astros', 'optimized') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $photo->localized('title') }} — Deepsky">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $photo->getFirstMediaUrl('astros', 'optimized') }}">

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/logo/deepsky.svg') }}">
    <x-pwa-head />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Les curseurs pour indiquer l'action */
        .zoom-in {
            cursor: zoom-in;
        }

        .zoom-out {
            cursor: zoom-out;
        }

        /* Conteneur pour gérer le défilement lors du zoom */
        #container {
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            /* Permet le scroll quand l'image est zoomée */
        }

        /* L'image par défaut (Fit to Screen) */
        #full-image {
            max-width: 100%;
            max-height: 100%;
            object-contain: contain;
            /* Garde les proportions */
            transition: transform 0.3s ease-in-out;
            transform-origin: center center;
            /* Zoom depuis le centre */
        }

        /* L'image en mode "Full Resolution" */
        #full-image.is-zoomed {
            max-width: none;
            max-height: none;
            transform: scale(1);
            /* On annule le scale du JS, on gère par max-width */
            cursor: zoom-out;
        }
    </style>
    @livewireStyles
</head>

<body class="m-0 p-0 overflow-hidden bg-[#010409]">

    @php
        $starlessUrl = $photo->getFirstMediaUrl('starless', 'full_res');
        $showStarless = $starlessUrl && request()->boolean('starless');
    @endphp

    <div class="fixed top-[calc(1.25rem+env(safe-area-inset-top,0px)+var(--pwa-top-spacing))] left-5 right-5 z-50 flex flex-wrap items-center gap-2 pointer-events-none">
        <a href="{{ route('welcome') }}" onclick="event.preventDefault(); goBack()"
            class="pointer-events-auto text-white/70 hover:text-white transition-colors uppercase tracking-widest text-xs bg-black/60 backdrop-blur px-3 py-2 rounded">
            {{ __('← Retour') }}
        </a>

        @if ($starlessUrl)
            <div id="starless-switch" class="pointer-events-auto flex items-center gap-1 rounded bg-black/60 backdrop-blur p-1 uppercase tracking-widest text-[10px]">
                <button type="button" data-starless="0" onclick="setStarless(false)" class="rounded px-3 py-1.5 transition-colors">{{ __('Avec étoiles') }}</button>
                <button type="button" data-starless="1" onclick="setStarless(true)" class="rounded px-3 py-1.5 transition-colors">{{ __('Starless') }}</button>
            </div>
        @endif

        <div class="pointer-events-auto ml-auto flex items-center gap-2 uppercase tracking-widest text-xs">
            <button type="button" onclick="sharePhoto(this, @js(route('astrophoto.show', $photo)), @js($photo->localized('title')))"
                class="text-white/70 hover:text-white transition-colors bg-black/60 backdrop-blur px-3 py-2 rounded"><span class="share-label">{{ __('Partager') }}</span></button>
            <button type="button" onclick="copyPhotoLink(this, @js(route('astrophoto.show', $photo)))"
                class="text-white/70 hover:text-white transition-colors bg-black/60 backdrop-blur px-3 py-2 rounded"><span class="share-label">{{ __('Copier le lien') }}</span></button>
        </div>
    </div>

    @if ($photo->astrometry_status === App\Enums\AstrometryStatus::Solved)
        <div class="fixed bottom-5 left-5 z-50 max-w-sm rounded-lg bg-black/60 backdrop-blur p-4 text-xs text-white/80 space-y-2">
            <p class="uppercase tracking-widest text-white/50 text-[10px]">{{ __('Astrométrie — nova.astrometry.net') }}</p>
            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                <span class="text-white/50">{{ __('AD (RA)') }}</span><span>{{ $photo->formatted_ra }}</span>
                <span class="text-white/50">{{ __('Déc.') }}</span><span>{{ $photo->formatted_dec }}</span>
                <span class="text-white/50">{{ __('Échelle') }}</span><span>{{ __(':value″/px', ['value' => number_format($photo->pixel_scale, 2)]) }}</span>
                <span class="text-white/50">{{ __('Rayon du champ') }}</span><span>{{ number_format($photo->field_radius, 2) }}°</span>
            </div>
            @if (!empty($photo->objects_in_field))
                <div class="flex flex-wrap gap-1 pt-1">
                    @foreach ($photo->objects_in_field as $object)
                        <span class="rounded bg-white/10 px-1.5 py-0.5">{{ $object }}</span>
                    @endforeach
                </div>
            @endif
            @if (!empty($photo->annotations))
                <button id="annotations-toggle" type="button" onclick="toggleAnnotations()"
                    class="w-full mt-2 py-2 border border-white/20 text-white/70 uppercase tracking-widest text-[10px] font-bold hover:bg-white/10 hover:text-white transition-colors rounded">
                    {{ __('Afficher les annotations') }}
                </button>
            @endif
        </div>
    @endif

    <nav class="fixed bottom-[calc(1.25rem+env(safe-area-inset-bottom,0px))] right-[max(1.25rem,env(safe-area-inset-right,0px))] z-50 flex gap-2" aria-label="{{ __('Navigation entre les photos') }}">
        @if($previous)<a href="{{ route('astrophoto.full', $previous) }}" aria-label="{{ __('Photo précédente') }}" class="rounded bg-black/70 px-4 py-3 text-white">{{ __('←') }}</a>@endif
        <a href="{{ route('astrophoto.show', $photo) }}" class="rounded bg-black/70 px-4 py-3 text-xs text-white">{{ __('Fiche photo') }}</a>
        @if($next)<a href="{{ route('astrophoto.full', $next) }}" aria-label="{{ __('Photo suivante') }}" class="rounded bg-black/70 px-4 py-3 text-white">{{ __('→') }}</a>@endif
    </nav>
    <div id="container" class="relative" data-photo-navigation data-previous="{{ $previous ? route('astrophoto.full', $previous) : '' }}" data-next="{{ $next ? route('astrophoto.full', $next) : '' }}">
        <img id="full-image" src="{{ $showStarless ? $starlessUrl : $photo->getFirstMediaUrl('astros', 'full_res') }}" alt="{{ $photo->localized('title') }}"
            class="zoom-in" onclick="toggleZoom(this)">
        <div id="annotations-overlay" class="absolute pointer-events-none hidden"></div>
    </div>

    <script>
        const imageSources = {
            stars: @json($photo->getFirstMediaUrl('astros', 'full_res')),
            starless: @json($starlessUrl ?: null),
        };

        let starless = false;

        function setStarless(active) {
            starless = active && !!imageSources.starless;
            document.getElementById('full-image').src = starless ? imageSources.starless : imageSources.stars;
            document.querySelectorAll('#starless-switch [data-starless]').forEach((button) => {
                const selected = (button.dataset.starless === '1') === starless;
                button.classList.toggle('bg-white', selected);
                button.classList.toggle('text-black', selected);
                button.classList.toggle('text-white/70', !selected);
            });

            const url = new URL(window.location);
            starless ? url.searchParams.set('starless', '1') : url.searchParams.delete('starless');
            history.replaceState(history.state, '', url);
        }

        // Onglet ouvert depuis la galerie : on le ferme. En PWA (même fenêtre) : retour arrière.
        // Arrivée directe via un lien partagé : on renvoie vers la galerie.
        function goBack() {
            const fromSite = document.referrer && new URL(document.referrer).origin === window.location.origin;

            if (fromSite && history.length > 1) {
                history.back();
                return;
            }

            window.close();
            setTimeout(() => (window.location.href = @json(route('welcome'))), 150);
        }

        if (document.getElementById('starless-switch')) {
            setStarless(@json($showStarless));
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') goBack();
            if (e.key.toLowerCase() === 's' && imageSources.starless) {
                setStarless(!starless);
            }
        });

        // Coordonnées d'annotation exprimées dans l'espace pixel de la conversion
        // 'optimized' : on les normalise via ses dimensions naturelles.
        const annotations = @json($photo->annotations ?? []);
        const optimizedProbe = new Image();
        let annotationsVisible = false;

        if (annotations.length) {
            optimizedProbe.src = @json($photo->getFirstMediaUrl('astros', 'optimized'));
        }

        function toggleAnnotations() {
            annotationsVisible = !annotationsVisible;
            document.getElementById('annotations-toggle').innerText = annotationsVisible ?
                @js(__('Masquer les annotations')) :
                @js(__('Afficher les annotations'));
            renderAnnotations();
        }

        function renderAnnotations() {
            const overlay = document.getElementById('annotations-overlay');
            const img = document.getElementById('full-image');

            if (!annotationsVisible || !annotations.length || !optimizedProbe.naturalWidth) {
                overlay.classList.add('hidden');
                return;
            }

            const scale = img.clientWidth / optimizedProbe.naturalWidth;

            overlay.style.left = img.offsetLeft + 'px';
            overlay.style.top = img.offsetTop + 'px';
            overlay.style.width = img.clientWidth + 'px';
            overlay.style.height = img.clientHeight + 'px';
            overlay.innerHTML = '';

            annotations.forEach((a) => {
                const r = Math.max(a.r * scale, 14);
                const circle = document.createElement('div');
                circle.style.cssText = `position:absolute;border:2px solid rgba(96,165,250,0.85);border-radius:9999px;` +
                    `left:${a.x * scale - r}px;top:${a.y * scale - r}px;width:${r * 2}px;height:${r * 2}px;`;

                const label = document.createElement('span');
                label.style.cssText = 'position:absolute;left:50%;transform:translateX(-50%);top:100%;margin-top:4px;' +
                    'color:rgba(147,197,253,0.95);font-family:monospace;font-size:11px;white-space:nowrap;' +
                    'background:rgba(1,4,9,0.7);padding:0 4px;border-radius:3px;';
                label.innerText = a.name;
                circle.appendChild(label);

                overlay.appendChild(circle);
            });

            overlay.classList.remove('hidden');
        }

        window.addEventListener('resize', renderAnnotations);
        optimizedProbe.addEventListener('load', renderAnnotations);
        document.getElementById('full-image').addEventListener('load', renderAnnotations);

        function toggleZoom(img) {
            const container = document.getElementById('container');

            if (img.classList.contains('zoom-in')) {
                // --- PASSAGE EN FULL RESOLUTION ---
                img.classList.remove('zoom-in');
                img.classList.add('is-zoomed');

                // On s'assure que le conteneur permet le scroll
                container.style.overflow = 'auto';

                // Optionnel : centrer le scroll après le zoom
                setTimeout(() => {
                    container.scrollLeft = (img.width - container.clientWidth) / 2;
                    container.scrollTop = (img.height - container.clientHeight) / 2;
                }, 50);

            } else {
                // --- RETOUR AU "FIT TO SCREEN" ---
                img.classList.remove('is-zoomed');
                img.classList.add('zoom-in');

                // On remet le conteneur en "hidden" pour éviter les barres de scroll inutiles
                container.style.overflow = 'hidden';
                // On scrolle tout en haut/gauche pour le prochain zoom
                container.scrollLeft = 0;
                container.scrollTop = 0;
            }

            // L'image change de taille (transition 300ms) : on repositionne l'overlay après coup
            setTimeout(renderAnnotations, 350);
        }
    </script>
    @livewireScriptConfig
</body>

</html>
