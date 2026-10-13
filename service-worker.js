'use strict';

// GitHub Actions sustituye este marcador por el SHA exacto de cada despliegue.
const BUILD_VERSION = 'b7414d078d23bbe59285653ae7dbd92f798a4d07';
const CACHE_NAME = 'fotos-leon-static-' + BUILD_VERSION;
const RUNTIME_CACHE = 'fotos-leon-runtime-' + BUILD_VERSION;
const OFFLINE_URL = '/offline.html';
const PUBLIC_STATIC = [
    // Componentes de estilo (assets/css/components/manifest.json); tests/catalogs.php comprueba que esta lista coincide.
    '/assets/css/components/base.css',
    '/assets/css/components/cursor.css',
    '/assets/css/components/hero.css',
    '/assets/css/components/gallery-intro.css',
    '/assets/css/components/masonry.css',
    '/assets/css/components/lightbox.css',
    '/assets/css/components/top-button.css',
    '/assets/css/components/pagination.css',
    '/assets/css/components/footer.css',
    '/assets/css/components/legal.css',
    '/assets/css/components/preloader.css',
    '/assets/css/components/scroll-progress.css',
    '/assets/css/components/cursor-label.css',
    '/assets/css/components/hero-shine.css',
    '/assets/css/components/project.css',
    '/assets/css/components/tilt.css',
    '/assets/css/components/filter-transition.css',
    '/assets/css/components/categories.css',
    '/assets/css/components/map.css',
    '/assets/css/components/lightbox-flip.css',
    '/assets/css/components/photo-detail.css',
    '/assets/css/components/share.css',
    '/assets/css/components/related.css',
    '/assets/css/components/contact.css',
    '/assets/css/components/mobile-profile.css',
    '/assets/css/components/admin-access.css',
    '/assets/css/components/mobile-links.css',
    '/assets/css/components/pwa.css',
    '/assets/css/components/photo-actions.css',
    '/assets/css/components/narrow-mobile.css',
    '/assets/css/components/collections.css',
    '/assets/js/pwa.js',
    '/assets/js/main.js',
    '/assets/js/app/helpers.js',
    '/assets/js/app/share.js',
    '/assets/js/app/preloader.js',
    '/assets/js/app/scroll-progress.js',
    '/assets/js/app/hero-parallax.js',
    '/assets/js/app/reveal.js',
    '/assets/js/app/lazy-images.js',
    '/assets/js/app/tilt.js',
    '/assets/js/app/top-button.js',
    '/assets/js/app/cursor.js',
    '/assets/js/app/image-protection.js',
    '/assets/js/app/photo-gestures.js',
    '/assets/js/app/app-photo-features.js',
    '/assets/js/app/photo-slide.js',
    '/assets/js/app/lightbox.js',
    '/assets/js/app/photo-page.js',
    '/assets/js/app/map.js',
    '/assets/js/app/gallery.js',
    '/assets/js/app/mosaic-color.js',
    '/assets/js/photo-editor.js',
    '/assets/js/photo-filter-engine.js',
    '/assets/js/photo-editor-presets.js',
    '/assets/css/photo-editor.css',
    '/assets/icons/favicon-32.png',
    '/assets/icons/apple-touch-icon.png',
    '/assets/icons/pwa-icon-192.webp',
    '/assets/icons/pwa-icon-512.webp',
    '/assets/icons/pwa-icon-maskable-192.webp',
    '/assets/icons/pwa-icon-maskable-512.webp',
    '/assets/icons/pwa-icon-monochrome-512.png',
    OFFLINE_URL
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(PUBLIC_STATIC)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => (key.startsWith('fotos-leon-static-') && key !== CACHE_NAME)
                || (key.startsWith('fotos-leon-runtime-') && key !== RUNTIME_CACHE)).map((key) => caches.delete(key))))
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
        && !url.pathname.startsWith('/assets/icons/');
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
        || url.pathname.startsWith('/assets/js/')
        || url.pathname.startsWith('/assets/icons/');
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
