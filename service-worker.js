'use strict';

// GitHub Actions sustituye este marcador por el SHA exacto de cada despliegue.
const BUILD_VERSION = '__BUILD_VERSION__';
const CACHE_NAME = 'kookye-cat-gallery-static-' + BUILD_VERSION;
const RUNTIME_CACHE = 'kookye-cat-gallery-runtime-' + BUILD_VERSION;
const OFFLINE_URL = '/offline.html';
const PUBLIC_STATIC = [
    '/assets/css/style.css',
    '/assets/js/pwa.js',
    '/assets/js/main.js',
    '/assets/js/photo-editor.js',
    '/assets/js/photo-filter-engine.js',
    '/assets/js/photo-editor-presets.js',
    '/assets/css/photo-editor.css',
    '/favicon.svg',
    '/favicon.svg',
    '/favicon.svg',
    '/favicon.svg',
    '/favicon.svg',
    '/favicon.svg',
    '/favicon.svg',
    '/manifest.json',
    OFFLINE_URL
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PUBLIC_STATIC)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => (key.startsWith('kookye-cat-gallery-static-') && key !== CACHE_NAME)
                || (key.startsWith('kookye-cat-gallery-runtime-') && key !== RUNTIME_CACHE)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Navegación siempre intenta red primero; sin conexión muestra una página offline cuidada.
    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try { return await fetch(request, { cache: 'no-store' }); }
            catch (_) { return (await caches.match(OFFLINE_URL)) || Response.error(); }
        })());
        return;
    }

    // El panel, API y datos nunca se sirven desde la caché del service worker.
    if (/\.php(?:$|\/)/i.test(url.pathname) || url.pathname.startsWith('/data/')) {
        event.respondWith(fetch(request, { cache: 'no-store' }));
        return;
    }

    const isPhoto = /\.(?:jpe?g|png|webp|avif)$/i.test(url.pathname)
        && url.pathname !== '/favicon.svg';
    if (isPhoto) {
        event.respondWith((async () => {
            const cache = await caches.open(RUNTIME_CACHE);
            const cached = await cache.match(request);
            try {
                // Red primero: una fotografía editada puede conservar la misma URL.
                // Así nunca mostramos indefinidamente una versión anterior del editor.
                const response = await fetch(request, { cache: 'no-cache' });
                if (response.ok) {
                    await cache.put(request, response.clone());
                    const keys = await cache.keys();
                    if (keys.length > 40) await cache.delete(keys[0]);
                }
                return response;
            } catch (_) {
                // Sin conexión sí usamos la última copia vista.
                return cached || Response.error();
            }
        })());
        return;
    }

    const isPublicStatic = PUBLIC_STATIC.some((path) => url.pathname === path)
        || url.pathname.startsWith('/assets/css/')
        || url.pathname.startsWith('/assets/js/');
    if (!isPublicStatic) return;

    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        try {
            const response = await fetch(request);
            if (response.ok) await cache.put(request, response.clone());
            return response;
        } catch (error) {
            const cached = await cache.match(request);
            if (cached) return cached;
            throw error;
        }
    })());
});
