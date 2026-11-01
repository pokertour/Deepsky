@extends('layouts.app')
@section('robots', request()->anyFilled(['search', 'type', 'constellation', 'year', 'equipment', 'sort']) ? 'noindex, follow' : 'index, follow')

@if ($overview['cover'])
    @section('og_image', $overview['cover'])
@endif

@section('content')
    @if ($photos->onFirstPage() && ! request()->anyFilled(['search', 'type', 'constellation', 'year', 'equipment', 'sort']) && $overview['photos'] > 0)
        <section class="col-span-full -mt-4 mb-2 flex flex-col gap-6 rounded-2xl border border-border/70 bg-card-bg/40 px-6 py-6 backdrop-blur-sm sm:px-8 lg:flex-row lg:items-center lg:justify-between" aria-label="{{ __('Le portfolio en chiffres') }}">
            <p class="max-w-xl text-[1.05rem] leading-relaxed text-text-main">
                {{ __('Des photons partis il y a des milliers, parfois des millions d’années, collectés') }}
                <span class="text-text-bold">{{ __('pose après pose') }}</span> {{ __('depuis un jardin nantais.') }}
            </p>
            <dl class="grid grid-cols-2 gap-x-8 gap-y-4 font-mono sm:grid-cols-4">
                @foreach ([
                    [trans_choice('image|images', $overview['photos']), $overview['photos'], true],
                    [trans_choice('heure de pose|heures de pose', $overview['hours']), $overview['hours'], true],
                    [trans_choice('cible|cibles', $overview['targets']), $overview['targets'], true],
                    [__('depuis'), $overview['since'], false],
                ] as [$label, $value, $isCount])
                    @if ($value !== null)
                        <div class="flex flex-col-reverse">
                            <dt class="text-[0.65rem] uppercase tracking-[0.18rem] text-text-main/55">{{ $label }}</dt>
                            <dd class="text-2xl font-medium text-text-bold tabular-nums">{{ $isCount ? Illuminate\Support\Number::format($value, locale: app()->getLocale()) : $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </section>
    @endif
    <div id="gallery-filters" x-show="galleryFiltersOpen" x-cloak
        x-transition:enter="transition-[grid-template-rows,opacity] duration-200 ease-out motion-reduce:transition-none"
        x-transition:enter-start="grid-rows-[0fr] opacity-0"
        x-transition:enter-end="grid-rows-[1fr] opacity-100"
        x-transition:leave="transition-[grid-template-rows,opacity] duration-150 ease-in motion-reduce:transition-none"
        x-transition:leave-start="grid-rows-[1fr] opacity-100"
        x-transition:leave-end="grid-rows-[0fr] opacity-0"
        @keydown.escape.stop.prevent="galleryFiltersOpen = false; $refs.filtersToggle.focus()"
        class="col-span-full grid">
        <div class="min-h-0 overflow-hidden">
            <x-search :constellations="$filterOptions['constellations']" :years="$filterOptions['years']" :rigs="$filterOptions['rigs']" :equipment="$filterOptions['equipment']" />
        </div>
    </div>
    @forelse ($photos as $photo)
        <x-card :photo="$photo" :versions="$versionsByTarget[$photo->target] ?? collect()" :priority="$loop->first" />
    @empty
        <div class="col-span-full flex flex-col items-center py-20 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" aria-hidden="true" class="mb-6 size-12 text-border">
                <circle cx="12" cy="12" r="3.5" /><path stroke-linecap="round" d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8" />
            </svg>
            <p class="font-mono text-[0.8rem] uppercase tracking-[0.2rem] text-text-main/60">{{ __('Aucune image ne correspond à cette recherche') }}</p>
            @if (request()->anyFilled(['search', 'type', 'constellation', 'year', 'equipment']))
                <a wire:navigate href="{{ route('welcome') }}"
                    class="mt-6 rounded-full border border-accent/40 px-5 py-2 text-sm text-accent transition-colors hover:bg-accent/10">{{ __('Réinitialiser les filtres') }}</a>
            @endif
        </div>
    @endforelse

    @if ($photos->hasPages())
        <div class="col-span-full mt-12 mb-8">
            {{ $photos->links('pagination.deepsky') }}
        </div>
    @endif

    <!-- Modal System -->
    <div id="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title"
        class="fixed inset-0 w-full h-screen max-h-screen bg-[#010409]/98 z-[2000]
           hidden opacity-0 transition-opacity duration-500 overflow-hidden
           [&.modal-active]:flex [&.modal-active]:flex-col [&.modal-active]:lg:grid [&.modal-active]:lg:grid-cols-[1fr_400px]">
        <button id="modal-close" type="button" aria-label="{{ __('Fermer l\'aperçu') }}" title="{{ __('Fermer (Échap)') }}"
            class="absolute top-[calc(1rem+env(safe-area-inset-top,0px)+var(--pwa-top-spacing))] right-4 sm:right-8 lg:right-[424px] z-[2100] flex size-11 items-center justify-center rounded-full border border-white/10 bg-[#010409]/70 text-text-main backdrop-blur cursor-pointer pointer-events-auto transition-colors hover:border-accent hover:text-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            onclick="closeModal()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" class="size-5">
                <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
            </svg>
        </button>

        <div class="absolute bottom-4 left-4 z-[2100] flex gap-2 lg:bottom-8 lg:left-8">
            <button id="modal-prev" type="button" onclick="navigatePhoto(-1)" aria-label="{{ __('Photo précédente') }}" title="{{ __('Photo précédente (←)') }}" class="flex size-11 items-center justify-center rounded-full border border-white/10 bg-[#010409]/70 text-text-main backdrop-blur transition-colors enabled:hover:border-accent enabled:hover:text-accent disabled:opacity-30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" /></svg>
            </button>
            <button id="modal-next" type="button" onclick="navigatePhoto(1)" aria-label="{{ __('Photo suivante') }}" title="{{ __('Photo suivante (→)') }}" class="flex size-11 items-center justify-center rounded-full border border-white/10 bg-[#010409]/70 text-text-main backdrop-blur transition-colors enabled:hover:border-accent enabled:hover:text-accent disabled:opacity-30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" /></svg>
            </button>
        </div>
        <div id="modal-img-container" class="p-8 flex items-center justify-center relative overflow-hidden h-[50vh] lg:h-full">
            <img id="modal-img" src="" alt="{{ __('Zoom') }}"
                class="max-w-[95%] max-h-[95%] w-auto h-auto object-contain shadow-[0_0_50px_rgba(0,0,0,0.8)] border border-border transition-transform duration-300">
            <div id="annotations-overlay" class="absolute pointer-events-none hidden"></div>

            {{-- Comparateur avant/après entre deux versions de la même cible --}}
            <div id="compare-wrap" class="absolute hidden cursor-ew-resize select-none touch-none">
                <img id="compare-img" src="" alt="{{ __('Version précédente') }}"
                    class="absolute inset-0 w-full h-full object-cover pointer-events-none">
                <div id="compare-handle" class="absolute top-0 bottom-0 w-[2px] bg-accent pointer-events-none">
                    <div class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 left-[1px] w-8 h-8 rounded-full bg-accent text-[#010409] flex items-center justify-center font-bold text-xs shadow-lg">
                        {{ __('↔') }}</div>
                </div>
                <span id="compare-label-before"
                    class="absolute top-3 left-3 bg-[#010409]/80 text-accent font-mono text-[0.65rem] px-2 py-1 rounded pointer-events-none"></span>
                <span id="compare-label-after"
                    class="absolute top-3 right-3 bg-[#010409]/80 text-accent font-mono text-[0.65rem] px-2 py-1 rounded pointer-events-none"></span>
            </div>

            <div id="starless-switch"
                class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 hidden items-center gap-1 rounded-full border border-white/15 bg-[#010409]/80 backdrop-blur p-1 font-mono text-[0.65rem] uppercase tracking-widest">
                <button type="button" data-starless="0" onclick="setStarless(false)"
                    class="rounded-full px-3 py-1.5 transition-colors">{{ __('Avec étoiles') }}</button>
                <button type="button" data-starless="1" onclick="setStarless(true)"
                    class="rounded-full px-3 py-1.5 transition-colors">{{ __('Starless') }}</button>
            </div>
        </div>

        <aside
            class="bg-card-bg border-t lg:border-t-0 lg:border-l border-border p-6 lg:p-10
              flex-1 overflow-y-auto lg:h-full lg:max-w-[400px]">
            <div class="mb-8">
                <h2 id="modal-title" class="text-[2rem] text-text-bold font-bold mb-2 uppercase tracking-tight">{{ __('OBJECT_NAME') }}
                </h2>
                <p id="modal-subtitle" class="text-accent text-[0.9rem] font-mono tracking-wider">{{ __('CONSTELLATION_DATA') }}</p>
            </div>

            <div id="celestial-section" class="mb-10 hidden">
                <h4
                    class="text-accent uppercase text-[0.8rem] tracking-[0.1rem] mb-5 border-b border-border pb-2 font-bold">
                    {{ __('OBJET CÉLESTE') }}</h4>
                <div class="space-y-0">
                    <x-modal-tech-row label="{{ __('Désignation') }}" id="celestial-designation" />
                    <div id="celestial-name-row" class="hidden"><x-modal-tech-row label="{{ __('Nom') }}" id="celestial-name" /></div>
                    <div id="celestial-type-row" class="hidden"><x-modal-tech-row label="{{ __('Type') }}" id="celestial-type" /></div>
                    <div id="celestial-constellation-row" class="hidden"><x-modal-tech-row label="{{ __('Constellation') }}" id="celestial-constellation" /></div>
                    <div id="celestial-magnitude-row" class="hidden"><x-modal-tech-row label="{{ __('Magnitude') }}" id="celestial-magnitude" /></div>
                    <div id="celestial-distance-row" class="hidden"><x-modal-tech-row label="{{ __('Distance') }}" id="celestial-distance" /></div>
                    <div id="celestial-size-row" class="hidden"><x-modal-tech-row label="{{ __('Taille angulaire') }}" id="celestial-size" /></div>
                    <div id="celestial-physical-size-row" class="hidden"><x-modal-tech-row label="{{ __('Taille réelle') }}" id="celestial-physical-size" /></div>
                </div>
            </div>

            <div class="mb-10">
                <h4
                    class="text-accent uppercase text-[0.8rem] tracking-[0.1rem] mb-5 border-b border-border pb-2 font-bold">
                    {{ __('EXIF / COCKPIT DATA') }}</h4>
                <div class="space-y-0">
                    <x-modal-tech-row label="{{ __('Pose') }}" id="spec-integ" />
                    <x-modal-tech-row label="{{ __('Date') }}" id="spec-date" />
                    <div id="spec-region-row" class="hidden"><x-modal-tech-row label="{{ __('Région') }}" id="spec-region" /></div>
                    <x-modal-tech-row label="{{ __('Gain') }}" id="spec-gain" />
                    <x-modal-tech-row label="{{ __('Offset') }}" id="spec-offset" />
                    <x-modal-tech-row label="{{ __('Temp. capteur') }}" id="spec-temp" />
                    <x-modal-tech-row label="{{ __('Focale') }}" id="spec-focal" />
                </div>
            </div>

            <div>
                <h4
                    class="text-accent uppercase text-[0.8rem] tracking-[0.1rem] mb-5 border-b border-border pb-2 font-bold">
                    {{ __('EQUIPMENT SETUP') }}</h4>
                <div class="space-y-0">
                    <x-modal-tech-row label="{{ __('Rig') }}" id="setup-rig" />
                    <div id="setup-items"></div>
                    <x-modal-tech-row label="{{ __('Filtres') }}" id="setup-filters" />
                </div>
            </div>

            <div id="versions-section" class="mt-10 hidden">
                <h4
                    class="text-accent uppercase text-[0.8rem] tracking-[0.1rem] mb-5 border-b border-border pb-2 font-bold">
                    {{ __('VERSIONS DE CETTE CIBLE') }}</h4>
                <div id="versions-list" class="space-y-2"></div>
            </div>

            <div id="astro-section" class="mt-10 hidden">
                <h4
                    class="text-accent uppercase text-[0.8rem] tracking-[0.1rem] mb-5 border-b border-border pb-2 font-bold">
                    {{ __('ASTROMÉTRIE') }}</h4>
                <div class="space-y-0">
                    <x-modal-tech-row label="{{ __('AD (RA)') }}" id="astro-ra" />
                    <x-modal-tech-row label="{{ __('Déclinaison') }}" id="astro-dec" />
                    <x-modal-tech-row label="{{ __('Échelle') }}" id="astro-scale" />
                    <x-modal-tech-row label="{{ __('Champ') }}" id="astro-radius" />
                </div>
                <div id="astro-objects" class="flex flex-wrap gap-1.5 mt-4"></div>
                <button id="astro-toggle" type="button" onclick="toggleAnnotations()"
                    class="hidden mt-5 w-full py-3 border border-accent/30 bg-accent/5 text-accent uppercase text-[0.75rem] tracking-[0.2rem] font-bold hover:bg-accent hover:text-[#010409] transition-all duration-300">
                    {{ __('Afficher les annotations') }}
                </button>
            </div>

            <div id="modal-description-section" class="mt-6 hidden">
                <h3 class="mb-3 text-xs uppercase tracking-widest text-accent">{{ __('Récit de capture') }}</h3>
                <p id="modal-description" class="whitespace-pre-line text-sm leading-relaxed text-text-main"></p>
            </div>
            <a wire:navigate id="modal-detail-link" href="" class="mt-6 block rounded border border-accent/40 px-4 py-3 text-center text-sm text-accent">{{ __('Voir la fiche photo') }}</a>
            <div class="mt-12 grid grid-cols-2 gap-3 mb-3">
                <button type="button" onclick="sharePhoto(this, galleryCurrentPhoto.share_url, galleryCurrentPhoto.title)"
                    class="flex items-center justify-center gap-2 py-3 border border-border text-text-main uppercase text-[0.7rem] tracking-[0.15rem] font-bold hover:border-accent hover:text-accent transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                    </svg>
                    <span class="share-label">{{ __('Partager') }}</span>
                </button>
                <button type="button" onclick="copyPhotoLink(this, galleryCurrentPhoto.share_url)"
                    class="flex items-center justify-center gap-2 py-3 border border-border text-text-main uppercase text-[0.7rem] tracking-[0.15rem] font-bold hover:border-accent hover:text-accent transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                    <span class="share-label">{{ __('Copier le lien') }}</span>
                </button>
            </div>
            <div>
                <a id="modal-original-link" href="" target="_blank" rel="noopener"
                    class="flex items-center justify-center gap-3 w-full py-4 border border-accent/30 bg-accent/5 text-accent uppercase text-[0.8rem] tracking-[0.2rem] font-bold hover:bg-accent hover:text-[#010409] transition-all duration-300 group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="w-5 h-5 group-hover:rotate-45 transition-transform">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    {{ __('Afficher l\'original') }} <span id="modal-size" class="opacity-50 text-[0.7rem] font-mono ml-2"></span>
                </a>
            </div>
        </aside>
    </div>
@endsection
