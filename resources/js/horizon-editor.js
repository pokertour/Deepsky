import {horizonLine, horizonX, horizonY, trajectorySegments} from './horizon-geometry.js';
import {photoDirection, alignPhotoToReference, normalizeBearing} from './photo-calibration.js';
import { t } from './i18n.js';

const round = (value) => Math.round(value * 10) / 10;

function parseMask(value) {
    const mask = JSON.parse(value || '{}');
    if (!mask || Array.isArray(mask) || typeof mask !== 'object' || Object.keys(mask).length > 720) throw new Error();
    if (Object.values(mask).some(altitude => typeof altitude !== 'number')) throw new Error();
    const points = Object.entries(mask).map(([azimuth, altitude]) => ({azimuth: Number(azimuth), altitude}));
    if (points.some(point => !Number.isFinite(point.azimuth) || !Number.isFinite(point.altitude) || point.azimuth < 0 || point.azimuth >= 360 || point.altitude < 0 || point.altitude > 90) || new Set(points.map(point => point.azimuth)).size !== points.length) throw new Error();
    return points.sort((a, b) => a.azimuth - b.azimuth).map((point, id) => ({...point, id}));
}

export function registerHorizonTools(Alpine) {
    Alpine.data('horizonEditor', () => {
        let nextId = 0;
        let draggedId = null;
        let photoUrl;
        let form;
        let submitHandler;
        let photoReadId = 0;

        return {
            points: [], json: '', error: '', selectedId: null,
            photo: '', photoError: '', photoBearing: 0, photoHorizon: 75, photoVertical: 90,
            photoKind: 'panorama', photoHorizontal: '', photoAspect: 1,
            photoAction: 'reference', referenceBearing: '', photoCalibrated: false, photoNotice: '',
            init() {
                this.json = this.$root.dataset.mask || '';
                try { this.points = parseMask(this.json); nextId = this.points.length; }
                catch { this.error = t('Masque existant invalide. Corrige le JSON dans les options avancées ou repars d’un horizon libre.'); }
                form = this.$root.closest('form');
                submitHandler = (event) => {
                    if (!this.error) this.applyJson();
                    if (this.error) { event.preventDefault(); this.$nextTick(() => this.$refs.error.focus()); }
                };
                form?.addEventListener('submit', submitHandler);
            },
            get line() { return horizonLine(this.points); },
            get area() { return `40,204 ${this.line} 760,204`; },
            get photoHeight() { return Number(this.photoVertical) * 2; },
            get photoTop() { return 204 - this.photoHeight * Number(this.photoHorizon) / 100; },
            get photoLeft() { return 40 + Number(this.photoBearing) * 2; },
            get validPhotoField() { return this.photoKind === 'panorama' || (this.photoHorizontal !== '' && Number(this.photoHorizontal) > 0 && Number(this.photoHorizontal) < 160); },
            get hasReference() { return this.referenceBearing !== '' && Number.isFinite(Number(this.referenceBearing)) && Number(this.referenceBearing) >= 0 && Number(this.referenceBearing) < 360; },
            get calibration() { return {kind: this.photoKind, horizontal: Number(this.photoHorizontal), vertical: Number(this.photoVertical), horizon: Number(this.photoHorizon), aspect: this.photoAspect, bearing: Number(this.photoBearing)}; },
            receiveReference(bearing) {
                this.referenceBearing = bearing ?? '';
                this.photoCalibrated = false;
                this.photoAction = 'reference';
                this.photoNotice = bearing === null ? t('Le lieu a changé : recale l’image sur un repère.') : t('Repère à :bearing°. Appuie sur ce même repère dans la photo.', {bearing: Number(bearing).toFixed(1)});
                if (bearing !== null && this.photo) this.$refs.photoPreview.scrollIntoView({behavior: 'smooth', block: 'center'});
            },
            invalidatePhoto() { this.photoCalibrated = false; this.photoAction = 'reference'; this.photoNotice = t('Les réglages ont changé : recale l’image sur le repère.'); },
            markPhoto(event) {
                const rect = this.$refs.photoPreview.getBoundingClientRect();
                const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
                const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));
                if (this.photoAction === 'horizon') {
                    this.photoHorizon = round(y * 100);
                    this.invalidatePhoto();
                    this.photoNotice = t('Ligne horizontale à 0° définie. Cale maintenant le repère.');
                    return;
                }
                if (!this.validPhotoField) { this.photoNotice = t('Renseigne le champ horizontal de cette photo avant le calage.'); return; }
                if (this.photoAction === 'reference') {
                    if (!this.hasReference) { this.photoNotice = t('Choisis d’abord un repère sur la carte ou renseigne son azimut.'); return; }
                    this.photoBearing = normalizeBearing(round(alignPhotoToReference(x, y, this.calibration, Number(this.referenceBearing))));
                    this.photoCalibrated = true; this.photoAction = 'trace';
                    this.photoNotice = t('Image calée. Appuie sur le sommet des obstacles pour ajouter des points au masque.');
                    return;
                }
                if (!this.photoCalibrated) { this.photoNotice = t('Cale d’abord l’image sur un repère.'); return; }
                const point = photoDirection(x, y, this.calibration);
                if (point.altitude < 0 || point.altitude > 90) { this.photoNotice = t('Choisis un obstacle entre l’horizontale et le zénith.'); return; }
                const azimuth = Math.min(359.9, round(point.azimuth));
                const existing = this.points.find(point => Number(point.azimuth) === azimuth);
                if (existing) existing.altitude = round(point.altitude);
                else if (this.points.length < 720) this.points.push({id: nextId++, azimuth, altitude: round(point.altitude)});
                this.points.sort((a, b) => a.azimuth - b.azimuth); this.sync();
                this.photoNotice = t('Point ajouté : azimut :azimuth° · hauteur :altitude°.', {azimuth, altitude: round(point.altitude)});
            },
            x: horizonX, y: horizonY,
            renderPoints() {
                const nodes = this.points.map(point => {
                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    for (const [name, value] of Object.entries({cx: horizonX(point.azimuth), cy: horizonY(point.altitude), r: 9, fill: this.selectedId === point.id ? '#ffffff' : '#f0c674', stroke: '#0d1117', 'stroke-width': 2, 'data-point-id': point.id})) circle.setAttribute(name, value);
                    circle.style.cursor = 'grab';
                    return circle;
                });
                this.$refs.points.replaceChildren(...nodes);
            },
            sync() {
                const invalid = this.points.some(point => point.azimuth === '' || point.altitude === '' || !Number.isFinite(Number(point.azimuth)) || !Number.isFinite(Number(point.altitude)) || point.azimuth < 0 || point.azimuth >= 360 || point.altitude < 0 || point.altitude > 90);
                const duplicate = new Set(this.points.map(point => Number(point.azimuth))).size !== this.points.length;
                this.error = invalid ? t('Azimut : 0 à moins de 360°. Hauteur : 0 à 90°.') : (duplicate ? t('Chaque direction doit être unique.') : '');
                this.json = this.points.length ? JSON.stringify(Object.fromEntries(this.points.map(point => [point.azimuth, point.altitude])), null, 2) : '';
            },
            preset() {
                this.points = Array.from({length: 8}, (_, index) => ({id: nextId++, azimuth: index * 45, altitude: 0}));
                this.selectedId = null; this.sync();
            },
            clear() { this.points = []; this.selectedId = null; this.sync(); },
            add() {
                if (this.points.length >= 720) return;
                let azimuth = 0;
                while (this.points.some(point => Number(point.azimuth) === azimuth) && azimuth < 360) azimuth += 1;
                if (azimuth >= 360) return;
                const point = {id: nextId++, azimuth, altitude: 0};
                this.points.push(point); this.selectedId = point.id; this.sync();
            },
            remove(id) { this.points = this.points.filter(point => point.id !== id); this.selectedId = null; this.sync(); },
            position(event) {
                const svg = this.$refs.graph;
                const point = svg.createSVGPoint(); point.x = event.clientX; point.y = event.clientY;
                const position = point.matrixTransform(svg.getScreenCTM().inverse());
                return {azimuth: Math.min(359.9, Math.max(0, round((position.x - 40) / 2))), altitude: Math.min(90, Math.max(0, round((204 - position.y) / 2)))};
            },
            begin(event) {
                if (event.button !== 0 || this.error) return;
                const targetId = event.target.dataset.pointId;
                const position = this.position(event);
                const matrix = this.$refs.graph.getScreenCTM();
                let point = targetId !== undefined ? this.points.find(point => point.id === Number(targetId)) : this.points.find(point => Math.hypot((point.azimuth - position.azimuth) * 2 * matrix.a, (point.altitude - position.altitude) * 2 * matrix.d) < 16);
                if (!point) {
                    if (this.points.length >= 720) return;
                    point = {id: nextId++, ...position}; this.points.push(point);
                }
                this.selectedId = point.id; draggedId = point.id;
                this.$refs.graph.setPointerCapture(event.pointerId);
                this.move(event); this.sync();
            },
            move(event) {
                if (draggedId === null) return;
                const point = this.points.find(point => point.id === draggedId);
                const position = this.position(event);
                if (this.points.some(other => other.id !== point.id && Number(other.azimuth) === position.azimuth)) return;
                Object.assign(point, position); this.sync();
            },
            end() { draggedId = null; this.points.sort((a, b) => a.azimuth - b.azimuth); },
            applyJson() {
                try { this.points = parseMask(this.json).map(point => ({...point, id: nextId++})); this.selectedId = null; this.sync(); }
                catch { this.error = t('JSON attendu : un objet azimut → hauteur, avec des valeurs numériques dans les limites indiquées.'); }
            },
            exportMask() {
                if (this.error) return;
                const url = URL.createObjectURL(new Blob([this.json || '{}'], {type: 'application/json'}));
                const link = document.createElement('a'); link.href = url; link.download = 'horizon-deepsky.json'; link.click();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            },
            async importMask(event) {
                const file = event.target.files?.[0]; if (!file) return;
                if (file.size > 100000) { this.error = t('Le fichier JSON dépasse 100 Ko.'); return; }
                try { this.json = await file.text(); this.applyJson(); }
                catch { this.error = t('Impossible de lire ce fichier.'); }
                event.target.value = '';
            },
            async loadPhoto(event, kind = 'panorama') {
                const file = event.target.files?.[0]; if (!file) return;
                if (!['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'].includes(file.type) || file.size > 20 * 1024 * 1024) {
                    this.photoError = t('Choisis une photo JPEG, PNG, WebP ou HEIC de moins de 20 Mo.'); return;
                }
                const currentRead = ++photoReadId;
                const url = URL.createObjectURL(file);
                const image = new Image(); image.src = url;
                try { await image.decode(); }
                catch { URL.revokeObjectURL(url); if (currentRead === photoReadId) this.photoError = t('Ce navigateur ne peut pas lire cette photo. Utilise un fichier JPEG.'); return; }
                if (currentRead !== photoReadId) { URL.revokeObjectURL(url); return; }
                if (photoUrl) URL.revokeObjectURL(photoUrl);
                photoUrl = url; this.photo = url; this.photoError = ''; this.photoKind = kind;
                this.photoAspect = image.naturalWidth / image.naturalHeight;
                this.photoHorizon = kind === 'photo' ? 50 : 75;
                this.photoCalibrated = false; this.photoAction = 'horizon';
                this.photoNotice = t('Photo chargée. Commence par désigner l’horizontale à 0°.');
                event.target.value = '';
            },
            removePhoto() { photoReadId++; this.photo = ''; this.photoNotice = ''; this.photoCalibrated = false; if (photoUrl) URL.revokeObjectURL(photoUrl); photoUrl = undefined; },
            destroy() { photoReadId++; if (photoUrl) URL.revokeObjectURL(photoUrl); form?.removeEventListener('submit', submitHandler); },
        };
    });

    Alpine.data('horizonTrajectory', () => ({
        mask: [], samples: [], selected: 0, warning: '',
        init() {
            try { this.mask = parseMask(this.$root.dataset.mask); }
            catch { this.warning = t('Le masque de ce lieu doit être corrigé dans l’éditeur d’horizon.'); }
            this.samples = JSON.parse(this.$root.dataset.samples);
        },
        get line() { return horizonLine(this.mask); },
        get area() { return `40,204 ${this.line} 760,204`; },
        get segments() { return trajectorySegments(this.samples); },
        renderSegments() {
            const nodes = this.segments.map(segment => {
                const line = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                const color = segment.status === 'blocked' ? '#f0883e' : (segment.status === 'visible' ? '#3fb950' : '#58a6ff');
                for (const [name, value] of Object.entries({points: segment.points, fill: 'none', stroke: color, 'stroke-width': 3, 'stroke-linecap': 'round'})) line.setAttribute(name, value);
                return line;
            });
            this.$refs.segments.replaceChildren(...nodes);
        },
        get current() { return this.samples[Number(this.selected)] ?? null; },
        get state() { return this.current.altitude < this.current.horizon ? t('Masquée par l’horizon') : (this.current.visible ? t('Dégagée · au-dessus du seuil de 20°') : t('Dégagée de l’obstacle · sous le seuil de 20°')); },
        x: horizonX, y: horizonY,
    }));
}
