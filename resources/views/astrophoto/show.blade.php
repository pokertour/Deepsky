@extends('layouts.app')

@section('title', $photo->localized('title').' — Astrophotographie | Deepsky')
@section('og_title', $photo->localized('title').' — Deepsky')
@section('og_image', $photo->getFirstMediaUrl('astros', 'optimized'))
@section('meta_description', \Illuminate\Support\Str::limit($photo->localized('description') ?: $photo->target ?: $photo->localized('title'), 160))
@section('robots', $photo->is_published ? 'index, follow' : 'noindex, nofollow')

@section('content')
    @php
        $celestial = $photo->celestialObject;
        $equipmentRows = $photo->equipmentSetup
            ? $photo->equipmentSetup->enabledSetupItems->map(fn ($item) => ['label' => $item->pivot->role ?: $item->type->label(), 'value' => trim($item->brand.' '.$item->name)])
            : collect([
                ['label' => __('Tube optique'), 'value' => $photo->opticalTube?->name],
                ['label' => __('Caméra'), 'value' => $photo->camera?->name],
                ['label' => __('Monture'), 'value' => $photo->mount?->name],
            ])->filter(fn ($row) => filled($row['value']));
    @endphp
    <article class="col-span-full min-w-0">
        <a wire:navigate href="{{ route('welcome') }}" class="inline-flex items-center gap-1 text-sm text-accent hover:underline"><span aria-hidden="true">←</span> {{ __('Retour à la galerie') }}</a>
        @unless($photo->is_published)
            <p class="mt-4 rounded border border-amber-500/40 bg-amber-500/10 p-4 text-amber-300">{{ __('Aperçu privé — cette photo est en brouillon.') }}</p>
        @endunless
        <header class="my-6">
            <p class="mb-2 font-mono text-xs uppercase tracking-widest text-accent">{{ $celestial?->designation() ?: $photo->target }}</p>
            <h1 class="text-3xl font-bold text-text-bold sm:text-4xl">{{ $photo->localized('title') }}</h1>
            <p class="mt-3 text-sm text-text-main">{{ $photo->captured_at?->format('d/m/Y') }} @if($photo->observingLocation?->region) · {{ $photo->observingLocation->region }} @endif</p>
        </header>
        <a href="{{ route('astrophoto.full', $photo) }}" aria-label="{{ __('Voir :title en pleine résolution', ['title' => $photo->localized('title')]) }}" class="block overflow-hidden rounded-xl border border-border bg-black shadow-[0_25px_60px_-20px_rgba(0,0,0,0.8)] transition-colors hover:border-accent/50">
            <img id="photo-detail-image" data-photo-navigation data-previous="{{ $previous ? route('astrophoto.show', $previous) : '' }}" data-next="{{ $next ? route('astrophoto.show', $next) : '' }}" data-view-url="{{ $photo->is_published ? route('astrophoto.click', $photo) : '' }}" data-csrf="{{ csrf_token() }}" src="{{ $photo->getFirstMediaUrl('astros', 'optimized') }}" alt="{{ $photo->localized('title') }}" fetchpriority="high" class="mx-auto max-h-[75vh] w-full object-contain">
        </a>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            @php($dive = \App\Support\SkyDive::for($photo))
            @if ($dive)
                <span x-data="{ diving: false }" class="contents">
                    <button type="button" @click="diving = true" class="group inline-flex items-center gap-2 rounded-full bg-accent px-4 py-2 text-sm font-semibold text-bg transition hover:bg-sky-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" class="size-4 transition-transform group-hover:scale-110"><circle cx="11" cy="11" r="6.5" /><path stroke-linecap="round" d="m16 16 4.5 4.5M11 8v6M8 11h6" /></svg>
                        {{ __('Plonger vers l’objet') }}
                    </button>
                    @include('astrophoto.partials.sky-dive')
                </span>
            @endif
            <a href="{{ route('astrophoto.full', $photo) }}" class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent border-accent/40 bg-accent/5 text-accent hover:bg-accent/15">{{ __('Pleine résolution') }}</a>
            @if($photo->hasMedia('starless'))
                <a href="{{ route('astrophoto.full', [$photo, 'starless' => 1]) }}" class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent border-border text-text-main hover:border-accent hover:text-accent">{{ __('Version sans étoiles') }}</a>
            @endif
            @if($photo->is_published)
                <button type="button" onclick="sharePhoto(this, @js(route('astrophoto.show', $photo)), @js($photo->localized('title')))" class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent border-border text-text-main hover:border-accent hover:text-accent"><span class="share-label">{{ __('Partager') }}</span></button>
                <button type="button" onclick="copyPhotoLink(this, @js(route('astrophoto.show', $photo)))" class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent border-border text-text-main hover:border-accent hover:text-accent"><span class="share-label">{{ __('Copier le lien') }}</span></button>
            @endif
        </div>
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            @if($photo->localized('object_description'))
                <section class="rounded-xl border border-border bg-card-bg p-6 lg:col-span-2">
                    <h2 class="mb-4 text-lg font-bold text-text-bold">{{ __('À la découverte de cet objet') }}</h2>
                    <p class="whitespace-pre-line text-text-main leading-relaxed">{{ $photo->localized('object_description') }}</p>
                </section>
            @endif
            @if($photo->localized('description'))
                <section class="rounded-xl border border-border bg-card-bg p-6 lg:col-span-2">
                    <h2 class="mb-4 text-lg font-bold text-text-bold">{{ __('Récit de capture') }}</h2>
                    <p class="whitespace-pre-line text-text-main leading-relaxed">{{ $photo->localized('description') }}</p>
                </section>
            @endif
            <section @class(['rounded-xl border border-border bg-card-bg p-6', 'lg:col-span-2' => ! $celestial && $photo->astrometry_status !== \App\Enums\AstrometryStatus::Solved])>
                <h2 class="mb-4 text-lg font-bold text-text-bold">{{ __('Capture et matériel') }}</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm [&>dt]:text-text-main/60">
                    <dt>{{ __('Intégration') }}</dt><dd class="text-right text-text-bold">{{ $photo->formatted_integ }}</dd>
                    @foreach(['Filtres' => $photo->filters, 'Gain' => $photo->gain, 'Offset' => $photo->offset, 'Température' => $photo->sensor_temp !== null ? $photo->sensor_temp.' °C' : null, 'Focale' => $photo->focal_length ? $photo->focal_length.' mm' : null, 'Rig' => $photo->equipmentSetup?->name] as $label => $value)
                        @if($value !== null && $value !== '')<dt>{{ __($label) }}</dt><dd class="text-right text-text-bold">{{ $value }}</dd>@endif
                    @endforeach
                    @foreach($equipmentRows as $equipment)<dt>{{ $equipment['label'] }}</dt><dd class="text-right text-text-bold">{{ $equipment['value'] }}</dd>@endforeach
                </dl>
            </section>
            @if($celestial)
                <section class="rounded-xl border border-border bg-card-bg p-6">
                    <h2 class="mb-4 text-lg font-bold text-text-bold">{{ __('Objet céleste') }}</h2>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm [&>dt]:text-text-main/60">
                        @foreach(['Désignation' => $celestial->designation(), 'Nom' => $celestial->name, 'Type' => $celestial->type, 'Constellation' => $celestial->constellation, 'Magnitude' => $celestial->magnitude, 'Distance' => $celestial->formattedDistance(), 'Dimensions' => $celestial->formattedAngularSize(), 'Taille réelle' => $celestial->formattedPhysicalSize()] as $label => $value)
                            @if($value !== null && $value !== '')<dt>{{ __($label) }}</dt><dd class="text-right text-text-bold">{{ $value }}</dd>@endif
                        @endforeach
                    </dl>
                </section>
            @endif
            @if($photo->astrometry_status === \App\Enums\AstrometryStatus::Solved)
                <section class="rounded-xl border border-border bg-card-bg p-6">
                    <h2 class="mb-4 text-lg font-bold text-text-bold">{{ __('Astrométrie') }}</h2>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm [&>dt]:text-text-main/60">
                        <dt>{{ __('Ascension droite') }}</dt><dd class="text-right text-text-bold">{{ $photo->formatted_ra }}</dd>
                        <dt>{{ __('Déclinaison') }}</dt><dd class="text-right text-text-bold">{{ $photo->formatted_dec }}</dd>
                        <dt>{{ __('Échantillonnage') }}</dt><dd class="text-right text-text-bold">{{ __(':pixel_scale″/px', ['pixel_scale' => $photo->pixel_scale]) }}</dd>
                        <dt>{{ __('Rayon du champ') }}</dt><dd class="text-right text-text-bold">{{ $photo->field_radius }}°</dd>
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-2">@foreach($photo->objects_in_field ?? [] as $object)<span class="rounded border border-accent/30 px-2 py-1 text-xs text-accent">{{ $object }}</span>@endforeach</div>
                </section>
            @endif
        </div>
        @if(filled($photo->acquisition_sessions))
            <x-acquisition-summary :sessions="$photo->acquisition_sessions" />
        @endif
        @if($photo->is_published)
            <div class="mt-8 rounded-xl border border-accent/25 bg-card-bg p-5 sm:p-8">
                <x-newsletter-opt-in />
            </div>
        @endif
        @if($versions->isNotEmpty())
            <section class="mt-8"><h2 class="mb-4 text-lg font-bold text-text-bold">{{ __('Autres captures de cette cible') }}</h2><div class="flex flex-wrap gap-3">@foreach($versions as $version)<a wire:navigate href="{{ route('astrophoto.show', $version) }}" class="rounded border border-border px-4 py-3 text-sm hover:border-accent">{{ $version->localized('title') }} · {{ $version->captured_at?->format('d/m/Y') }}</a>@endforeach</div></section>
        @endif
        <nav class="mt-8 flex justify-between gap-4 border-t border-border pt-6" aria-label="{{ __('Navigation entre les photos') }}">
            <div class="min-w-0">@if($previous)<a wire:navigate href="{{ route('astrophoto.show', $previous) }}" rel="prev" class="group block py-1 text-sm"><span class="block font-mono text-[0.65rem] uppercase tracking-widest text-text-main/50">{{ __('← Précédente') }}</span><span class="text-accent group-hover:underline">{{ $previous->localized('title') }}</span></a>@endif</div>
            <div class="min-w-0 text-right">@if($next)<a wire:navigate href="{{ route('astrophoto.show', $next) }}" rel="next" class="group block py-1 text-sm"><span class="block font-mono text-[0.65rem] uppercase tracking-widest text-text-main/50">{{ __('Suivante →') }}</span><span class="text-accent group-hover:underline">{{ $next->localized('title') }}</span></a>@endif</div>
        </nav>
    </article>
@endsection
