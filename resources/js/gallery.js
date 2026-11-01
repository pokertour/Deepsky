import {onPageLoad} from './page-lifecycle';
import { t } from './i18n.js';

onPageLoad((signal) => {
    if (!document.getElementById('modal')) return;

    async function toggleLike(button, photoId) {
        button.disabled = true;

        try {
            const response = await fetch(`/astrophoto/${photoId}/like`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) return;

            const data = await response.json();

            button.dataset.liked = data.liked ? '1' : '0';
            button.classList.toggle('text-tech-red', data.liked);
            button.classList.toggle('text-white/70', !data.liked);
            button.querySelector('.like-icon').setAttribute('fill', data.liked ? 'currentColor' : 'none');
            button.querySelector('.like-count').innerText = data.likes_count;
            button.setAttribute('aria-pressed', data.liked ? 'true' : 'false');
        } finally {
            button.disabled = false;
        }
    }

    let currentPhoto = null;
    let modalOpener = null;
    let annotationsVisible = false;
    let compareActive = false;
    let starlessActive = false;

    function setStarless(active) {
        starlessActive = active && !!currentPhoto?.starless;
        document.getElementById('modal-img').src = starlessActive ? currentPhoto.starless : currentPhoto.img;
        document.querySelectorAll('#starless-switch [data-starless]').forEach((button) => {
            const selected = (button.dataset.starless === '1') === starlessActive;
            button.classList.toggle('bg-accent', selected);
            button.classList.toggle('text-[#010409]', selected);
            button.classList.toggle('text-white/70', !selected);
        });

        const originalLink = document.getElementById('modal-original-link');
        if (originalLink && currentPhoto) {
            originalLink.href = `/astrophoto/${currentPhoto.id}/full` + (starlessActive ? '?starless=1' : '');
        }
    }

    function buildVersions(item) {
        const section = document.getElementById('versions-section');
        const list = document.getElementById('versions-list');

        list.innerHTML = '';

        if (!item.versions?.length) {
            section.classList.add('hidden');
            return;
        }

        item.versions.forEach((version) => {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'w-full flex items-center justify-between gap-3 border border-border rounded px-3 py-2 ' +
                'font-mono text-[0.75rem] text-text-main hover:border-accent hover:text-accent transition-colors';
            const label = document.createElement('span');
            label.textContent = `${version.date ?? '—'}${version.integ ? ' · ' + version.integ : ''}`;
            const cta = document.createElement('span');
            cta.className = 'uppercase text-[0.65rem] tracking-widest compare-cta';
            cta.textContent = t('Comparer');
            row.append(label, cta);
            row.onclick = () => compareActive && row.classList.contains('compare-active') ?
                stopCompare() :
                startCompare(version, row);
            list.appendChild(row);
        });

        section.classList.remove('hidden');
    }

    function startCompare(version, row) {
        stopCompare();

        const wrap = document.getElementById('compare-wrap');
        const img = document.getElementById('modal-img');

        document.getElementById('compare-img').src = version.img;
        document.getElementById('compare-label-before').innerText = version.date ?? t('Avant');
        document.getElementById('compare-label-after').innerText = currentPhoto.date ?? t('Après');

        // Le comparateur remplace l'overlay d'annotations le temps de la comparaison
        annotationsVisible = false;
        renderAnnotations();

        compareActive = true;
        row.classList.add('compare-active', 'border-accent', 'text-accent');
        row.querySelector('.compare-cta').innerText = t('Fermer');

        wrap.style.left = img.offsetLeft + 'px';
        wrap.style.top = img.offsetTop + 'px';
        wrap.style.width = img.clientWidth + 'px';
        wrap.style.height = img.clientHeight + 'px';
        wrap.classList.remove('hidden');

        setComparePosition(50);
    }

    function stopCompare() {
        compareActive = false;
        document.getElementById('compare-wrap').classList.add('hidden');
        document.querySelectorAll('#versions-list .compare-active').forEach((row) => {
            row.classList.remove('compare-active', 'border-accent', 'text-accent');
            row.querySelector('.compare-cta').innerText = t('Comparer');
        });
    }

    function setComparePosition(percent) {
        percent = Math.max(0, Math.min(100, percent));
        document.getElementById('compare-img').style.clipPath = `inset(0 ${100 - percent}% 0 0)`;
        document.getElementById('compare-handle').style.left = `calc(${percent}% - 1px)`;
    }

    document.getElementById('compare-wrap').addEventListener('pointerdown', (e) => {
        const wrap = e.currentTarget;
        wrap.setPointerCapture(e.pointerId);

        const move = (event) => {
            const rect = wrap.getBoundingClientRect();
            setComparePosition((event.clientX - rect.left) / rect.width * 100);
        };

        move(e);
        wrap.addEventListener('pointermove', move);
        wrap.addEventListener('pointerup', () => wrap.removeEventListener('pointermove', move), { once: true });
    });

    window.addEventListener('resize', () => {
        if (compareActive) {
            const wrap = document.getElementById('compare-wrap');
            const img = document.getElementById('modal-img');
            wrap.style.left = img.offsetLeft + 'px';
            wrap.style.top = img.offsetTop + 'px';
            wrap.style.width = img.clientWidth + 'px';
            wrap.style.height = img.clientHeight + 'px';
        }
    }, { signal });

    function toggleAnnotations() {
        if (!currentPhoto?.astro?.annotations?.length) return;

        annotationsVisible = !annotationsVisible;
        document.getElementById('astro-toggle').innerText = annotationsVisible ?
            t('Masquer les annotations') :
            t('Afficher les annotations');
        renderAnnotations();
    }

    // Dessine les cercles d'annotation par-dessus l'image, en convertissant
    // les coordonnées pixel (image native) vers la taille affichée.
    function renderAnnotations() {
        const overlay = document.getElementById('annotations-overlay');
        const img = document.getElementById('modal-img');

        if (!annotationsVisible || !currentPhoto?.astro?.annotations?.length || !img.naturalWidth) {
            overlay.classList.add('hidden');
            return;
        }

        const scale = img.clientWidth / img.naturalWidth;

        overlay.style.left = img.offsetLeft + 'px';
        overlay.style.top = img.offsetTop + 'px';
        overlay.style.width = img.clientWidth + 'px';
        overlay.style.height = img.clientHeight + 'px';
        overlay.innerHTML = '';

        currentPhoto.astro.annotations.forEach((a) => {
            const r = Math.max(a.r * scale, 12);
            const circle = document.createElement('div');
            circle.className = 'absolute rounded-full border-2 border-accent/80';
            circle.style.left = (a.x * scale - r) + 'px';
            circle.style.top = (a.y * scale - r) + 'px';
            circle.style.width = (r * 2) + 'px';
            circle.style.height = (r * 2) + 'px';

            const label = document.createElement('span');
            label.className = 'absolute left-1/2 -translate-x-1/2 top-full mt-1 text-accent text-[0.65rem] font-mono whitespace-nowrap bg-[#010409]/70 px-1 rounded';
            label.innerText = a.name;
            circle.appendChild(label);

            overlay.appendChild(circle);
        });

        overlay.classList.remove('hidden');
    }

    window.addEventListener('resize', renderAnnotations, { signal });
    document.getElementById('modal-img').addEventListener('load', renderAnnotations, { signal });

    function openModal(el) {
        const item = JSON.parse(el.dataset.photo);
        const cards = [...document.querySelectorAll('article[data-photo]')];
        const index = cards.indexOf(el);
        document.getElementById('modal-prev').disabled = index <= 0;
        document.getElementById('modal-next').disabled = index < 0 || index >= cards.length - 1;
        document.getElementById('modal-description-section').classList.toggle('hidden', !item.description);
        document.getElementById('modal-description').textContent = item.description || '';
        document.getElementById('modal-detail-link').href = item.share_url;

        // POST request to increment views_count
        const formData = new FormData();
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
        navigator.sendBeacon(`/astrophoto/${item.id}/click`, formData);
        // Remplissage des données
        document.getElementById('modal-title').innerText = item.title;
        document.getElementById('modal-subtitle').innerText = item.subtitle;
        currentPhoto = item;
        window.galleryCurrentPhoto = item;
        document.getElementById('starless-switch').classList.toggle('hidden', !item.starless);
        document.getElementById('starless-switch').classList.toggle('flex', !!item.starless);
        setStarless(false);
        document.getElementById('spec-date').innerText = item.date;
        const regionRow = document.getElementById('spec-region-row');
        regionRow.classList.toggle('hidden', !item.region);
        document.getElementById('spec-region').innerText = item.region || '';
        document.getElementById('spec-integ').innerText = item.integ;
        document.getElementById('spec-gain').innerText = item.gain;
        document.getElementById('spec-offset').innerText = item.offset;
        document.getElementById('spec-temp').innerText = item.temp ? item.temp + "°C" : '';
        document.getElementById('spec-focal').innerText = item.focal ? item.focal + "mm" : '';

        const celestialSection = document.getElementById('celestial-section');
        if (item.celestial) {
            document.getElementById('celestial-designation').innerText = item.celestial.designation || '---';
            const fillCelestialRow = (rowId, valueId, value) => {
                document.getElementById(rowId).classList.toggle('hidden', !value);
                document.getElementById(valueId).innerText = value || '';
            };
            fillCelestialRow('celestial-name-row', 'celestial-name', item.celestial.name);
            fillCelestialRow('celestial-type-row', 'celestial-type', item.celestial.type);
            fillCelestialRow('celestial-constellation-row', 'celestial-constellation', item.celestial.constellation);
            fillCelestialRow('celestial-magnitude-row', 'celestial-magnitude', item.celestial.magnitude);
            fillCelestialRow('celestial-distance-row', 'celestial-distance', item.celestial.distance);
            fillCelestialRow('celestial-size-row', 'celestial-size', item.celestial.size);
            fillCelestialRow('celestial-physical-size-row', 'celestial-physical-size', item.celestial.physical_size);
            celestialSection.classList.remove('hidden');
        } else {
            celestialSection.classList.add('hidden');
        }

        document.getElementById('setup-rig').innerText = item.rig || t('Configuration historique');
        const equipmentContainer = document.getElementById('setup-items');
        equipmentContainer.innerHTML = '';
        item.equipment.forEach((equipment) => {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] gap-4 border-b border-border/60 py-2 text-[0.75rem]';
            const label = document.createElement('span');
            label.className = 'text-text-main/50';
            label.textContent = equipment.label;
            const value = document.createElement('span');
            value.className = 'text-right text-text-bold';
            value.textContent = equipment.value;
            row.append(label, value);
            equipmentContainer.appendChild(row);
        });
        document.getElementById('setup-filters').innerText = item.filters;

        // Section astrométrie (visible uniquement si la photo est résolue)
        currentPhoto = item;
        window.galleryCurrentPhoto = item;
        annotationsVisible = false;
        const astroSection = document.getElementById('astro-section');
        const astroToggle = document.getElementById('astro-toggle');
        if (item.astro) {
            document.getElementById('astro-ra').innerText = item.astro.ra;
            document.getElementById('astro-dec').innerText = item.astro.dec;
            document.getElementById('astro-scale').innerText = item.astro.scale;
            document.getElementById('astro-radius').innerText = item.astro.radius;

            const objectsContainer = document.getElementById('astro-objects');
            objectsContainer.innerHTML = '';
            item.astro.objects.forEach((name) => {
                const badge = document.createElement('span');
                badge.className = 'bg-accent/10 border border-accent/30 text-accent px-2 py-0.5 rounded text-[0.7rem] font-mono';
                badge.innerText = name;
                objectsContainer.appendChild(badge);
            });

            astroToggle.classList.toggle('hidden', !item.astro.annotations.length);
            astroToggle.innerText = t('Afficher les annotations');
            astroSection.classList.remove('hidden');
        } else {
            astroSection.classList.add('hidden');
        }
        renderAnnotations();
        stopCompare();
        buildVersions(item);

        // En PWA installée, un nouvel onglet n'a pas de bouton retour : on reste dans la même fenêtre
        if (isStandalonePwa()) {
            document.getElementById('modal-original-link').removeAttribute('target');
        }

        const modal = document.getElementById('modal');
        const wasOpen = modal.classList.contains('modal-active');

        // 1. On affiche la structure (grid au lieu de hidden)
        modal.classList.remove('hidden');
        modal.classList.add('modal-active');

        if (!wasOpen) {
            modalOpener = el;
            document.getElementById('modal-close').focus({ preventScroll: true });
        }

        // 2. On déclenche lt('opacité après un micro-délai pour l')animation
        setTimeout(() => {
            modal.classList.add('opacity-100');
        }, 10);

        document.body.style.overflow = 'hidden';
    }

    function navigatePhoto(direction) {
        if (!currentPhoto || compareActive) return;
        const cards = [...document.querySelectorAll('article[data-photo]')];
        const index = cards.findIndex((card) => JSON.parse(card.dataset.photo).id === currentPhoto.id);
        if (cards[index + direction]) openModal(cards[index + direction]);
    }

    let swipeStart = null;
    const imageContainer = document.getElementById('modal-img-container');
    imageContainer.addEventListener('touchstart', (event) => {
        swipeStart = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
    }, { passive: true });
    imageContainer.addEventListener('touchend', (event) => {
        if (!swipeStart || compareActive || !event.changedTouches.length) return;
        const deltaX = event.changedTouches[0].clientX - swipeStart.x;
        const deltaY = event.changedTouches[0].clientY - swipeStart.y;
        swipeStart = null;
        if (Math.abs(deltaX) > 65 && Math.abs(deltaX) > Math.abs(deltaY) * 1.5) navigatePhoto(deltaX < 0 ? 1 : -1);
    }, { passive: true });

    function closeModal() {
        stopCompare();

        const modal = document.getElementById('modal');

        // 1. On lance la transition de sortie
        modal.classList.remove('opacity-100');

        // 2. On cache après la fin de la transition (500ms)
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('modal-active');
        }, 500);

        document.body.style.overflow = 'auto';

        // Rend le focus à la carte de la photo affichée en dernier (navigation clavier).
        const cards = [...document.querySelectorAll('article[data-photo]')];
        const lastCard = cards.find((card) => JSON.parse(card.dataset.photo).id === currentPhoto?.id) ?? modalOpener;
        lastCard?.focus({ preventScroll: true });
        lastCard?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        modalOpener = null;
    }

    // Garde le focus clavier à l'intérieur de la modale ouverte.
    function trapFocus(event) {
        const modal = document.getElementById('modal');
        const focusable = [...modal.querySelectorAll('a[href], button:not([disabled])')]
            .filter((node) => node.offsetParent !== null);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    // Ouverture de l'aperçu au clavier depuis une carte focalisée.
    document.addEventListener('keydown', (e) => {
        if (document.getElementById('modal').classList.contains('modal-active')) return;
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches?.('article[data-photo]')) {
            e.preventDefault();
            openModal(e.target);
        }
    }, { signal });

    // Close on Esc, bascule starless avec « S »
    document.addEventListener('keydown', (e) => {
        if (!document.getElementById('modal').classList.contains('modal-active')) return;
        if (e.key === 'Escape') closeModal();
        if (e.key === 'Tab') trapFocus(e);
        if (e.key === 'ArrowLeft') navigatePhoto(-1);
        if (e.key === 'ArrowRight') navigatePhoto(1);
        if (e.key.toLowerCase() === 's' && currentPhoto?.starless && document.getElementById('modal').classList.contains('modal-active')) {
            setStarless(!starlessActive);
        }
    }, { signal });

    Object.assign(window, {toggleLike, openModal, closeModal, navigatePhoto, setStarless, toggleAnnotations});
    return () => {
        stopCompare();
        document.body.style.overflow = "";
        window.galleryCurrentPhoto = null;
    };

});
