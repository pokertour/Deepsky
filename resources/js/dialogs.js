import { t } from './i18n.js';

/*
 * Modales natives (<dialog>) aux couleurs de Deepsky, à la place de
 * window.confirm / window.prompt : focus piégé, Échap pour annuler,
 * fermeture au clic sur le fond.
 */

const TONES = {
    danger: { icon: 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z', badge: 'bg-red-400/10 text-red-300', button: 'bg-red-500 text-white hover:bg-red-400' },
    accent: { icon: 'M12 8v4m0 4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z', badge: 'bg-accent/10 text-accent', button: 'bg-accent text-slate-950 hover:bg-sky-300' },
};

const escape = (text) => String(text).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char]);

function openDialog(html, setup) {
    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'm-auto w-[min(28rem,calc(100vw-2rem))] rounded-2xl border border-border bg-card-bg p-0 text-text-main shadow-[0_40px_120px_-20px_rgba(0,0,0,.9)] backdrop:bg-black/70 backdrop:backdrop-blur-sm open:animate-[dialog-in_.18s_ease-out]';
        dialog.innerHTML = html;
        document.body.append(dialog);

        let result = false;
        const close = (value) => { result = value; dialog.close(); };
        dialog.addEventListener('close', () => { dialog.remove(); resolve(result); });
        dialog.addEventListener('click', (event) => { if (event.target === dialog) close(false); });
        dialog.querySelectorAll('[data-dialog-cancel]').forEach((button) => button.addEventListener('click', () => close(false)));
        setup?.(dialog, close);
        dialog.showModal();
    });
}

/**
 * Demande une confirmation. Résout true si l'utilisateur confirme.
 *
 * @param {{ title?: string, message: string, confirmLabel?: string, cancelLabel?: string, tone?: 'danger'|'accent' }} options
 */
export function confirmDialog({ title, message, confirmLabel, cancelLabel, tone = 'accent' }) {
    const style = TONES[tone] ?? TONES.accent;

    return openDialog(`
        <form method="dialog" class="p-6">
            <div class="flex gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl ${style.badge}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5" aria-hidden="true"><path d="${style.icon}"/></svg>
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-text-bold">${escape(title || t('Confirmer l’action'))}</h2>
                    <p class="mt-1.5 text-sm leading-relaxed text-text-main/70">${escape(message)}</p>
                </div>
            </div>
            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" data-dialog-cancel class="min-h-11 rounded-xl border border-border px-4 py-2.5 text-sm text-text-main/80 transition hover:text-text-bold">${escape(cancelLabel || t('Annuler'))}</button>
                <button type="submit" data-dialog-confirm class="min-h-11 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${style.button}">${escape(confirmLabel || t('Confirmer'))}</button>
            </div>
        </form>`, (dialog, close) => {
        dialog.querySelector('[data-dialog-confirm]').addEventListener('click', (event) => { event.preventDefault(); close(true); });
        // Le focus initial va sur « Annuler » pour une action dangereuse.
        (tone === 'danger' ? dialog.querySelector('[data-dialog-cancel]') : dialog.querySelector('[data-dialog-confirm]')).focus();
    });
}

/** Affiche un lien à copier à la main quand le presse-papiers est indisponible. */
export function linkDialog({ title, url }) {
    return openDialog(`
        <div class="p-6">
            <h2 class="text-base font-semibold text-text-bold">${escape(title || t('Copier le lien'))}</h2>
            <p class="mt-1.5 text-sm text-text-main/70">${escape(t('La copie automatique n’est pas disponible : sélectionne le lien ci-dessous.'))}</p>
            <input type="text" readonly value="${escape(url)}" class="mt-4 w-full rounded-xl border border-border bg-bg px-4 py-3 font-mono text-xs text-text-bold outline-none focus:border-accent">
            <div class="mt-5 flex justify-end">
                <button type="button" data-dialog-cancel class="min-h-11 rounded-xl border border-border px-4 py-2.5 text-sm text-text-main/80 transition hover:text-text-bold">${escape(t('Fermer'))}</button>
            </div>
        </div>`, (dialog) => {
        const input = dialog.querySelector('input');
        input.focus();
        input.select();
    });
}

/*
 * Formulaires à confirmer : <form data-confirm="Message" data-confirm-title="…"
 * data-confirm-label="Supprimer" data-confirm-tone="danger">.
 */
globalThis.document?.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('form[data-confirm]');
    if (!form || form.dataset.confirmed === 'true') return;

    event.preventDefault();
    event.stopImmediatePropagation();

    const confirmed = await confirmDialog({
        title: form.dataset.confirmTitle,
        message: form.dataset.confirm,
        confirmLabel: form.dataset.confirmLabel,
        tone: form.dataset.confirmTone,
    });

    if (confirmed) {
        form.dataset.confirmed = 'true';
        form.requestSubmit(event.submitter ?? undefined);
        delete form.dataset.confirmed;
    }
}, true);

if (typeof window !== 'undefined') window.confirmDialog = confirmDialog;
