{{-- La Plongée : du ciel au-dessus de Nantes jusqu'à la photo, puis exploration de son champ. --}}
<template x-teleport="body">
    <template x-if="diving">
        <div x-data="liveSky(@js($dive))" @keydown.escape.window="diving = false" role="dialog" aria-modal="true" aria-label="{{ __('Plongée vers :target', ['target' => $dive['focus']['label']]) }}"
            class="fixed inset-0 z-[3000] overflow-hidden bg-[#05080f] font-['Space_Grotesk'] text-text-main">
            <canvas x-ref="sky" aria-hidden="true" class="absolute inset-0 size-full transition-opacity duration-700" :class="phase === 'photo' && 'opacity-25'"></canvas>

            <div class="absolute inset-x-0 top-0 z-20 flex items-center justify-between gap-3 p-4 pt-[calc(1rem+env(safe-area-inset-top,0px))]">
                <p class="min-w-0 truncate font-mono text-[11px] uppercase tracking-[0.25rem] text-accent">{{ __('● Plongée · :value', ['value' => $dive['focus']['label']]) }}</p>
                <div class="flex shrink-0 gap-2">
                    <button type="button" x-show="phase === 'photo'" x-cloak @click="replayDive()" class="rounded-full border border-white/15 bg-[#05080f]/70 px-4 py-2 text-xs text-text-bold backdrop-blur transition hover:border-accent hover:text-accent">{{ __('Revoir la plongée') }}</button>
                    <button type="button" @click="diving = false" aria-label="{{ __('Fermer la plongée') }}" class="grid size-10 place-items-center rounded-full border border-white/15 bg-[#05080f]/70 text-text-bold backdrop-blur transition hover:border-accent hover:text-accent">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" class="size-5"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>
            </div>

            <p x-show="phase !== 'photo'" x-transition.opacity aria-live="polite"
                class="absolute inset-x-4 bottom-[calc(1.5rem+env(safe-area-inset-bottom,0px))] z-20 mx-auto max-w-xl rounded-2xl border border-white/10 bg-[#05080f]/75 px-5 py-4 text-center text-sm text-text-bold backdrop-blur"
                x-text="phase === 'sky' ? focusNote : @js(__('Plongée vers')) + ' ' + @js($dive['focus']['label']) + @js(__('… le champ de la photo se rapproche.'))"></p>

            <div x-show="phase === 'photo'" x-cloak class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-4 px-4 pb-4 pt-20"
                x-data="{ natural: null }">
                <figure class="relative transition-[transform,opacity] duration-[1200ms] ease-out" :style="photoStyle">
                    <img src="{{ $dive['focus']['image'] }}" alt="{{ $dive['focus']['title'] }}" @load="natural = { w: $el.naturalWidth, h: $el.naturalHeight }"
                        class="block max-h-[62vh] max-w-[92vw] rounded-lg border border-white/10 shadow-[0_0_80px_rgba(88,166,255,0.15)] sm:max-h-[68vh]">
                    <template x-if="natural">
                        <div class="absolute inset-0">
                            <template x-for="(annotation, index) in @js($dive['focus']['annotations'])" :key="index">
                                <button type="button" @click="focusSelection = annotation" @mouseenter="focusSelection = annotation"
                                    :aria-label="annotation.name"
                                    :style="`left: ${annotation.x / natural.w * 100}%; top: ${annotation.y / natural.h * 100}%; width: max(22px, ${annotation.r * 2 / natural.w * 100}%)`"
                                    :class="focusSelection === annotation ? 'border-accent bg-accent/10' : 'border-accent/50 hover:border-accent'"
                                    class="absolute aspect-square -translate-x-1/2 -translate-y-1/2 rounded-full border-2 transition-colors"></button>
                            </template>
                        </div>
                    </template>
                </figure>

                @php($main = $dive['focus'])
                <div class="w-full max-w-2xl rounded-2xl border border-white/10 bg-[#0d1117]/85 p-5 backdrop-blur" aria-live="polite">
                    <p class="font-mono text-[11px] uppercase tracking-[0.2rem] text-accent" x-text="focusSelection ? focusSelection.name : @js($main['label'])"></p>
                    <p class="mt-2 text-lg font-semibold text-text-bold" x-text="(focusSelection ?? @js(['light' => $main['light']])).light
                        ? @js(__('Sa lumière est partie il y a')) + ' ' + (focusSelection ?? @js(['light' => $main['light']])).light.duration + ', ' + (focusSelection ?? @js(['light' => $main['light']])).light.epoch + '.'
                        : @js(__('Sa distance n’est pas encore connue des catalogues.'))"></p>
                    <p class="mt-2 text-sm text-text-main/65" x-text="focusSelection ? @js(__('Survole ou touche les autres cercles : chaque objet identifié dans la photo a sa propre histoire.')) : @js(__(':count objets identifiés dans le champ : survole ou touche les cercles pour remonter le temps.', ['count' => count($main['annotations'])]))"></p>
                </div>
            </div>
        </div>
    </template>
</template>
