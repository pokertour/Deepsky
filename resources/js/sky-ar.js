import {CONSTELLATIONS, STARS} from './sky-catalog.js';
import {equatorialToHorizontal, localSiderealDegrees, moonPosition, PLANETS, planetPosition} from './live-sky.js';
import { compassPhrase, t } from './i18n.js';

/**
 * Mode « Lève les yeux » : le téléphone devient une fenêtre sur le ciel réel.
 * L'orientation (boussole + gyroscope, repère W3C Est-Nord-Haut) donne la direction
 * visée par la caméra arrière ; le ciel est projeté comme par un objectif (gnomonique).
 */
const RAD = Math.PI / 180;

const dot = (a, b) => a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
const cross = (a, b) => [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
const normalize = (v) => {
    const length = Math.hypot(...v) || 1;
    return v.map((value) => value / length);
};

/**
 * Repère de la caméra : avant (dos du téléphone), haut et droite de l'écran, en Est-Nord-Haut.
 * alpha est compté depuis le nord dans le sens antihoraire (deviceorientationabsolute).
 */
export function cameraBasis(alpha, beta, gamma, screenAngle = 0) {
    const [a, b, g] = [alpha * RAD, beta * RAD, gamma * RAD];
    const [cA, sA, cB, sB, cG, sG] = [Math.cos(a), Math.sin(a), Math.cos(b), Math.sin(b), Math.cos(g), Math.sin(g)];
    const rotation = [
        [cA * cG - sA * sB * sG, -cB * sA, cG * sA * sB + cA * sG],
        [cG * sA + cA * sB * sG, cA * cB, sA * sG - cA * cG * sB],
        [-cB * sG, sB, cB * cG],
    ];
    const apply = (vector) => rotation.map((row) => dot(row, vector));
    const forward = normalize(apply([0, 0, -1]));
    const screenUpInDevice = [Math.sin(screenAngle * RAD), Math.cos(screenAngle * RAD), 0];
    const rawUp = apply(screenUpInDevice);
    const up = normalize(rawUp.map((value, index) => value - dot(rawUp, forward) * forward[index]));
    return {forward, up, right: cross(forward, up)};
}

export function horizontalToVector(altitude, azimuth) {
    return [Math.cos(altitude * RAD) * Math.sin(azimuth * RAD), Math.cos(altitude * RAD) * Math.cos(azimuth * RAD), Math.sin(altitude * RAD)];
}

export function pointingOf(basis) {
    const [east, north, up] = basis.forward;
    return {altitude: Math.asin(Math.max(-1, Math.min(1, up))) / RAD, azimuth: ((Math.atan2(east, north) / RAD) + 360) % 360};
}

/**
 * Coordonnées écran normalisées (focale = 1) ; null si le point est derrière la caméra.
 */
export function projectGnomonic(vector, basis) {
    const depth = dot(vector, basis.forward);
    if (depth <= 0.05) return null;
    return {x: dot(vector, basis.right) / depth, y: dot(vector, basis.up) / depth};
}

/**
 * Lissage des tremblements du capteur : moyenne pondérée des axes, ré-orthonormalisée.
 */
export function smoothBasis(previous, next, factor = 0.25) {
    if (!previous) return next;
    const mix = (a, b) => normalize(a.map((value, index) => value + (b[index] - value) * factor));
    const forward = mix(previous.forward, next.forward);
    const rawUp = mix(previous.up, next.up);
    const up = normalize(rawUp.map((value, index) => value - dot(rawUp, forward) * forward[index]));
    return {forward, up, right: cross(forward, up)};
}


export function skyAr(config) {
    return {
        supported: typeof window !== 'undefined' && 'DeviceOrientationEvent' in window && window.matchMedia('(pointer: coarse)').matches,
        active: false,
        cameraOn: false,
        status: '',
        guide: '',
        targetIndex: 0,
        hits: [],

        targets() {
            return (config.targets ?? []).filter((target) => Number.isFinite(target.ra) && Number.isFinite(target.dec));
        },

        async start() {
            this.status = '';
            try {
                // Safari iOS demande l'autorisation ; certains navigateurs répondent « prompt » sans bloquer.
                if (typeof DeviceOrientationEvent.requestPermission === 'function' && await DeviceOrientationEvent.requestPermission() === 'denied') {
                    this.status = t('L’accès à l’orientation du téléphone a été refusé.');
                    return;
                }
            } catch {
                this.status = t('L’accès à l’orientation du téléphone a été refusé.');
                return;
            }
            this.active = true;
            this.$nextTick(() => this.run());
        },

        async toggleCamera() {
            const video = this.$refs.camera;
            if (this.cameraOn) {
                video.srcObject?.getTracks().forEach((track) => track.stop());
                video.srcObject = null;
                this.cameraOn = false;
                return;
            }
            try {
                video.srcObject = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}, audio: false});
                await video.play();
                this.cameraOn = true;
            } catch {
                this.status = t('Caméra indisponible : le ciel reste dessiné sur fond noir.');
            }
        },

        stop() {
            this.cleanup?.();
            if (this.cameraOn) this.toggleCamera();
            this.active = false;
        },

        run() {
            const canvas = this.$refs.ar;
            const context = canvas.getContext('2d');
            const stars = Object.entries(STARS).map(([key, [ra, dec, magnitude, name]]) => ({key, ra: ra * 15, dec, magnitude, name}));
            const targets = this.targets();
            let basis = null;
            let reading = null;
            let width = 0;
            let height = 0;
            let frame = null;
            const verticalFov = 62;

            const resize = () => {
                const ratio = Math.min(window.devicePixelRatio || 1, 2);
                width = canvas.clientWidth;
                height = canvas.clientHeight;
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
            };

            const onOrientation = (event) => {
                const alpha = typeof event.webkitCompassHeading === 'number' ? 360 - event.webkitCompassHeading : event.alpha;
                if (alpha === null || event.beta === null) return;
                if (event.type === 'deviceorientation' && !event.absolute && typeof event.webkitCompassHeading !== 'number' && 'ondeviceorientationabsolute' in window) return;
                reading = {alpha, beta: event.beta, gamma: event.gamma ?? 0};
            };

            const render = () => {
                frame = requestAnimationFrame(render);
                // La vue plein écran peut s'afficher après le lancement : on suit sa taille réelle.
                if (canvas.clientWidth !== width || canvas.clientHeight !== height) resize();
                context.clearRect(0, 0, width, height);
                if (!this.cameraOn) {
                    context.fillStyle = '#05080f';
                    context.fillRect(0, 0, width, height);
                }
                if (!reading) {
                    this.guide = t('Pointe le téléphone vers le ciel…');
                    return;
                }
                basis = smoothBasis(basis, cameraBasis(reading.alpha, reading.beta, reading.gamma, screen.orientation?.angle ?? 0));
                const focal = (height / 2) / Math.tan((verticalFov / 2) * RAD);
                const date = new Date();
                const lst = localSiderealDegrees(date, config.longitude);
                const toScreen = (ra, dec) => {
                    const {altitude, azimuth} = equatorialToHorizontal(ra, dec, config.latitude, lst);
                    const point = projectGnomonic(horizontalToVector(altitude, azimuth), basis);
                    return point && {x: width / 2 + point.x * focal, y: height / 2 - point.y * focal, altitude};
                };
                const onScreen = (point) => point && point.x > -40 && point.x < width + 40 && point.y > -40 && point.y < height + 40;

                context.strokeStyle = 'rgba(88, 166, 255, 0.55)';
                context.fillStyle = 'rgba(88, 166, 255, 0.85)';
                context.lineWidth = 1.5;
                context.font = '600 12px "JetBrains Mono", monospace';
                context.textAlign = 'center';
                context.beginPath();
                let drawing = false;
                for (let azimuth = 0; azimuth <= 360; azimuth += 2) {
                    const point = projectGnomonic(horizontalToVector(0, azimuth), basis);
                    if (!point) { drawing = false; continue; }
                    const [x, y] = [width / 2 + point.x * focal, height / 2 - point.y * focal];
                    drawing ? context.lineTo(x, y) : context.moveTo(x, y);
                    drawing = true;
                }
                context.stroke();
                for (const [label, azimuth] of [['N', 0], ['NE', 45], ['E', 90], ['SE', 135], ['S', 180], [t('SO'), 225], [t('O'), 270], [t('NO'), 315]]) {
                    const point = projectGnomonic(horizontalToVector(0, azimuth), basis);
                    if (point) context.fillText(label, width / 2 + point.x * focal, height / 2 - point.y * focal - 8);
                }

                const positions = {};
                for (const star of stars) {
                    const point = toScreen(star.ra, star.dec);
                    if (point) positions[star.key] = point;
                }
                context.strokeStyle = 'rgba(88, 166, 255, 0.35)';
                context.lineWidth = 1;
                context.font = '500 11px "JetBrains Mono", monospace';
                for (const [name, lines] of CONSTELLATIONS) {
                    const drawn = [];
                    context.beginPath();
                    for (const [from, to] of lines) {
                        if (!positions[from] || !positions[to]) continue;
                        context.moveTo(positions[from].x, positions[from].y);
                        context.lineTo(positions[to].x, positions[to].y);
                        drawn.push(positions[from]);
                    }
                    context.stroke();
                    const visible = drawn.filter(onScreen);
                    if (visible.length >= 2) {
                        context.fillStyle = 'rgba(201, 209, 217, 0.55)';
                        context.fillText(name.toUpperCase(), visible.reduce((s, p) => s + p.x, 0) / visible.length, visible.reduce((s, p) => s + p.y, 0) / visible.length + 16);
                    }
                }
                for (const star of stars) {
                    const point = positions[star.key];
                    if (!onScreen(point)) continue;
                    context.fillStyle = 'rgba(240, 246, 252, 0.95)';
                    context.beginPath();
                    context.arc(point.x, point.y, Math.max(1.2, 3.6 - 0.6 * star.magnitude), 0, Math.PI * 2);
                    context.fill();
                    if (star.name && star.magnitude < 1.6) {
                        context.fillStyle = 'rgba(240, 246, 252, 0.7)';
                        context.fillText(star.name, point.x, point.y - 9);
                    }
                }

                const moon = moonPosition(date);
                const moonPoint = toScreen(moon.ra, moon.dec);
                if (onScreen(moonPoint)) {
                    context.fillStyle = '#e6edf3';
                    context.beginPath();
                    context.arc(moonPoint.x, moonPoint.y, 12, 0, Math.PI * 2);
                    context.fill();
                    context.fillText('Lune', moonPoint.x, moonPoint.y - 18);
                }
                for (const planet of PLANETS) {
                    const {ra, dec} = planetPosition(planet.key, date);
                    const point = toScreen(ra, dec);
                    if (!onScreen(point)) continue;
                    context.fillStyle = planet.color;
                    context.beginPath();
                    context.arc(point.x, point.y, 4, 0, Math.PI * 2);
                    context.fill();
                    context.fillText(planet.name, point.x, point.y - 10);
                }

                const hits = [];
                context.font = '700 13px "JetBrains Mono", monospace';
                targets.forEach((target, index) => {
                    const point = toScreen(target.ra, target.dec);
                    if (!onScreen(point)) return;
                    const selected = index === this.targetIndex;
                    context.strokeStyle = selected ? '#58a6ff' : 'rgba(88, 166, 255, 0.7)';
                    context.lineWidth = selected ? 3 : 2;
                    context.setLineDash([5, 4]);
                    context.beginPath();
                    context.arc(point.x, point.y, selected ? 22 : 16, 0, Math.PI * 2);
                    context.stroke();
                    context.setLineDash([]);
                    context.fillStyle = '#58a6ff';
                    context.fillText(target.label, point.x, point.y - (selected ? 30 : 24));
                    hits.push({x: point.x, y: point.y, url: target.url});
                });
                this.hits = hits;

                const selected = targets[this.targetIndex];
                if (selected) {
                    const {altitude, azimuth} = equatorialToHorizontal(selected.ra, selected.dec, config.latitude, lst);
                    const vector = horizontalToVector(altitude, azimuth);
                    const ahead = projectGnomonic(vector, basis);
                    const visible = ahead && Math.abs(ahead.x * focal) < width / 2 && Math.abs(ahead.y * focal) < height / 2;
                    if (altitude < 0) {
                        this.guide = t(':target est sous l’horizon, :direction.', {target: selected.label, direction: compassPhrase(azimuth)});
                    } else if (visible) {
                        this.guide = `${selected.label} est dans le viseur : touche-le pour voir la photo.`;
                    } else {
                        const angle = Math.atan2(-dot(vector, basis.up), dot(vector, basis.right));
                        const edge = Math.min(width, height) * 0.38;
                        const [x, y] = [width / 2 + Math.cos(angle) * edge, height / 2 + Math.sin(angle) * edge];
                        context.save();
                        context.translate(x, y);
                        context.rotate(angle);
                        context.fillStyle = '#58a6ff';
                        context.beginPath();
                        context.moveTo(18, 0);
                        context.lineTo(-10, -12);
                        context.lineTo(-4, 0);
                        context.lineTo(-10, 12);
                        context.closePath();
                        context.fill();
                        context.restore();
                        this.guide = t(':target : :direction, à :altitude° de hauteur. Suis la flèche.', {target: selected.label, direction: compassPhrase(azimuth), altitude: Math.round(altitude)});
                    }
                }
            };

            const listenAbsolute = 'ondeviceorientationabsolute' in window;
            window.addEventListener(listenAbsolute ? 'deviceorientationabsolute' : 'deviceorientation', onOrientation);
            window.addEventListener('resize', resize);
            resize();
            render();
            this.cleanup = () => {
                cancelAnimationFrame(frame);
                window.removeEventListener(listenAbsolute ? 'deviceorientationabsolute' : 'deviceorientation', onOrientation);
                window.removeEventListener('resize', resize);
            };
        },

        tap(event) {
            const rect = this.$refs.ar.getBoundingClientRect();
            const [x, y] = [event.clientX - rect.left, event.clientY - rect.top];
            const hit = this.hits.find((candidate) => Math.hypot(candidate.x - x, candidate.y - y) < 32);
            if (hit) window.location.assign(hit.url);
        },

        destroy() {
            this.stop();
        },
    };
}
