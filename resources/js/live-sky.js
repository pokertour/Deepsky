import {CONSTELLATIONS, STARS} from './sky-catalog.js';
import { compassPhrase, intlLocale, t } from './i18n.js';

/**
 * Ciel en direct de la page de connexion : étoiles, constellations, Lune et
 * cibles déjà photographiées, positionnées pour l'heure et le lieu réels
 * (projection stéréographique centrée sur le zénith, nord en haut, est à gauche).
 */
const RAD = Math.PI / 180;
const WARM_STARS = new Set(['betelgeuse', 'antares', 'aldebaran', 'arcturus', 'pollux', 'kochab', 'mirach', 'menkar']);

const normalizeDegrees = (degrees) => ((degrees % 360) + 360) % 360;

export function julianDate(date) {
    return date.getTime() / 86400000 + 2440587.5;
}

export function localSiderealDegrees(date, longitude) {
    const days = julianDate(date) - 2451545.0;
    return normalizeDegrees(280.46061837 + 360.98564736629 * days + longitude);
}

export function equatorialToHorizontal(raDegrees, decDegrees, latitude, lstDegrees) {
    const hourAngle = (lstDegrees - raDegrees) * RAD;
    const dec = decDegrees * RAD;
    const lat = latitude * RAD;
    const altitude = Math.asin(Math.sin(lat) * Math.sin(dec) + Math.cos(lat) * Math.cos(dec) * Math.cos(hourAngle));
    const azimuth = Math.atan2(
        -Math.cos(dec) * Math.sin(hourAngle),
        Math.sin(dec) * Math.cos(lat) - Math.cos(dec) * Math.sin(lat) * Math.cos(hourAngle),
    );
    return {altitude: altitude / RAD, azimuth: normalizeDegrees(azimuth / RAD)};
}

function eclipticToEquatorial(longitude, latitude, obliquity) {
    const l = longitude * RAD;
    const b = latitude * RAD;
    const e = obliquity * RAD;
    return {
        ra: normalizeDegrees(Math.atan2(Math.sin(l) * Math.cos(e) - Math.tan(b) * Math.sin(e), Math.cos(l)) / RAD),
        dec: Math.asin(Math.sin(b) * Math.cos(e) + Math.cos(b) * Math.sin(e) * Math.sin(l)) / RAD,
    };
}

export function sunPosition(date) {
    const days = julianDate(date) - 2451545.0;
    const meanAnomaly = (357.528 + 0.9856003 * days) * RAD;
    const longitude = normalizeDegrees(280.46 + 0.9856474 * days + 1.915 * Math.sin(meanAnomaly) + 0.02 * Math.sin(2 * meanAnomaly));
    return {longitude, ...eclipticToEquatorial(longitude, 0, 23.439 - 0.0000004 * days)};
}

export function moonPosition(date) {
    const days = julianDate(date) - 2451545.0;
    const meanAnomaly = (134.963 + 13.064993 * days) * RAD;
    const longitude = normalizeDegrees(218.316 + 13.176396 * days + 6.289 * Math.sin(meanAnomaly));
    const latitude = 5.128 * Math.sin((93.272 + 13.22935 * days) * RAD);
    const elongation = normalizeDegrees(longitude - sunPosition(date).longitude);
    const phaseAngle = Math.acos(Math.cos(latitude * RAD) * Math.cos(elongation * RAD));
    return {
        ...eclipticToEquatorial(longitude, latitude, 23.439 - 0.0000004 * days),
        illumination: (1 - Math.cos(phaseAngle)) / 2,
        waxing: elongation < 180,
    };
}

// Éléments orbitaux képlériens approchés (JPL, Standish) : valeur J2000 puis variation par siècle.
const PLANET_ELEMENTS = {
    mercury: [[0.38709927, 0.20563593, 7.00497902, 252.2503235, 77.45779628, 48.33076593], [0.00000037, 0.00001906, -0.00594749, 149472.67411175, 0.16047689, -0.12534081]],
    venus: [[0.72333566, 0.00677672, 3.39467605, 181.9790995, 131.60246718, 76.67984255], [0.0000039, -0.00004107, -0.0007889, 58517.81538729, 0.00268329, -0.27769418]],
    earth: [[1.00000261, 0.01671123, -0.00001531, 100.46457166, 102.93768193, 0], [0.00000562, -0.00004392, -0.01294668, 35999.37244981, 0.32327364, 0]],
    mars: [[1.52371034, 0.0933941, 1.84969142, -4.55343205, -23.94362959, 49.55953891], [0.00001847, 0.00007882, -0.00813131, 19140.30268499, 0.44441088, -0.29257343]],
    jupiter: [[5.202887, 0.04838624, 1.30439695, 34.39644051, 14.72847983, 100.47390909], [-0.00011607, -0.00013253, -0.00183714, 3034.74612775, 0.21252668, 0.20469106]],
    saturn: [[9.53667594, 0.05386179, 2.48599187, 49.95424423, 92.59887831, 113.66242448], [-0.0012506, -0.00050991, 0.00193609, 1222.49362201, -0.41897216, -0.28867794]],
};

export const PLANETS = [
    {key: 'mercury', name: t('Mercure'), magnitude: 0, color: 'rgb(214, 205, 190)'},
    {key: 'venus', name: t('Vénus'), magnitude: -4, color: 'rgb(255, 248, 225)'},
    {key: 'mars', name: t('Mars'), magnitude: 0.5, color: 'rgb(255, 150, 110)'},
    {key: 'jupiter', name: t('Jupiter'), magnitude: -2.2, color: 'rgb(255, 236, 205)'},
    {key: 'saturn', name: t('Saturne'), magnitude: 0.6, color: 'rgb(240, 215, 160)'},
];

