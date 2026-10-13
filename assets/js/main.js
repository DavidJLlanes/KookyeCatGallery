/* =========================================================
   Galería fotográfica de autor
   Punto de entrada: módulos ES de assets/js/app/ (uno por función).
   Preloader · scroll suave · parallax · FLIP lightbox ·
   tilt 3D · blur-up · reveal · cursor · barra de scroll
   ========================================================= */

import { initCopyLinks } from './app/share.js';
import { initPreloader } from './app/preloader.js';
import { initScrollProgress } from './app/scroll-progress.js';
import { initHeroParallax } from './app/hero-parallax.js';
import { initReveal } from './app/reveal.js';
import { initLazyImages } from './app/lazy-images.js';
import { initTilt } from './app/tilt.js';
import { initTopButton } from './app/top-button.js';
import { initCursor } from './app/cursor.js';
import { initImageProtection } from './app/image-protection.js';
import { initAppPhotoFeatures } from './app/app-photo-features.js';
import { initLightbox } from './app/lightbox.js';
import { initPhotoPage } from './app/photo-page.js';
import { initGallery } from './app/gallery.js';
import { initMosaicColor } from './app/mosaic-color.js';
import { initMap } from './app/map.js';

// Marca que JS está activo (para revelados que no deben dejar contenido oculto sin JS)
document.documentElement.classList.add('js');

/* ---------- Init ---------- */
const init = () => {
    initPreloader();
    initScrollProgress();
    initHeroParallax();
    initReveal();
    initLazyImages();
    initTilt();
    initLightbox();
    initTopButton();
    initCursor();
    initGallery();
    initMap();
    initPhotoPage();
    initImageProtection();
    initCopyLinks();
    initAppPhotoFeatures();
    initMosaicColor();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
