import {onPageLoad} from './page-lifecycle';
import { t } from './i18n.js';

onPageLoad((signal) => {
    const offlineKey = 'deepsky-offline-plans-v1';
    const nightKey = 'deepsky-night-mode';

    function readPlans() {
        try {
            return JSON.parse(localStorage.getItem(offlineKey)) ?? {owner: null, plans: []};
        } catch {
            return {owner: null, plans: []};
        }
    }

    function applyNightMode(enabled) {
        document.documentElement.dataset.nightMode = enabled ? 'red' : 'normal';
        document.querySelectorAll('[data-night-mode-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', String(enabled));
            const label = enabled ? t('Désactiver le mode rouge') : t('Mode nuit rouge');
            (button.querySelector('[data-night-mode-label]') ?? button).textContent = label;
            button.title = label;
        });
    }

    if (document.body.dataset.offlineUser) {
        const owner = document.body.dataset.offlineUser;
        const stored = readPlans();
        try {
            if (stored.owner && stored.owner !== owner) localStorage.removeItem(offlineKey);
            applyNightMode(localStorage.getItem(nightKey) === 'red');
        } catch {
            applyNightMode(false);
        }

        document.querySelectorAll('[data-night-mode-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const enabled = document.documentElement.dataset.nightMode !== 'red';
                applyNightMode(enabled);
                try { localStorage.setItem(nightKey, enabled ? 'red' : 'normal'); } catch {}
            });
        });

        document.querySelectorAll('[data-offline-plan]').forEach((button) => {
            button.addEventListener('click', async () => {
                const status = document.querySelector('[data-offline-status]');
                try {
                    if ('serviceWorker' in navigator && import.meta.env.PROD) {
                        await navigator.serviceWorker.register('/sw.js');
                        await caches.open('deepsky-offline-v1').then((cache) => cache.addAll(['/offline.html', '/offline.css', '/offline.js']));
                    }
                    const plan = JSON.parse(button.dataset.offlinePlan);
                    const current = readPlans();
                    const plans = current.owner === owner && Array.isArray(current.plans) ? current.plans : [];
                    const saved = {...plan, savedAt: new Date().toISOString()};
                    localStorage.setItem(offlineKey, JSON.stringify({
                        owner,
                        plans: [saved, ...plans.filter((item) => item.id !== plan.id)].slice(0, 15),
                    }));
                    status.textContent = t('Copie enregistrée sur cet appareil. Ouvre « Plans hors connexion » pour la consulter.');
                    button.textContent = t('Actualiser la copie hors connexion');
                } catch {
                    status.textContent = t('Enregistrement impossible : vérifie que le stockage du navigateur est disponible.');
                }
            });
        });

        document.addEventListener('submit', (event) => {
            if (event.target.matches('form') && new URL(event.target.action, location.href).pathname === '/admin/deconnexion') {
                try { localStorage.removeItem(offlineKey); } catch {}
            }
        }, { signal });
    } else {
        applyNightMode(false);
    }

    if (document.body.hasAttribute('data-offline-clear')) {
        try { localStorage.removeItem(offlineKey); } catch {}
    }

});