function heliocentricPosition(key, centuries) {
    const [base, rate] = PLANET_ELEMENTS[key];
    const [a, e, inclination, meanLongitude, perihelion, node] = base.map((value, i) => value + rate[i] * centuries);
    const argument = (perihelion - node) * RAD;
    const meanAnomaly = normalizeDegrees(meanLongitude - perihelion) * RAD;
    let eccentricAnomaly = meanAnomaly;
    for (let i = 0; i < 8; i++) {
        eccentricAnomaly -= (eccentricAnomaly - e * Math.sin(eccentricAnomaly) - meanAnomaly) / (1 - e * Math.cos(eccentricAnomaly));
    }
    const xOrbit = a * (Math.cos(eccentricAnomaly) - e);
    const yOrbit = a * Math.sqrt(1 - e * e) * Math.sin(eccentricAnomaly);
    const [cw, sw, cn, sn, ci, si] = [Math.cos(argument), Math.sin(argument), Math.cos(node * RAD), Math.sin(node * RAD), Math.cos(inclination * RAD), Math.sin(inclination * RAD)];
    return {
        x: (cw * cn - sw * sn * ci) * xOrbit + (-sw * cn - cw * sn * ci) * yOrbit,
        y: (cw * sn + sw * cn * ci) * xOrbit + (-sw * sn + cw * cn * ci) * yOrbit,
        z: sw * si * xOrbit + cw * si * yOrbit,
    };
}

export function planetPosition(key, date) {
    const centuries = (julianDate(date) - 2451545.0) / 36525;
    const planet = heliocentricPosition(key, centuries);
    const earth = heliocentricPosition('earth', centuries);
    const [x, y, z] = [planet.x - earth.x, planet.y - earth.y, planet.z - earth.z];
    const obliquity = 23.43928 * RAD;
    const yEquatorial = y * Math.cos(obliquity) - z * Math.sin(obliquity);
    const zEquatorial = y * Math.sin(obliquity) + z * Math.cos(obliquity);
    return {
        ra: normalizeDegrees(Math.atan2(yEquatorial, x) / RAD),
        dec: Math.atan2(zEquatorial, Math.hypot(x, yEquatorial)) / RAD,
    };
}

// Pôle nord galactique et longitude du pôle céleste (J2000).
export function galacticToEquatorial(longitude, latitude) {
    const [poleRa, poleDec, celestialPoleLongitude] = [192.85948 * RAD, 27.12825 * RAD, 122.93192 * RAD];
    const l = longitude * RAD;
    const b = latitude * RAD;
    const dec = Math.asin(Math.sin(poleDec) * Math.sin(b) + Math.cos(poleDec) * Math.cos(b) * Math.cos(celestialPoleLongitude - l));
    const ra = poleRa + Math.atan2(Math.cos(b) * Math.sin(celestialPoleLongitude - l), Math.cos(poleDec) * Math.sin(b) - Math.sin(poleDec) * Math.cos(b) * Math.cos(celestialPoleLongitude - l));
    return {ra: normalizeDegrees(ra / RAD), dec: dec / RAD};
}

function milkyWayPoints() {
    const points = [];
    for (let longitude = 0; longitude < 360; longitude += 3) {
        // Plus dense vers le centre galactique (Sagittaire) et le Cygne, plus ténue vers l'anticentre.
        const brightness = 0.35 + 0.65 * Math.pow((Math.cos(longitude * RAD) + 1) / 2, 1.5) + (Math.abs(longitude - 75) < 25 ? 0.25 : 0);
        for (const latitude of [-6, -2, 2, 6]) {
            points.push({...galacticToEquatorial(longitude, latitude + Math.sin(longitude * 0.37) * 1.5), brightness: brightness * (1 - Math.abs(latitude) / 10)});
        }
    }
    return points;
}

/**
 * Événements des prochaines 24 h (pas de 5 min) : Soleil, nuit noire, Lune,
 * planètes et cibles observables pendant la nuit (au-dessus de 15°).
 */
export function nightSummary(now, latitude, longitude, targets = []) {
    const stepMinutes = 5;
    const nowIndex = (12 * 60) / stepMinutes;
    const samples = [];
    for (let minutes = -12 * 60; minutes <= 24 * 60; minutes += stepMinutes) {
        const date = new Date(now.getTime() + minutes * 60000);
        const lst = localSiderealDegrees(date, longitude);
        const sun = sunPosition(date);
        const moon = moonPosition(date);
        samples.push({
            date,
            lst,
            sun: equatorialToHorizontal(sun.ra, sun.dec, latitude, lst).altitude,
            moon: equatorialToHorizontal(moon.ra, moon.dec, latitude, lst).altitude,
        });
    }

    const crossings = (key, threshold, rising) => {
        const found = [];
        for (let i = 1; i < samples.length; i++) {
            const [before, after] = [samples[i - 1][key], samples[i][key]];
            if (rising ? before < threshold && after >= threshold : before >= threshold && after < threshold) {
                const ratio = (threshold - before) / (after - before);
                found.push({index: i, date: new Date(samples[i - 1].date.getTime() + ratio * stepMinutes * 60000)});
            }
        }
        return found;
    };
    const firstAfter = (events, index) => events.find((event) => event.index > index) ?? null;
    const lastBefore = (events, index) => [...events].reverse().find((event) => event.index <= index) ?? null;

    // Nuit en cours si le Soleil est déjà couché, sinon la prochaine.
    const sunsets = crossings('sun', -0.833, false);
    const sunset = samples[nowIndex].sun < -0.833 ? lastBefore(sunsets, nowIndex) : firstAfter(sunsets, nowIndex);
    const from = sunset?.index ?? nowIndex;
    const sunrise = firstAfter(crossings('sun', -0.833, true), from);
    const darkStart = firstAfter(crossings('sun', -18, false), from);
    const darkEnd = darkStart ? firstAfter(crossings('sun', -18, true), darkStart.index) : null;
    const nauticalStart = firstAfter(crossings('sun', -12, false), from);
    const nauticalEnd = nauticalStart ? firstAfter(crossings('sun', -12, true), nauticalStart.index) : null;

    const windowStart = Math.max(nowIndex, nauticalStart?.index ?? nowIndex);
    const windowEnd = nauticalEnd?.index ?? samples.length - 1;
    const window = samples.slice(windowStart, windowEnd + 1).filter((_, i) => i % 3 === 0);
    const middle = window[Math.floor(window.length / 2)] ?? samples[nowIndex];

    const bestDuringNight = (positionAt) => window.reduce((best, sample) => {
        const {ra, dec} = positionAt(sample.date);
        const altitude = equatorialToHorizontal(ra, dec, latitude, sample.lst).altitude;
        return altitude > best.altitude ? {altitude, date: sample.date} : best;
    }, {altitude: -90, date: null});

    const moon = moonPosition(middle.date);
    const moonRises = crossings('moon', 0, true);
    const moonUp = samples[nowIndex].moon > 0;

    return {
        sunset: sunset?.date ?? null,
        sunrise: sunrise?.date ?? null,
        darkStart: darkStart?.date ?? null,
        darkEnd: darkEnd?.date ?? null,
        alreadyDark: darkStart !== null && darkStart.index <= nowIndex && (darkEnd === null || darkEnd.index > nowIndex),
        moon: {
            illumination: moon.illumination,
            waxing: moon.waxing,
            rise: (moonUp ? lastBefore(moonRises, nowIndex) : firstAfter(moonRises, nowIndex))?.date ?? null,
            set: firstAfter(crossings('moon', 0, false), nowIndex)?.date ?? null,
        },
        planets: PLANETS
            .map((planet) => ({...planet, ...bestDuringNight((date) => planetPosition(planet.key, date))}))
            .filter((planet) => planet.altitude > 10)
            .sort((a, b) => a.magnitude - b.magnitude),
        targets: targets
            .map((target) => ({...target, ...bestDuringNight(() => target)}))
            .filter((target) => target.altitude > 15)
            .sort((a, b) => b.altitude - a.altitude),
    };
}

