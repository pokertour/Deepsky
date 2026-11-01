import './dialogs';
import './pwa-tools';
import './astrophoto-upload';
import './photo-navigation';
import './newsletter';
import './gallery';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import {registerObservingLocationMap} from './observing-location-map';
import {registerHorizonTools} from './horizon-editor';
import {acquisitionSessions} from './acquisition-sessions';
import {objectDescription} from './object-description';
import {astrophotoTranslation} from './astrophoto-translation';
import {qrSvg} from './qr-code';
import {liveSky} from './live-sky';
import {skyAr} from './sky-ar';
import { t } from './i18n.js';
import { linkDialog } from './dialogs';
window.Alpine = Alpine;
registerObservingLocationMap(Alpine);
registerHorizonTools(Alpine);
Alpine.data('acquisitionSessions', acquisitionSessions);
Alpine.data('objectDescription', objectDescription);
Alpine.data('astrophotoTranslation', astrophotoTranslation);
Alpine.data('liveSky', liveSky);
Alpine.data('skyAr', skyAr);
Alpine.data('qrCode', () => ({
    init() {
        this.$root.innerHTML = qrSvg(this.$root.dataset.value);
    },
}));

Alpine.data('setupItemForm', () => ({
    type: '',

    init() {
        this.type = this.$root.dataset.initialType;
    },

    shows(element) {
        return (element?.dataset.specTypes || '').split(',').includes(this.type);
    },

    hasSpecifications() {
        return [...this.$root.querySelectorAll('[data-spec-types]')].some((element) => this.shows(element));
    },
}));

Alpine.data('celestialObjectLookup', () => ({
    query: '',
    selectedId: '',
    results: [],
    loading: false,
    open: false,
    searchUrl: '',

    init() {
        this.searchUrl = this.$root.dataset.searchUrl;
        this.selectedId = this.$root.dataset.initialId;
        this.query = this.$root.dataset.initialLabel;
    },

    async search() {
        if (this.query.trim().length < 2 || this.query === this.$root.dataset.initialLabel) {
            this.open = false;
            return;
        }

        this.loading = true;
        this.open = true;

        try {
            const response = await fetch(`${this.searchUrl}?q=${encodeURIComponent(this.query.trim())}`, {
                headers: {'Accept': 'application/json'},
            });
            this.results = response.ok ? await response.json() : [];
        } finally {
            this.loading = false;
        }
    },

    choose(result) {
        this.selectedId = result.id;
        this.query = result.label;
        this.results = [];
        this.open = false;
        this.fillPhotoFields(result);
    },

    fillPhotoFields(result) {
        const form = this.$root.closest('form');

        if (! form || ! result.designation) {
            return;
        }

        const target = form.querySelector('[name="target"]');
        const title = form.querySelector('[name="title"]');

        if (target) {
            target.value = result.designation;
        }

        if (title && ! title.value.trim()) {
            title.value = result.title || result.designation;
        }

        if (result.tag) {
            const tag = form.querySelector(`[name="tags[]"][value="${result.tag}"]`);

            if (tag) {
                tag.checked = true;
            }
        }
    },

    clear() {
        this.selectedId = '';
        this.query = '';
        this.results = [];
        this.open = false;
    },

    close() {
        this.open = false;
    },
}));

window.copyPhotoLink = async (button, url) => {
    const label = button.querySelector('.share-label') ?? button;
    const original = label.dataset.original ??= label.innerText;

    try {
        await navigator.clipboard.writeText(url);
        label.innerText = t('Lien copié ✓');
        setTimeout(() => (label.innerText = original), 2000);
    } catch {
        linkDialog({ url });
    }
};

// Feuille de partage native (mobile / PWA), avec repli sur la copie du lien.
window.sharePhoto = async (button, url, title) => {
    if (! navigator.share) {
        return window.copyPhotoLink(button, url);
    }

    try {
        await navigator.share({title, url});
    } catch (error) {
        if (error.name !== 'AbortError') {
            window.copyPhotoLink(button, url);
        }
    }
};

window.isStandalonePwa = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

Livewire.start();

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
