const CACHE_NAME = 'deepsky-static-v2';
const OFFLINE_CACHE = 'deepsky-offline-v1';
const OFFLINE_FILES = ['/offline.html', '/offline.css', '/offline.js'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(OFFLINE_CACHE).then((cache) => cache.addAll(OFFLINE_FILES)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key.startsWith('deepsky-') && ![CACHE_NAME, OFFLINE_CACHE].includes(key)).map((key) => caches.delete(key)),
        )).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match('/offline.html')));
        return;
    }

    const url = new URL(event.request.url);
    if (url.origin === self.location.origin && OFFLINE_FILES.includes(url.pathname)) {
        event.respondWith(caches.match(event.request).then((cached) => cached || fetch(event.request)));
        return;
    }

    if (! ['style', 'script', 'image', 'font'].includes(event.request.destination)) {
        return;
    }

    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(event.request);

        try {
            const fresh = await fetch(event.request);

            if (fresh.ok) {
                cache.put(event.request, fresh.clone());
            }

            return fresh;
        } catch (error) {
            if (cached) {
                return cached;
            }

            throw error;
        }
    })());
});