export function twilightPhase(sunAltitude) {
    if (sunAltitude > 0) return t('Jour');
    if (sunAltitude > -6) return t('Crépuscule civil');
    if (sunAltitude > -12) return t('Crépuscule nautique');
    if (sunAltitude > -18) return t('Crépuscule astronomique');
    return t('Nuit noire');
}

function angularDistance(ra1, dec1, ra2, dec2) {
    const cosine = Math.sin(dec1 * RAD) * Math.sin(dec2 * RAD)
        + Math.cos(dec1 * RAD) * Math.cos(dec2 * RAD) * Math.cos((ra1 - ra2) * RAD);
    return Math.acos(Math.min(1, Math.max(-1, cosine))) / RAD;
}

export function zenithConstellation(date, latitude, longitude) {
    const lst = localSiderealDegrees(date, longitude);
    let best = null;
    for (const [name, lines] of CONSTELLATIONS) {
        const keys = [...new Set(lines.flat())];
        const ra = keys.reduce((sum, key) => sum + Math.cos(STARS[key][0] * 15 * RAD), 0);
        const raSin = keys.reduce((sum, key) => sum + Math.sin(STARS[key][0] * 15 * RAD), 0);
        const dec = keys.reduce((sum, key) => sum + STARS[key][1], 0) / keys.length;
        const distance = angularDistance(normalizeDegrees(Math.atan2(raSin, ra) / RAD), dec, lst, latitude);
        if (best === null || distance < best.distance) best = {name, distance};
    }
    return best.name;
}

function seededRandom(seed) {
    return () => {
        seed = (seed * 1664525 + 1013904223) % 4294967296;
        return seed / 4294967296;
    };
}

function backgroundStars(count, seed = 20260730) {
    const random = seededRandom(seed);
    return Array.from({length: count}, () => ({
        ra: random() * 360,
        dec: Math.asin(random() * 2 - 1) / RAD,
        magnitude: 4.2 + random() * 2.4,
        phase: random() * Math.PI * 2,
    }));
}

