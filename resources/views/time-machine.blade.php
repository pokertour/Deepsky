@extends('layouts.app')

@section('title', __('Remonter le temps — L’âge de la lumière des photos | Deepsky'))
@section('meta_description', __('Regarder loin, c’est regarder tôt : chaque photo du portfolio placée sur une frise selon l’âge de sa lumière, face à l’histoire de la Terre.'))
@section('og_title', __('Remonter le temps — Deepsky'))

@section('content')
    <section class="col-span-full">
        <header class="mx-auto mb-14 max-w-3xl text-center">
            <p class="font-mono text-[0.7rem] uppercase tracking-[0.3rem] text-accent">{{ __('La machine à remonter le temps') }}</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-text-bold sm:text-5xl">{{ __('Regarder loin, c’est regarder tôt') }}</h1>
            <p class="mt-5 text-text-main/75 sm:text-lg">
                {{ __('La lumière voyage vite, mais pas instantanément. Celle qui a impressionné le capteur était partie de chaque objet il y a des siècles, des millénaires, parfois des millions d’années. Voici les photos du portfolio rangées selon l’âge de leur lumière, face à l’histoire de la Terre.') }}
            </p>
            @if ($farthest)
                <p class="mt-6 inline-flex flex-wrap justify-center gap-x-2 rounded-full border border-accent/30 bg-accent/5 px-5 py-2 text-sm text-text-bold">
                    <span>{{ __(':photo_count photo:value datée:value2', ['photo_count' => $photoCount, 'value' => $photoCount > 1 ? 's' : '', 'value2' => $photoCount > 1 ? 's' : '']) }}</span><span class="text-text-main/40">·</span>
                    <span>{{ __('la plus ancienne lumière : :value', ['value' => \App\Support\LightTravel::duration($farthest->celestialObject->distance_ly)]) }}</span>
                </p>
            @endif
        </header>

        @if ($entries === [])
            <p class="py-16 text-center font-mono text-[0.8rem] uppercase tracking-[0.2rem] text-text-main/60">{{ __('Les distances des objets photographiés sont en cours de récupération.') }}</p>
        @else
            <ol class="relative mx-auto max-w-4xl">
                <span aria-hidden="true" class="absolute bottom-0 left-4 top-0 w-px bg-gradient-to-b from-accent/70 via-accent/30 to-transparent sm:left-1/2"></span>
                @foreach ($entries as $entry)
                    <li class="relative" style="margin-top: {{ $loop->first ? 0 : 24 + $entry['gap'] }}px">
                        @isset($entry['milestone'])
                            <div class="relative flex items-center gap-4 pl-10 sm:justify-center sm:pl-0">
                                <span aria-hidden="true" class="absolute left-4 top-1/2 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-amber-300/80 sm:left-1/2"></span>
                                <p class="rounded-full border border-amber-300/25 bg-[#0d1117]/90 px-4 py-1.5 text-xs text-amber-100/90 backdrop-blur sm:text-sm">
                                    <span class="font-mono text-amber-300/90">{{ __('il y a :value', ['value' => $entry['light']['duration']]) }}</span> · {{ $entry['milestone'] }}
                                </p>
                            </div>
                        @else
                            @php($photo = $entry['photo'])
                            @php($photoRank = ($photoRank ?? -1) + 1)
                            @php($right = $photoRank % 2 === 1)
                            <div class="relative pl-10 sm:grid sm:grid-cols-2 sm:gap-12 sm:pl-0">
                                <span aria-hidden="true" class="absolute left-4 top-8 size-3 -translate-x-1/2 rounded-full border-2 border-accent bg-bg shadow-[0_0_14px_rgba(88,166,255,0.8)] sm:left-1/2"></span>
                                <a wire:navigate href="{{ route('astrophoto.show', $photo) }}" @class(['group block overflow-hidden rounded-2xl border border-border bg-card-bg transition hover:-translate-y-1 hover:border-accent/60', 'sm:col-start-2' => $right])>
                                    <div class="aspect-[16/9] overflow-hidden bg-black">
                                        <img src="{{ $photo->thumbnailUrl() ?? $photo->getFirstMediaUrl('astros', 'thumb') }}" alt="{{ $photo->localized('title') }}" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-105">
                                    </div>
                                    <div class="p-5">
                                        <p class="font-mono text-[0.7rem] uppercase tracking-[0.15rem] text-accent">{{ $photo->celestialObject->designation() }}</p>
                                        <h2 class="mt-1 text-xl font-bold text-text-bold group-hover:text-accent">{{ $photo->localized('title') }}</h2>
                                        <p class="mt-3 text-sm text-text-main/80">{{ __('Sa lumière est partie il y a') }} <strong class="text-text-bold">{{ $entry['light']['duration'] }}</strong>, {{ $entry['light']['epoch'] }}.</p>
                                    </div>
                                </a>
                                <p @class(['mt-3 font-mono text-[0.7rem] uppercase tracking-[0.2rem] text-text-main/50 sm:mt-8', 'sm:col-start-1 sm:row-start-1 sm:text-right' => $right])>
                                    {{ __(':value années-lumière', ['value' => \Illuminate\Support\Number::format(round($entry['years']), locale: app()->getLocale())]) }}
                                </p>
                            </div>
                        @endisset
                    </li>
                @endforeach
            </ol>
            <p class="mx-auto mt-16 max-w-2xl text-center text-xs text-text-main/50">{{ __('Distances issues de la base SIMBAD (CDS, Strasbourg), médiane des mesures publiées. L’espacement de la frise suit une échelle logarithmique.') }}</p>
        @endif
    </section>
@endsection
