@props(['mask' => ''])
<section x-data="horizonEditor" @horizon-reference-picked.window="receiveReference($event.detail.bearing)" data-mask="{{ $mask }}" class="min-w-0 rounded-2xl border border-border bg-card-bg p-5 sm:p-8">
    <div class="mb-5">
        <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Obstacles du lieu') }}</p>
        <h2 class="mt-1 text-lg font-semibold text-text-bold">{{ __('Masque d’horizon') }}</h2>
        <p class="mt-2 text-sm text-text-main/55">{{ __('Appuie sur le graphique pour ajouter un point, puis déplace-le pour suivre les toits, arbres ou collines. Tu peux aussi saisir les angles dans le tableau.') }}</p>
    </div>
    <div class="mb-4 flex flex-wrap gap-2">
        <button type="button" @click="add()" class="min-h-11 rounded-xl border border-accent/40 px-3 py-2 text-xs text-accent">{{ __('Ajouter un point') }}</button>
        <button type="button" @click="preset()" class="min-h-11 rounded-xl border border-border px-3 py-2 text-xs">{{ __('Repartir de 8 directions') }}</button>
        <button type="button" @click="clear()" class="min-h-11 rounded-xl border border-border px-3 py-2 text-xs">{{ __('Horizon libre') }}</button>
    </div>
    <div class="relative overflow-hidden rounded-xl border border-border bg-bg">
        <svg x-ref="graph" viewBox="0 0 800 250" preserveAspectRatio="none" class="h-56 w-full touch-none select-none sm:h-64" role="img" aria-label="{{ __('Éditeur d’horizon : azimut de 0 à 360 degrés, hauteur de 0 à 90 degrés') }}"
            @pointerdown="begin($event)" @pointermove="move($event)" @pointerup="end()" @pointercancel="end()" @lostpointercapture="end()">
            <defs><clipPath id="horizon-editor-clip"><rect x="40" y="24" width="720" height="180" /></clipPath></defs>
            <g clip-path="url(#horizon-editor-clip)">
                <g x-show="photo && photoKind === 'panorama'" opacity="0.6" pointer-events="none">
                    <image :href="photo || null" :x="photoLeft" :y="photoTop" width="720" :height="photoHeight" preserveAspectRatio="none" />
                    <image :href="photo || null" :x="photoLeft - 720" :y="photoTop" width="720" :height="photoHeight" preserveAspectRatio="none" />
                </g>
                <polygon :points="area" fill="#f0c674" opacity="0.15" pointer-events="none" />
            </g>
            @foreach ([0, 30, 60, 90] as $height)
                <line x1="40" y1="{{ 204 - $height * 2 }}" x2="760" y2="{{ 204 - $height * 2 }}" stroke="currentColor" class="text-border" stroke-dasharray="4 6" pointer-events="none" />
                <text x="32" y="{{ 208 - $height * 2 }}" text-anchor="end" fill="currentColor" class="hidden text-[12px] text-text-main/60 sm:block" pointer-events="none">{{ $height }}°</text>
            @endforeach
            @foreach ([0 => 'N · 0°', 90 => 'E · 90°', 180 => 'S · 180°', 270 => 'O · 270°', 360 => 'N · 360°'] as $azimuth => $direction)
                <line x1="{{ 40 + $azimuth * 2 }}" y1="24" x2="{{ 40 + $azimuth * 2 }}" y2="204" stroke="currentColor" class="text-border" stroke-dasharray="4 6" pointer-events="none" />
                <text x="{{ 40 + $azimuth * 2 }}" y="228" text-anchor="middle" fill="currentColor" class="hidden text-[12px] text-text-main/70 sm:block" pointer-events="none">{{ $direction }}</text>
            @endforeach
            <polyline :points="line" fill="none" stroke="#f0c674" stroke-width="2.5" pointer-events="none" />
            <g x-ref="points" x-effect="renderPoints()"></g>
        </svg>
        <x-admin.horizon-mobile-labels />
    </div>
    <p class="mt-3 text-xs text-text-main/50">{{ __('Hauteur = angle au-dessus de l’horizontale, en degrés, et non altitude du terrain en mètres. Les points sont reliés, y compris autour du nord.') }}</p>
    <p x-show="error" x-cloak x-text="error" x-ref="error" tabindex="-1" role="alert" class="mt-3 text-sm text-red-300"></p>
    @error('horizon_mask')<p class="mt-3 text-sm text-red-300" role="alert">{{ $message }}</p>@enderror

    <div x-show="points.length" x-cloak class="mt-5 max-h-80 overflow-y-auto rounded-xl border border-border p-3">
        <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_44px] gap-2 text-[11px] text-text-main/50"><span>{{ __('Azimut (°)') }}</span><span>{{ __('Hauteur (°)') }}</span><span></span></div>
        <template x-for="point in points" :key="point.id">
            <div class="mt-2 grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_44px] gap-2">
                <input type="number" min="0" max="359.9999" step="any" required x-model.number="point.azimuth" @input="sync()" aria-label="{{ __('Azimut du point') }}" class="min-h-11 w-full min-w-0 rounded-lg border border-border bg-bg px-3 py-2 text-sm">
                <input type="number" min="0" max="90" step="any" required x-model.number="point.altitude" @input="sync()" aria-label="{{ __('Hauteur du point') }}" class="min-h-11 w-full min-w-0 rounded-lg border border-border bg-bg px-3 py-2 text-sm">
                <button type="button" @click="remove(point.id)" aria-label="{{ __('Supprimer ce point') }}" class="min-h-11 rounded-lg border border-border text-lg text-text-main/60 hover:text-red-300">{{ __('×') }}</button>
            </div>
        </template>
    </div>
    <input type="hidden" name="horizon_mask" :value="json">
    <details class="mt-5 rounded-xl border border-border p-4">
        <summary class="cursor-pointer text-sm text-accent">{{ __('Photographier et tracer le lieu') }}</summary>
        <div class="mt-4 grid gap-4">
            <p class="text-xs leading-relaxed text-text-main/60">{{ __('Prends une photo depuis la position du télescope, sans incliner l’appareil sur le côté. Une photo classique couvre une direction ; un panorama 360° couvre tout le tour. Cale l’horizontale et un repère connu avant de tracer. Vérifie quelques angles avec un inclinomètre.') }}</p>
            <button type="button" @click="$refs.cameraInput.click()" class="min-h-11 rounded-xl border border-accent/40 bg-accent/10 px-4 py-3 text-sm text-accent">{{ __('Prendre une photo du lieu') }}</button>
            <input x-ref="cameraInput" type="file" accept="image/*" capture="environment" @change="loadPhoto($event, 'photo')" class="hidden" aria-label="{{ __('Caméra arrière du lieu') }}">
            <p class="text-[11px] text-text-main/45">{{ __('Sur mobile, ce bouton demande la caméra arrière. Selon le navigateur, un choix caméra/fichiers peut apparaître. Le mode panorama de l’appareil photo n’est pas imposé par la PWA.') }}</p>
            <label class="grid gap-2 text-xs">{{ __('Type d’image') }}<select x-model="photoKind" @change="invalidatePhoto()" class="min-h-11 w-full min-w-0 rounded-lg border border-border bg-bg px-3 py-2"><option value="panorama">{{ __('Panorama complet · 360°') }}</option><option value="photo">{{ __('Photo classique · une partie de l’horizon') }}</option></select></label>
            <label class="grid gap-2 text-xs">{{ __('Ou choisir une photo / un panorama (20 Mo max.)') }}<input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" @change="loadPhoto($event, photoKind)" class="w-full min-w-0 rounded-lg border border-border p-2 text-xs"></label>
            <p x-show="photoError" x-cloak x-text="photoError" class="text-xs text-red-300" role="alert"></p>
            <div x-show="photo" x-cloak class="grid gap-4">
                <label x-show="photoKind === 'photo'" class="grid gap-2 text-xs">{{ __('Champ horizontal de la photo (°)') }}<input type="number" min="1" max="159.9" step="0.1" x-model.number="photoHorizontal" @input="invalidatePhoto()" :disabled="!photo || photoKind !== 'photo'" placeholder="{{ __('Champ de l’objectif utilisé') }}" class="min-h-11 w-full min-w-0 rounded-lg border border-border bg-bg px-3 py-2"><span class="text-text-main/50">{{ __('Ce champ doit correspondre à l’objectif et au zoom utilisés. Il n’est pas déduit de la prise de vue ; une valeur approximative donnera des angles approximatifs. Objectif classique, sans fisheye.') }}</span></label>
                <button type="button" @click="$dispatch('horizon-pick-reference')" class="min-h-11 rounded-xl border border-accent/40 px-3 py-2 text-xs text-accent">{{ __('Choisir un repère sur la carte') }}</button>
                <label class="grid gap-2 text-xs">{{ __('Azimut du repère (° depuis le nord)') }}<input type="number" min="0" max="359.9999999" step="any" x-model.number="referenceBearing" @input="invalidatePhoto()" :disabled="!photo" class="min-h-11 w-full min-w-0 rounded-lg border border-border bg-bg px-3 py-2" placeholder="{{ __('Rempli depuis la carte, ou saisi à la main') }}"></label>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="photoAction = 'horizon'" :aria-pressed="photoAction === 'horizon'" class="min-h-11 rounded-lg border border-border px-3 py-2 text-xs">{{ __('1. Désigner l’horizontale 0°') }}</button>
                    <button type="button" @click="photoAction = 'reference'" :disabled="!hasReference || !validPhotoField" :aria-pressed="photoAction === 'reference'" class="min-h-11 rounded-lg border border-border px-3 py-2 text-xs disabled:opacity-40">{{ __('2. Désigner le repère') }}</button>
                    <button type="button" @click="photoAction = 'trace'" :disabled="!photoCalibrated || !validPhotoField" :aria-pressed="photoAction === 'trace'" class="min-h-11 rounded-lg border border-border px-3 py-2 text-xs disabled:opacity-40">{{ __('3. Tracer les obstacles') }}</button>
                </div>
                <p class="text-xs text-text-main/60">{{ __('Appuie dans l’image ci-dessous selon l’étape choisie. Les points tracés apparaissent dans le graphique et le tableau du masque.') }}</p>
                <img x-ref="photoPreview" :src="photo || null" @click="markPhoto($event)" x-on:error="photoError = @js(__('Impossible d’afficher cette photo.'))" alt="{{ __('Photo du lieu pour le calage et le tracé des obstacles') }}" class="w-full cursor-crosshair rounded-lg border border-border">
                <p x-show="photoNotice" x-text="photoNotice" class="text-xs text-accent" role="status"></p>
            </div>
            <div x-show="photo" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <label class="grid gap-2 text-xs"><span x-text="photoKind === 'panorama' ? @js(__('Direction du bord gauche (°)')) : @js(__('Direction au centre (°)'))"></span><input type="number" min="0" max="359.9" step="0.1" x-model.number="photoBearing" :disabled="!photo" class="min-h-11 w-full min-w-0 rounded-lg border border-border bg-bg px-3 py-2"></label>
                <label class="grid gap-2 text-xs">{{ __('Position de l’horizon (% depuis le haut)') }}<input type="number" min="0" max="100" step="0.1" x-model.number="photoHorizon" @input="invalidatePhoto()" :disabled="!photo" class="min-h-11 w-full min-w-0 rounded-lg border border-border bg-bg px-3 py-2"></label>
                <label x-show="photoKind === 'panorama'" class="grid gap-2 text-xs">{{ __('Étendue verticale de l’image (°)') }}<input type="number" min="1" max="180" step="0.1" x-model.number="photoVertical" @input="invalidatePhoto()" :disabled="!photo || photoKind !== 'panorama'" class="min-h-11 w-full min-w-0 rounded-lg border border-border bg-bg px-3 py-2"></label>
                <button type="button" @click="removePhoto()" class="min-h-11 w-fit text-xs text-accent">{{ __('Retirer le fond photo') }}</button>
            </div>
            <p class="text-[11px] text-text-main/45">{{ __('L’image reste sur cet appareil, le temps de cette édition. Seuls les points du masque sont enregistrés dans le lieu.') }}</p>
        </div>
    </details>
    <details class="mt-3 rounded-xl border border-border p-4">
        <summary class="cursor-pointer text-sm text-text-main/65">{{ __('Import, export et JSON avancé') }}</summary>
        <div class="mt-4 grid gap-3">
            <div class="flex flex-wrap gap-3">
                <button type="button" @click="exportMask()" class="min-h-11 rounded-lg border border-border px-3 py-2 text-xs">{{ __('Exporter le masque') }}</button>
                <label class="grid gap-2 text-xs">{{ __('Importer un masque JSON') }}<input type="file" accept="application/json,.json" @change="importMask($event)" class="w-full min-w-0 text-xs"></label>
            </div>
            <label class="grid gap-2 text-xs">{{ __('JSON azimut → hauteur') }}<textarea rows="5" x-model="json" @change="applyJson()" class="w-full rounded-xl border border-border bg-bg p-3 font-[var(--font-mono)] text-xs" placeholder='{"0":25,"90":10,"180":5,"270":30}'></textarea></label>
            <button type="button" @click="applyJson()" class="min-h-11 w-fit text-xs text-accent">{{ __('Appliquer le JSON au graphique') }}</button>
        </div>
    </details>
</section>
