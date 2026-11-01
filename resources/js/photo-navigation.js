import {onPageLoad} from './page-lifecycle';
import { t } from './i18n.js';

onPageLoad((signal) => {
    document.querySelectorAll('[data-photo-navigation]').forEach((surface) => {
        const navigate = (direction) => {
            const url = direction < 0 ? surface.dataset.previous : surface.dataset.next;
            if (url) {
                if (surface.id === 'container') window.location.assign(url);
                else window.Livewire.navigate(url);
            }
        };
        let start = null;
        surface.addEventListener('touchstart', (event) => {
            if (surface.querySelector('.is-zoomed')) return;
            start = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
        }, { passive: true });
        surface.addEventListener('touchend', (event) => {
            if (!start || surface.querySelector('.is-zoomed') || !event.changedTouches.length) return;
            const dx = event.changedTouches[0].clientX - start.x;
            const dy = event.changedTouches[0].clientY - start.y;
            start = null;
            if (Math.abs(dx) > 65 && Math.abs(dx) > Math.abs(dy) * 1.5) navigate(dx < 0 ? 1 : -1);
        }, { passive: true });
        if (surface.id === 'container') {
            document.addEventListener('keydown', (event) => {
                if (surface.querySelector('.is-zoomed')) return;
                if (event.key === 'ArrowLeft') navigate(-1);
                if (event.key === 'ArrowRight') navigate(1);
            }, { signal });
        }
        if (surface.dataset.viewUrl) {
            const data = new FormData();
            data.append('_token', surface.dataset.csrf);
            navigator.sendBeacon(surface.dataset.viewUrl, data);
        }
    });

});
