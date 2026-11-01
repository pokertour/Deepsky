const offlineKey = 'deepsky-offline-plans-v1';
const plansContainer = document.getElementById('plans');
const status = document.getElementById('status');

function read() {
    try {
        const value = JSON.parse(localStorage.getItem(offlineKey));
        return value && Array.isArray(value.plans) ? value : {owner: null, plans: []};
    } catch { return {owner: null, plans: []}; }
}

function element(tag, text, className = '') {
    const node = document.createElement(tag);
    node.textContent = text ?? '';
    node.className = className;
    return node;
}

function render() {
    plansContainer.replaceChildren();
    const stored = read();
    if (!stored.plans.length) plansContainer.append(element('p', 'Aucun plan enregistré. Ouvre un plan avec une connexion et choisis « Enregistrer hors connexion ».'));
    stored.plans.forEach((plan) => {
        const article = element('article');
        article.append(element('h2', `Plan du ${plan.date}`));
        article.append(element('p', `${plan.location || 'Lieu non renseigné'} · ${plan.rig || 'Sans Rig'} · ${plan.timezone || ''}`));
        article.append(element('p', `Copie du ${new Date(plan.savedAt).toLocaleString('fr-FR')} · ${plan.status}`, 'muted'));
        if (plan.notes) article.append(element('p', plan.notes));
        const list = element('ol');
        (plan.targets || []).forEach((target) => {
            const item = element('li');
            item.append(element('h3', target.name));
            item.append(element('p', `${target.start} → ${target.end} · score ${target.score}/100`));
            if (target.coordinates) item.append(element('p', target.coordinates, 'muted'));
            if (target.acquisition) item.append(element('p', target.acquisition));
            list.append(item);
        });
        article.append(list);
        const remove = element('button', 'Supprimer cette copie');
        remove.type = 'button';
        remove.addEventListener('click', () => {
            try {
                const current = read();
                current.plans = current.plans.filter((item) => item.id !== plan.id);
                localStorage.setItem(offlineKey, JSON.stringify(current));
                render();
            } catch { status.textContent = 'Impossible de modifier le stockage.'; }
        });
        article.append(remove);
        plansContainer.append(article);
    });
}

const nightButton = document.getElementById('night-mode');
function setNight(enabled) {
    document.documentElement.dataset.nightMode = enabled ? 'red' : 'normal';
    nightButton.setAttribute('aria-pressed', String(enabled));
    nightButton.textContent = enabled ? 'Désactiver le mode rouge' : 'Mode nuit rouge';
}
try { setNight(localStorage.getItem('deepsky-night-mode') === 'red'); } catch { setNight(false); }
nightButton.addEventListener('click', () => {
    const enabled = document.documentElement.dataset.nightMode !== 'red';
    setNight(enabled);
    try { localStorage.setItem('deepsky-night-mode', enabled ? 'red' : 'normal'); } catch {}
});
document.getElementById('clear-plans').addEventListener('click', () => {
    if (!confirm('Supprimer toutes les copies enregistrées sur cet appareil ?')) return;
    try { localStorage.removeItem(offlineKey); render(); } catch { status.textContent = 'Impossible de modifier le stockage.'; }
});
render();
if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(() => {});
