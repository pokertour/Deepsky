import { t } from './i18n.js';
export function objectDescription() {
    return {
        text: '',
        suggestion: '',
        suggestionObjectId: '',
        loading: false,
        error: '',
        init() { this.text = this.$root.dataset.initialDescription || ''; },
        selectedObjectId() {
            return this.$root.closest('form').querySelector('[name="celestial_object_id"]').value;
        },
        async generate() {
            if (this.loading) return;
            const objectId = this.selectedObjectId();
            this.error = '';
            this.suggestion = '';
            if (!objectId) {
                this.error = t('Sélectionne d’abord un objet dans le catalogue.');
                return;
            }
            this.loading = true;
            try {
                const response = await fetch(this.$root.dataset.generateUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.$root.closest('form').querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify({celestial_object_id: objectId}),
                });
                const data = await response.json();
                if (!response.ok) {
                    this.error = Object.values(data.errors || {}).flat().join(' ') || data.message || t('Génération impossible. Réessaie plus tard.');
                } else if (this.selectedObjectId() !== objectId) {
                    this.error = t('L’objet sélectionné a changé. Relance la génération pour la nouvelle cible.');
                } else {
                    this.suggestion = data.description;
                    this.suggestionObjectId = String(data.object_id);
                }
            } catch {
                this.error = t('Connexion interrompue ou service indisponible. Réessaie plus tard.');
            } finally {
                this.loading = false;
            }
        },
        accept() {
            if (this.selectedObjectId() !== this.suggestionObjectId) {
                this.error = t('L’objet sélectionné a changé. Relance la génération pour la nouvelle cible.');
                return;
            }
            this.text = this.suggestion;
            this.suggestion = '';
            this.error = '';
        },
    };
}
