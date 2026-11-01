import {onPageLoad} from './page-lifecycle';
import { t } from './i18n.js';

onPageLoad((signal) => {
    document.querySelectorAll('[data-astrophoto-upload]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!form.querySelector('input[type="file"]:not(:disabled)') || !form.reportValidity()) return;
            event.preventDefault();
            if (form.dataset.uploading === 'true') return;
            form.dataset.uploading = 'true';
            const status = form.querySelector('[data-upload-status]');
            const message = form.querySelector('[data-upload-message]');
            const progress = form.querySelector('[data-upload-progress]');
            const buttons = [...form.querySelectorAll('button[type="submit"], button:not([type])')];
            const data = new FormData(form);
            const request = new XMLHttpRequest();
            status.hidden = false;
            buttons.forEach((button) => { button.disabled = true; });
            message.textContent = t('Transfert en cours…');
            progress.value = 0;
            request.open('POST', form.action);
            request.setRequestHeader('Accept', 'application/json');
            request.upload.addEventListener('progress', (upload) => {
                if (upload.lengthComputable) {
                    progress.value = Math.round(upload.loaded / upload.total * 100);
                    message.textContent = progress.value === 100
                        ? t('Transfert terminé. Enregistrement et préparation des images…')
                        : t('Transfert en cours : :percent %', {percent: progress.value});
                }
            });
            const fail = (text) => {
                form.dataset.uploading = 'false';
                message.textContent = text;
                buttons.forEach((button) => { button.disabled = false; });
            };
            request.addEventListener('load', () => {
                let response;
                try { response = JSON.parse(request.responseText); } catch { response = {}; }
                if (request.status >= 200 && request.status < 300 && response.redirect) {
                    window.location.assign(response.redirect);
                } else {
                    fail(Object.values(response.errors || {}).flat().join(' ') || t('Envoi impossible. Vérifie le fichier et réessaie.'));
                }
            });
            request.addEventListener('error', () => fail(t('Connexion interrompue. Vérifie ta connexion puis réessaie.')));
            request.send(data);
        });
    });

    document.querySelectorAll('[data-processing-status]').forEach((panel) => {
        const refresh = async () => {
            if (signal.aborted || !panel.isConnected) return;
            if (document.hidden) {
                window.setTimeout(refresh, 5000);
                return;
            }
            try {
                const response = await fetch(panel.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', signal });
                if (!response.ok) return;
                const status = await response.json();
                if (signal.aborted) return;
                panel.querySelector('[data-conversion-label]').textContent = status.conversion_label;
                panel.querySelector('[data-astrometry-label]').textContent = t('Astrométrie : :status', {status: status.astrometry_label});
                const publish = panel.closest('form').querySelector('select[name="is_published"] option[value="1"]');
                publish.disabled = status.conversion_status !== 'ready' && !publish.selected;
                const retry = document.querySelector('[data-retry-conversions]');
                if (retry) retry.disabled = status.conversion_status === 'converting';
                if (!['queued', 'converting'].includes(status.conversion_status) && !['pending', 'solving'].includes(status.astrometry_status)) return;
            } catch {
                // Retry when connectivity returns.
            }
            window.setTimeout(refresh, 5000);
        };
        refresh();
    });

});