export function liveSky(config) {
    return {
        location: config.label,
        localTime: '',
        siderealTime: '',
        twilight: '',
        moonLabel: '',
        zenith: '',
        visibleTargets: 0,
        hover: null,
        offsetMinutes: 0,
        night: null,
        zoom: 1,
        phase: 'sky',
        focusNote: '',
        focusSelection: null,
        photoStyle: '',

        formatTime(date) {
            return date ? date.toLocaleTimeString(intlLocale(), {hour: '2-digit', minute: '2-digit', timeZone: config.timezone}) : '—';
        },

        init() {
            const canvas = this.$refs.sky;
            const context = canvas.getContext('2d');
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const faintStars = backgroundStars(900);
            // Étoiles plus faibles, révélées progressivement au zoom comme dans un instrument.
            const deepStars = backgroundStars(9000, 7331).map((star) => ({...star, magnitude: 6.4 + (star.magnitude - 4.2) * 0.75}));
            const milkyWay = milkyWayPoints();
            const glowSprite = document.createElement('canvas');
            glowSprite.width = glowSprite.height = 64;
            const spriteContext = glowSprite.getContext('2d');
            const spriteGradient = spriteContext.createRadialGradient(32, 32, 0, 32, 32, 32);
            spriteGradient.addColorStop(0, 'rgba(170, 190, 230, 0.55)');
            spriteGradient.addColorStop(1, 'rgba(170, 190, 230, 0)');
            spriteContext.fillStyle = spriteGradient;
            spriteContext.fillRect(0, 0, 64, 64);
            const namedStars = Object.entries(STARS).map(([key, [ra, dec, magnitude, name]]) => ({key, ra: ra * 15, dec, magnitude, name, phase: (ra * 7.3 + dec) % 6.28}));
            const targets = (config.targets ?? []).filter((target) => Number.isFinite(target.ra) && Number.isFinite(target.dec));
            for (const target of targets) {
                if (!config.detailed || !target.thumb) continue;
                target.image = new Image();
                target.image.decoding = 'async';
                target.image.src = target.thumb;
            }
            const meteors = [];
            const pointer = {x: 0, y: 0, tx: 0, ty: 0};
            const view = {panX: 0, panY: 0};
            const activePointers = new Map();
            let gesture = null;
            let suppressClick = false;
            const MAX_ZOOM = 8;
            let width = 0;
            let height = 0;
            let ratio = 1;
            let warpStart = null;
            let frame = null;
            const introStart = performance.now();
            const introDuration = reduceMotion || config.focus ? 0 : 2600;
            const introOffsetMinutes = 120;

            const resize = () => {
                ratio = Math.min(window.devicePixelRatio || 1, 2);
                width = canvas.clientWidth;
                height = canvas.clientHeight;
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
            };

            const skyDate = (now) => {
                const progress = introDuration ? Math.min(1, (now - introStart) / introDuration) : 1;
                const eased = 1 - Math.pow(1 - progress, 3);
                return new Date(Date.now() + Number(this.offsetMinutes) * 60000 - (1 - eased) * introOffsetMinutes * 60000);
            };

            const project = (altitude, azimuth) => {
                const radius = Math.hypot(width, height) / 2;
                const r = radius * this.zoom * Math.tan(((90 - altitude) / 2) * RAD);
                return {
                    x: width / 2 + view.panX + pointer.x - r * Math.sin(azimuth * RAD),
                    y: height / 2 + view.panY + pointer.y - r * Math.cos(azimuth * RAD),
                };
            };

            const updateInfo = () => {
                const now = new Date(Date.now() + Number(this.offsetMinutes) * 60000);
                const lst = localSiderealDegrees(now, config.longitude) / 15;
                const sun = sunPosition(now);
                const sunAltitude = equatorialToHorizontal(sun.ra, sun.dec, config.latitude, lst * 15).altitude;
                const moon = moonPosition(now);
                const moonAltitude = equatorialToHorizontal(moon.ra, moon.dec, config.latitude, lst * 15).altitude;
                this.localTime = this.formatTime(now);
                this.siderealTime = `${String(Math.floor(lst)).padStart(2, '0')}h${String(Math.floor((lst % 1) * 60)).padStart(2, '0')}`;
                this.twilight = `${twilightPhase(sunAltitude)} · ${t('Soleil')} ${Math.round(sunAltitude)}°`;
                this.moonLabel = t(moon.waxing ? 'Lune :percent % croissante' : 'Lune :percent % décroissante', {percent: Math.round(moon.illumination * 100)}) + (moonAltitude > 0 ? '' : ' · ' + t('couchée'));
                this.zenith = zenithConstellation(now, config.latitude, config.longitude);
                this.visibleTargets = targets.filter((target) => equatorialToHorizontal(target.ra, target.dec, config.latitude, lst * 15).altitude > 10).length;
            };

            const drawBackground = (sunAltitude) => {
                const twilight = Math.min(1, Math.max(0, (sunAltitude + 18) / 18));
                const gradient = context.createRadialGradient(width / 2, height / 2, 0, width / 2, height / 2, Math.hypot(width, height) / 2);
                gradient.addColorStop(0, `rgb(${5 + 10 * twilight}, ${8 + 22 * twilight}, ${16 + 40 * twilight})`);
                gradient.addColorStop(1, `rgb(${13 + 40 * twilight}, ${17 + 50 * twilight}, ${30 + 70 * twilight})`);
                context.fillStyle = gradient;
                context.fillRect(0, 0, width, height);
                return 1 - twilight * 0.75;
            };

            const drawStar = (x, y, magnitude, alpha, color) => {
                const size = Math.max(0.55, 3.0 - 0.5 * magnitude) * (1 + (this.zoom - 1) * 0.12);
                context.globalAlpha = alpha;
                context.fillStyle = color;
                context.beginPath();
                context.arc(x, y, size, 0, Math.PI * 2);
                context.fill();
                if (magnitude < 1.6) {
                    const glow = context.createRadialGradient(x, y, 0, x, y, size * 6);
                    glow.addColorStop(0, color.replace('rgb', 'rgba').replace(')', ', 0.35)'));
                    glow.addColorStop(1, 'rgba(0, 0, 0, 0)');
                    context.fillStyle = glow;
                    context.beginPath();
                    context.arc(x, y, size * 6, 0, Math.PI * 2);
                    context.fill();
                }
            };

            const drawMoon = (moon, position) => {
                const radius = 10;
                context.globalAlpha = 1;
                const glow = context.createRadialGradient(position.x, position.y, radius, position.x, position.y, radius * 7);
                glow.addColorStop(0, `rgba(230, 237, 243, ${0.18 * moon.illumination})`);
                glow.addColorStop(1, 'rgba(0, 0, 0, 0)');
                context.fillStyle = glow;
                context.beginPath();
                context.arc(position.x, position.y, radius * 7, 0, Math.PI * 2);
                context.fill();
                context.fillStyle = '#1c2330';
                context.beginPath();
                context.arc(position.x, position.y, radius, 0, Math.PI * 2);
                context.fill();
                const lit = '#e6edf3';
                const dark = '#1c2330';
                const litStart = moon.waxing ? -Math.PI / 2 : Math.PI / 2;
                context.fillStyle = lit;
                context.beginPath();
                context.arc(position.x, position.y, radius, litStart, litStart + Math.PI);
                context.fill();
                context.fillStyle = moon.illumination > 0.5 ? lit : dark;
                context.beginPath();
                context.ellipse(position.x, position.y, radius * Math.abs(1 - 2 * moon.illumination), radius, 0, 0, Math.PI * 2);
                context.fill();
            };

            const drawCardinals = () => {
                context.globalAlpha = 0.5;
                context.fillStyle = '#58a6ff';
                context.font = '600 11px "JetBrains Mono", monospace';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                for (const [label, azimuth] of [['N', 0], ['E', 90], ['S', 180], [t('O'), 270]]) {
                    const point = project(0, azimuth);
                    context.fillText(label, Math.min(width - 16, Math.max(16, point.x)), Math.min(height - 16, Math.max(16, point.y)));
                }
            };

            // —— Plongée : du ciel jusqu'à la photo, à sa position et son orientation astrométriques ——
            const focus = config.focus ?? null;
            let focusImage = null;
            let focusGeometry = null;
            let dive = null;
            if (focus?.image) {
                focusImage = new Image();
                focusImage.decoding = 'async';
                focusImage.src = focus.image;
            }

            const horizontalAt = (ra, dec, date) => equatorialToHorizontal(ra, dec, config.latitude, localSiderealDegrees(date, config.longitude));

            /**
             * Vecteurs écran (pixels par degré) du nord et de l'est célestes au centre de la photo.
             */
            const focusFrame = (date) => {
                const step = 0.05;
                const at = (ra, dec) => {
                    const {altitude, azimuth} = horizontalAt(ra, dec, date);
                    return project(altitude, azimuth);
                };
                const center = at(focus.ra, focus.dec);
                const north = at(focus.ra, Math.min(89.99, focus.dec + step));
                const east = at(focus.ra + step / Math.max(0.01, Math.cos(focus.dec * RAD)), focus.dec);
                return {
                    center,
                    north: {x: (north.x - center.x) / step, y: (north.y - center.y) / step},
                    east: {x: (east.x - center.x) / step, y: (east.y - center.y) / step},
                };
            };

            const recenterOnFocus = (date) => {
                const {center} = focusFrame(date);
                view.panX += width / 2 - center.x;
                view.panY += height / 2 - center.y;
            };

            const drawFocus = (date, time, warp) => {
                const frame = focusFrame(date);
                const pulse = reduceMotion ? 0 : (Math.sin(time * 3) + 1) * 3;
                if (focusImage?.complete && focusImage.naturalWidth) {
                    // Haut de lt('image orienté à « orientation » degrés à l')est du nord ; droite = ouest tourné d'autant.
                    const angle = focus.orientation * RAD;
                    const degreesPerPixel = focus.pixelScale / 3600;
                    const up = {x: Math.cos(angle) * frame.north.x + Math.sin(angle) * frame.east.x, y: Math.cos(angle) * frame.north.y + Math.sin(angle) * frame.east.y};
                    const right = {x: -Math.cos(angle) * frame.east.x + Math.sin(angle) * frame.north.x, y: -Math.cos(angle) * frame.east.y + Math.sin(angle) * frame.north.y};
                    const [a, b, c, d] = [right.x * degreesPerPixel, right.y * degreesPerPixel, -up.x * degreesPerPixel, -up.y * degreesPerPixel];
                    const screenWidth = Math.hypot(a, b) * focusImage.naturalWidth;
                    focusGeometry = {angle: Math.atan2(b, a), width: screenWidth, mirrored: a * d - b * c < 0, center: frame.center};
                    const opacity = Math.min(1, Math.max(0, (screenWidth - 30) / 220));
                    if (opacity > 0) {
                        context.save();
                        context.globalAlpha = opacity * (1 - warp);
                        context.transform(a, b, c, d, frame.center.x, frame.center.y);
                        context.drawImage(focusImage, -focusImage.naturalWidth / 2, -focusImage.naturalHeight / 2);
                        context.restore();
                    }
                }
                if (this.phase === 'sky' || (focusGeometry?.width ?? 0) < 120) {
                    context.globalAlpha = 0.9;
                    context.strokeStyle = '#58a6ff';
                    context.lineWidth = 1.5;
                    context.beginPath();
                    context.arc(frame.center.x, frame.center.y, 16 + pulse, 0, Math.PI * 2);
                    context.stroke();
                    context.fillStyle = '#58a6ff';
                    context.font = '600 12px "JetBrains Mono", monospace';
                    context.textAlign = 'center';
                    context.fillText(focus.label, frame.center.x, frame.center.y - 26 - pulse);
                    context.globalAlpha = 1;
                }
            };

            const render = (now) => {
                pointer.x += (pointer.tx - pointer.x) * 0.05;
                pointer.y += (pointer.ty - pointer.y) * 0.05;
                const date = skyDate(now);
                const lst = localSiderealDegrees(date, config.longitude);
                const sun = sunPosition(date);
                const sunAltitude = equatorialToHorizontal(sun.ra, sun.dec, config.latitude, lst).altitude;
                const visibility = drawBackground(sunAltitude);
                if (sunAltitude > -3) {
                    const sunPoint = project(sunAltitude, equatorialToHorizontal(sun.ra, sun.dec, config.latitude, lst).azimuth);
                    const halo = context.createRadialGradient(sunPoint.x, sunPoint.y, 0, sunPoint.x, sunPoint.y, 220);
                    halo.addColorStop(0, 'rgba(255, 244, 214, 0.95)');
                    halo.addColorStop(0.06, 'rgba(255, 220, 160, 0.55)');
                    halo.addColorStop(1, 'rgba(255, 200, 120, 0)');
                    context.globalAlpha = Math.min(1, (sunAltitude + 3) / 6);
                    context.fillStyle = halo;
                    context.fillRect(sunPoint.x - 220, sunPoint.y - 220, 440, 440);
                    context.globalAlpha = 1;
                }
                const time = now / 1000;
                const warp = warpStart === null ? 0 : Math.min(1, (now - warpStart) / 650);

                context.globalCompositeOperation = 'lighter';
                const blobSize = Math.hypot(width, height) * 0.07 * this.zoom;
                for (const point of milkyWay) {
                    const {altitude, azimuth} = equatorialToHorizontal(point.ra, point.dec, config.latitude, lst);
                    if (altitude < -5) continue;
                    const position = project(altitude, azimuth);
                    context.globalAlpha = 0.11 * point.brightness * visibility * (1 - warp) * Math.min(1, (altitude + 5) / 20);
                    context.drawImage(glowSprite, position.x - blobSize / 2, position.y - blobSize / 2, blobSize, blobSize);
                }
                context.globalCompositeOperation = 'source-over';

                for (const star of faintStars) {
                    const {altitude, azimuth} = equatorialToHorizontal(star.ra, star.dec, config.latitude, lst);
                    if (altitude < 0) continue;
                    const point = project(altitude, azimuth);
                    const twinkle = reduceMotion ? 1 : 0.75 + 0.25 * Math.sin(time * 1.7 + star.phase);
                    drawStar(point.x, point.y, star.magnitude, 0.8 * twinkle * visibility * Math.min(1, altitude / 8), 'rgb(201, 209, 217)');
                }

                const deepVisibility = Math.min(1, Math.max(0, (this.zoom - 1.3) / 2));
                if (deepVisibility > 0) {
                    for (const star of deepStars) {
                        const {altitude, azimuth} = equatorialToHorizontal(star.ra, star.dec, config.latitude, lst);
                        if (altitude < 0) continue;
                        const point = project(altitude, azimuth);
                        if (point.x < -4 || point.y < -4 || point.x > width + 4 || point.y > height + 4) continue;
                        drawStar(point.x, point.y, star.magnitude, 0.75 * deepVisibility * visibility, 'rgb(201, 209, 217)');
                    }
                }

                const positions = {};
                for (const star of namedStars) {
                    const {altitude, azimuth} = equatorialToHorizontal(star.ra, star.dec, config.latitude, lst);
                    if (altitude < -2) continue;
                    positions[star.key] = {...project(altitude, azimuth), altitude, star};
                }

                context.lineWidth = 1;
                context.textAlign = 'center';
                context.font = '500 10px "JetBrains Mono", monospace';
                for (const [name, lines] of CONSTELLATIONS) {
                    const drawn = [];
                    context.strokeStyle = 'rgba(88, 166, 255, 0.22)';
                    context.globalAlpha = visibility * (1 - warp);
                    context.beginPath();
                    for (const [from, to] of lines) {
                        if (!positions[from] || !positions[to]) continue;
                        context.moveTo(positions[from].x, positions[from].y);
                        context.lineTo(positions[to].x, positions[to].y);
                        drawn.push(positions[from], positions[to]);
                    }
                    context.stroke();
                    if (drawn.length >= 4) {
                        context.globalAlpha = 0.32 * visibility * (1 - warp);
                        context.fillStyle = '#8b949e';
                        context.fillText(name.toUpperCase(), drawn.reduce((s, p) => s + p.x, 0) / drawn.length, drawn.reduce((s, p) => s + p.y, 0) / drawn.length + 14);
                    }
                }

                for (const {x, y, altitude, star} of Object.values(positions)) {
                    const twinkle = reduceMotion ? 1 : 0.85 + 0.15 * Math.sin(time * 2.3 + star.phase);
                    const color = WARM_STARS.has(star.key) ? 'rgb(255, 196, 140)' : 'rgb(230, 237, 243)';
                    if (warp > 0) {
                        const dx = x - width / 2;
                        const dy = y - height / 2;
                        context.globalAlpha = 0.8;
                        context.strokeStyle = color;
                        context.lineWidth = Math.max(0.6, 2.4 - 0.4 * star.magnitude);
                        context.beginPath();
                        context.moveTo(x, y);
                        context.lineTo(x + dx * warp * 0.9, y + dy * warp * 0.9);
                        context.stroke();
                    }
                    drawStar(x, y, star.magnitude, twinkle * Math.min(1, (altitude + 2) / 6) * Math.max(visibility, star.magnitude < 1 ? 0.6 : 0), color);
                }

                const moon = moonPosition(date);
                const moonHorizontal = equatorialToHorizontal(moon.ra, moon.dec, config.latitude, lst);
                if (moonHorizontal.altitude > -1) drawMoon(moon, project(moonHorizontal.altitude, moonHorizontal.azimuth));

                context.font = '500 10px "JetBrains Mono", monospace';
                for (const planet of PLANETS) {
                    const {ra, dec} = planetPosition(planet.key, date);
                    const {altitude, azimuth} = equatorialToHorizontal(ra, dec, config.latitude, lst);
                    planet.visible = altitude > 0;
                    if (!planet.visible) continue;
                    const point = project(altitude, azimuth);
                    planet.screen = point;
                    drawStar(point.x, point.y, planet.magnitude, Math.max(visibility, planet.magnitude < -3 ? 0.8 : 0.35), planet.color);
                    context.globalAlpha = 0.55 * visibility * (1 - warp);
                    context.fillStyle = planet.color;
                    context.fillText(planet.name, point.x, point.y + 16);
                }

                context.font = '600 10px "JetBrains Mono", monospace';
                for (const target of targets) {
                    const {altitude, azimuth} = equatorialToHorizontal(target.ra, target.dec, config.latitude, lst);
                    target.visible = false;
                    // En plongée, l'objet visé a son propre repère : pas de doublon.
                    if (altitude < 0 || (config.focus && target.label === config.focus.label)) continue;
                    const point = project(altitude, azimuth);
                    if (target.image?.complete && target.image.naturalWidth && this.zoom > 1.8) {
                        // Échelle locale de la projection stéréographique, en pixels par degré.
                        const zenithDistance = (90 - altitude) * RAD;
                        const pixelsPerDegree = (Math.hypot(width, height) / 2) * this.zoom * 0.5 / Math.cos(zenithDistance / 2) ** 2 * RAD;
                        const size = Math.max(36, pixelsPerDegree * ((target.size ?? 30) / 60) * 1.4);
                        const aspect = target.image.naturalHeight / target.image.naturalWidth;
                        const [drawWidth, drawHeight] = aspect < 1 ? [size / aspect, size] : [size, size * aspect];
                        context.save();
                        context.globalAlpha = Math.min(1, (this.zoom - 1.8) / 1.5) * 0.9 * (1 - warp);
                        context.beginPath();
                        context.arc(point.x, point.y, size / 2, 0, Math.PI * 2);
                        context.clip();
                        context.drawImage(target.image, point.x - drawWidth / 2, point.y - drawHeight / 2, drawWidth, drawHeight);
                        context.restore();
                        target.photoRadius = size / 2;
                    } else {
                        target.photoRadius = 0;
                    }
                    const pulse = reduceMotion ? 0 : (Math.sin(time * 2 + target.ra) + 1) * 1.5;
                    context.globalAlpha = 0.75 * (1 - warp);
                    context.strokeStyle = '#58a6ff';
                    context.setLineDash([3, 3]);
                    context.beginPath();
                    context.arc(point.x, point.y, Math.max(7, target.photoRadius) + pulse, 0, Math.PI * 2);
                    context.stroke();
                    context.setLineDash([]);
                    context.fillStyle = '#58a6ff';
                    context.fillText(target.label, point.x, point.y - Math.max(14, target.photoRadius + 8) - pulse);
                    target.screen = point;
                    target.visible = true;
                }

                for (let i = meteors.length - 1; i >= 0; i--) {
                    const meteor = meteors[i];
                    meteor.life += 1 / 40;
                    if (meteor.life >= 1) {
                        meteors.splice(i, 1);
                        continue;
                    }
                    const headX = meteor.x + meteor.dx * meteor.life;
                    const headY = meteor.y + meteor.dy * meteor.life;
                    const trail = context.createLinearGradient(headX - meteor.dx * 0.25, headY - meteor.dy * 0.25, headX, headY);
                    trail.addColorStop(0, 'rgba(88, 166, 255, 0)');
                    trail.addColorStop(1, 'rgba(230, 237, 243, 0.9)');
                    context.globalAlpha = 1 - meteor.life;
                    context.strokeStyle = trail;
                    context.lineWidth = 1.5;
                    context.beginPath();
                    context.moveTo(headX - meteor.dx * 0.25, headY - meteor.dy * 0.25);
                    context.lineTo(headX, headY);
                    context.stroke();
                }

                if (focus) drawFocus(date, time, warp);
                drawCardinals();
                context.globalAlpha = 1;
                this.positions = positions;
            };

            const loop = (now) => {
                if (dive) {
                    const progress = Math.min(1, (now - dive.start) / dive.duration);
                    const eased = progress < 0.5 ? 4 * progress ** 3 : 1 - (-2 * progress + 2) ** 3 / 2;
                    this.zoom = Math.exp(Math.log(dive.zoomTo) * eased);
                    recenterOnFocus(skyDate(now));
                    if (progress >= 1) {
                        dive = null;
                        this.showPhoto();
                    }
                }
                render(now);
                frame = requestAnimationFrame(loop);
            };

            this.startDive = () => {
                if (!focusImage?.naturalWidth) return;
                const {north} = focusFrame(skyDate(performance.now()));
                const pixelsPerDegreeAtOne = Math.hypot(north.x, north.y) / this.zoom;
                const fieldWidth = focusImage.naturalWidth * focus.pixelScale / 3600;
                const fieldHeight = focusImage.naturalHeight * focus.pixelScale / 3600;
                const zoomTo = Math.min(width * 0.8 / (fieldWidth * pixelsPerDegreeAtOne), height * 0.72 / (fieldHeight * pixelsPerDegreeAtOne));
                this.phase = 'zoom';
                if (reduceMotion) {
                    this.zoom = Math.max(1, zoomTo);
                    recenterOnFocus(skyDate(performance.now()));
                    render(performance.now());
                    this.showPhoto();
                    return;
                }
                dive = {start: performance.now(), duration: 5200, zoomTo: Math.max(1, zoomTo)};
            };

            // Passage du canevas à la photo nette : on part de l'angle et de la taille vus dans le ciel.
            this.showPhoto = () => {
                const aspect = focusImage.naturalHeight / focusImage.naturalWidth;
                const fittedWidth = Math.min(window.innerWidth * 0.92, window.innerHeight * 0.72 / aspect);
                const geometry = focusGeometry ?? {angle: 0, width: fittedWidth, mirrored: false};
                this.photoStyle = `transform: rotate(${geometry.angle}rad) scale(${(geometry.mirrored ? -1 : 1) * geometry.width / fittedWidth}, ${geometry.width / fittedWidth}); opacity: 0.6`;
                this.phase = 'photo';
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    this.photoStyle = 'transform: none; opacity: 1';
                }));
            };

            this.replayDive = () => {
                this.phase = 'sky';
                this.focusSelection = null;
                this.zoom = 1;
                view.panX = 0;
                view.panY = 0;
                setTimeout(() => this.startDive(), reduceMotion ? 0 : 1800);
            };

            if (focus) {
                const now = new Date();
                const nowHorizontal = horizontalAt(focus.ra, focus.dec, now);
                const sun = sunPosition(now);
                const sunAltitude = horizontalAt(sun.ra, sun.dec, now).altitude;
                let bestMinutes = 0;
                if (nowHorizontal.altitude < 25 || sunAltitude > -6) {
                    let bestScore = -Infinity;
                    for (let minutes = 0; minutes <= 20 * 60; minutes += 10) {
                        const date = new Date(now.getTime() + minutes * 60000);
                        const sunThen = sunPosition(date);
                        const score = horizontalAt(focus.ra, focus.dec, date).altitude
                            - (horizontalAt(sunThen.ra, sunThen.dec, date).altitude > -12 ? 60 : 0) - minutes / 240;
                        if (score > bestScore) {
                            bestScore = score;
                            bestMinutes = minutes;
                        }
                    }
                }
                this.offsetMinutes = bestMinutes;
                const shown = horizontalAt(focus.ra, focus.dec, new Date(now.getTime() + bestMinutes * 60000));
                const place = t(':direction, à :altitude° au-dessus de l’horizon', {direction: compassPhrase(shown.azimuth), altitude: Math.round(shown.altitude)});
                this.focusNote = bestMinutes === 0
                    ? t(':target est là, :place.', {target: focus.label, place})
                    : t(':target est mal placé maintenant : voici le ciel à :time, :place.', {target: focus.label, time: this.formatTime(new Date(now.getTime() + bestMinutes * 60000)), place});
                const begin = () => setTimeout(() => this.startDive(), reduceMotion ? 0 : 2600);
                focusImage.complete && focusImage.naturalWidth ? begin() : focusImage.addEventListener('load', begin, {once: true});
            }

            this.shootingStar = () => {
                if (reduceMotion || meteors.length > 6) return;
                const angle = (200 + Math.random() * 50) * RAD;
                const length = 140 + Math.random() * 160;
                meteors.push({x: Math.random() * width, y: Math.random() * height * 0.6, dx: Math.cos(angle) * length, dy: -Math.sin(angle) * length, life: 0});
            };

            this.warp = () => new Promise((resolve) => {
                if (reduceMotion) return resolve();
                warpStart = performance.now();
                setTimeout(resolve, 520);
            });

            this.inspect = (event) => {
                const rect = canvas.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                if (!reduceMotion && this.zoom === 1 && !focus) {
                    pointer.tx = (x / width - 0.5) * -18;
                    pointer.ty = (y / height - 0.5) * -18;
                }
                let found = null;
                for (const target of targets) {
                    if (target.visible && Math.hypot(target.screen.x - x, target.screen.y - y) < Math.max(14, target.photoRadius ?? 0)) found = {label: `${target.label}${target.name ? ' — ' + target.name : ''} · ${t('photographiée')}`, x, y, url: target.url};
                }
                for (const planet of PLANETS) {
                    if (!found && planet.visible && Math.hypot(planet.screen.x - x, planet.screen.y - y) < 14) found = {label: `${planet.name} · ${t('planète')}`, x, y};
                }
                for (const position of Object.values(this.positions ?? {})) {
                    if (!found && position.star.name && Math.hypot(position.x - x, position.y - y) < 12) found = {label: position.star.name, x, y};
                }
                this.hover = found;
            };


            const clampPan = () => {
                const limit = (Math.hypot(width, height) / 2) * (this.zoom - 1);
                view.panX = Math.max(-limit, Math.min(limit, view.panX));
                view.panY = Math.max(-limit, Math.min(limit, view.panY));
            };

            // Zoom centré sur un point de l'écran : ce point reste sous le curseur (ou entre les doigts).
            const zoomAround = (factor, x = width / 2, y = height / 2) => {
                const next = Math.min(MAX_ZOOM, Math.max(1, this.zoom * factor));
                const ratio = next / this.zoom;
                view.panX = x - width / 2 - (x - width / 2 - view.panX) * ratio;
                view.panY = y - height / 2 - (y - height / 2 - view.panY) * ratio;
                this.zoom = next;
                clampPan();
                if (reduceMotion) render(performance.now());
            };

            const localPoint = (event) => {
                const rect = canvas.getBoundingClientRect();
                return {x: event.clientX - rect.left, y: event.clientY - rect.top};
            };

            this.zoomIn = () => zoomAround(1.5);
            this.zoomOut = () => zoomAround(1 / 1.5);
            this.resetView = () => {
                this.zoom = 1;
                view.panX = 0;
                view.panY = 0;
                if (reduceMotion) render(performance.now());
            };

            this.onWheel = (event) => {
                const {x, y} = localPoint(event);
                zoomAround(Math.exp(-event.deltaY * 0.0015), x, y);
            };

            this.onPointerDown = (event) => {
                activePointers.set(event.pointerId, localPoint(event));
                suppressClick = false;
                if (activePointers.size === 2) {
                    const [a, b] = [...activePointers.values()];
                    gesture = {type: 'pinch', distance: Math.hypot(a.x - b.x, a.y - b.y)};
                } else if (activePointers.size === 1 && this.zoom > 1) {
                    gesture = {type: 'pan', ...localPoint(event)};
                    canvas.setPointerCapture?.(event.pointerId);
                }
            };

            this.onPointerMove = (event) => {
                if (activePointers.has(event.pointerId)) activePointers.set(event.pointerId, localPoint(event));
                if (gesture?.type === 'pinch' && activePointers.size === 2) {
                    const [a, b] = [...activePointers.values()];
                    const distance = Math.hypot(a.x - b.x, a.y - b.y);
                    zoomAround(distance / gesture.distance, (a.x + b.x) / 2, (a.y + b.y) / 2);
                    gesture.distance = distance;
                    suppressClick = true;
                    return;
                }
                if (gesture?.type === 'pan') {
                    const point = localPoint(event);
                    if (Math.hypot(point.x - gesture.x, point.y - gesture.y) > 3) suppressClick = true;
                    view.panX += point.x - gesture.x;
                    view.panY += point.y - gesture.y;
                    gesture.x = point.x;
                    gesture.y = point.y;
                    clampPan();
                    this.hover = null;
                    if (reduceMotion) render(performance.now());
                    return;
                }
                this.inspect(event);
            };

            this.onPointerUp = (event) => {
                activePointers.delete(event.pointerId);
                if (activePointers.size < 2 && gesture?.type === 'pinch') gesture = null;
                if (activePointers.size === 0) gesture = null;
            };

            this.openHovered = () => {
                if (suppressClick) {
                    suppressClick = false;
                    return;
                }
                if (this.hover?.url) window.Livewire ? window.Livewire.navigate(this.hover.url) : window.location.assign(this.hover.url);
            };

            const updateNight = () => {
                if (config.detailed) this.night = nightSummary(new Date(), config.latitude, config.longitude, targets);
            };

            resize();
            updateInfo();
            updateNight();
            this.$watch('offsetMinutes', () => {
                updateInfo();
                if (reduceMotion) render(performance.now());
            });
            window.addEventListener('resize', resize);
            const infoTimer = setInterval(() => { updateInfo(); updateNight(); }, 60000);
            if (reduceMotion) {
                render(performance.now());
                const staticTimer = setInterval(() => render(performance.now()), 60000);
                this.destroy = () => { clearInterval(infoTimer); clearInterval(staticTimer); window.removeEventListener('resize', resize); };
            } else {
                frame = requestAnimationFrame(loop);
                this.destroy = () => { cancelAnimationFrame(frame); clearInterval(infoTimer); window.removeEventListener('resize', resize); };
            }
        },
    };
}
