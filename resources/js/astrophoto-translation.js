import { t } from './i18n.js';
import { confirmDialog } from './dialogs.js';

const FIELDS = ['title', 'description', 'object_description'];

export function astrophotoTranslation() {
    return {
        loading: false,
        done: false,
        error: '',
        field(name) {
            return this.$root.closest('form').querySelector(`[name="${name}"]`);
        },
        async translate() {
            if (this.loading) return;
            const texts = Object.fromEntries(FIELDS.map((name) => [name, this.field(name)?.value.trim() || null]));
            const overwrite = FIELDS.some((name) => this.field(`${name}_en`)?.value.trim());
            if (overwrite && !(await confirmDialog({
                title: t('Remplacer la version anglaise ?'),
                message: t('Remplacer les textes anglais déjà saisis par la traduction ?'),
                confirmLabel: t('Remplacer'),
            }))) return;

            this.loading = true;
            this.done = false;
            this.error = '';
            try {
                const response = await fetch(this.$root.dataset.translateUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.$root.closest('form').querySelector('[name="_token"]').value,
                    },
                    body: JSON.stringify(texts),
                });
                const data = await response.json();
                if (!response.ok) {
                    this.error = Object.values(data.errors || {}).flat().join(' ') || data.message || t('Traduction impossible. Réessaie plus tard.');
                    return;
                }
                for (const [name, translation] of Object.entries(data.translations || {})) {
                    const input = this.field(`${name}_en`);
                    if (input) input.value = translation;
                }
                this.done = true;
            } catch {
                this.error = t('Connexion interrompue ou service indisponible. Réessaie plus tard.');
            } finally {
                this.loading = false;
            }
        },
    };
}
