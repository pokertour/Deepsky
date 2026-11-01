import {readHeader, summarizeHeaders} from './fits-header.js';
import { t } from './i18n.js';
import { confirmDialog } from './dialogs.js';

export function integrationSeconds(sessions) {
    return Math.round(sessions.flatMap(session => session.frames)
        .filter(frame => frame.type === 'light')
        .reduce((total, frame) => total + (Number(frame.count) || 0) * (Number(frame.seconds) || 0), 0) * 1000) / 1000;
}

export function formatDuration(seconds) {
    const milliseconds = Math.round(Math.max(0, Number(seconds) || 0) * 1000);
    const hours = Math.floor(milliseconds / 3600000);
    const minutes = Math.floor((milliseconds % 3600000) / 60000);
    const remaining = (milliseconds % 60000) / 1000;
    return [hours ? `${hours}h` : '', minutes ? `${minutes}m` : '', remaining || !milliseconds ? `${remaining}s` : ''].filter(Boolean).join(' ');
}

export function acquisitionSessions() {
    return {
        sessions: [],
        nextKey: 0,
        manualExposure: '',
        importing: false,
        importReport: null,
        importErrors: [],
        init() {
            this.manualExposure = this.$root.dataset.initialExposure;
            this.sessions = JSON.parse(this.$root.dataset.sessions || '[]').map(session => this.withKeys(session));
        },
        withKeys(session) {
            return {...session, key: this.nextKey++, frames: session.frames.map(frame => ({...frame, key: this.nextKey++}))};
        },
        addSession() {
            this.sessions.push(this.withKeys({date: '', camera_mode: 'color', frames: [{type: 'light', filter: '', count: '', seconds: ''}]}));
        },
        duplicateSession(index) {
            const session = this.sessions[index];
            this.sessions.push(this.withKeys({...session, date: '', frames: session.frames.map(frame => ({...frame}))}));
        },
        removeSession(index) {
            if (this.sessions.length === 1) this.manualExposure = String(this.total);
            this.sessions.splice(index, 1);
        },
        addFrame(session) {
            session.frames.push({key: this.nextKey++, type: 'light', filter: '', count: '', seconds: ''});
        },
        async importFiles(fileList) {
            const files = [...(fileList ?? [])].filter((file) => /\.(fits?|fts|xisf)$/i.test(file.name));
            if (!files.length) return;
            this.importing = true;
            this.importErrors = [];
            const headers = [];
            for (const file of files) {
                try {
                    headers.push(await readHeader(file));
                } catch (error) {
                    this.importErrors.push(error.message);
                }
            }
            this.importing = false;
            if (!headers.length) return;

            const summary = summarizeHeaders(headers);
            if (!this.sessions.length || await confirmDialog({
                title: t('Remplacer les sessions ?'),
                message: t('Remplacer les sessions d’acquisition déjà saisies par celles des fichiers ?'),
                confirmLabel: t('Remplacer'),
            })) {
                this.sessions = summary.sessions.map((session) => this.withKeys(session));
            }

            const form = this.$root.closest('form');
            const filled = [];
            const fill = (name, value, label) => {
                const field = form?.elements.namedItem(name);
                if (!field || value === null || value === undefined || value === '' || String(field.value).trim() !== '') return;
                field.value = value;
                field.dispatchEvent(new Event('input', {bubbles: true}));
                filled.push(label);
            };
            fill('captured_at', summary.firstNight, 'date');
            fill('target', summary.target, 'cible');
            fill('gain', summary.gain, 'gain');
            fill('offset', summary.offset, 'offset');
            fill('sensor_temp', summary.sensorTemp, t('température'));
            fill('focal_length', summary.focalLength, 'focale');
            fill('filters', summary.filters, 'filtres');

            this.importReport = t(':lights light(s) et :calibration image(s) de calibration lus, :nights nuit(s)', {lights: summary.lightCount, calibration: summary.calibrationCount, nights: summary.sessions.length})
                + (filled.length ? t(' · champs remplis : :fields.', {fields: filled.join(', ')}) : '.');
        },
        get total() { return integrationSeconds(this.sessions); },
        sessionTotal(session) { return integrationSeconds([session]); },
        formatDuration,
        get summary() {
            const filters = new Map();
            this.sessions.flatMap(session => session.frames).filter(frame => frame.type === 'light').forEach(frame => {
                const filter = frame.filter?.trim() || t('Sans filtre');
                const item = filters.get(filter) || {filter, count: 0, seconds: 0};
                item.count += Number(frame.count) || 0;
                item.seconds += (Number(frame.count) || 0) * (Number(frame.seconds) || 0);
                filters.set(filter, item);
            });
            return [...filters.values()];
        },
    };
}
