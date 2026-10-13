/* Carga diferida con desenfoque progresivo (blur-up). */
import { $$ } from './helpers.js';

/* ---------- Lazy / blur-up ---------- */
export const initLazyImages = () => {
    const imgs = $$('.card__img');
    const markLoaded = (img) => {
        if (img.complete && img.naturalWidth > 0) {
            img.classList.add('is-loaded');
        } else {
            img.addEventListener('load', () => img.classList.add('is-loaded'), { once: true });
            img.addEventListener('error', () => img.classList.add('is-loaded'), { once: true });
        }
    };
    if (!('IntersectionObserver' in window)) { imgs.forEach(markLoaded); return; }
    const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) { markLoaded(entry.target); io.unobserve(entry.target); }
        });
    }, { rootMargin: '300px 0px' });
    imgs.forEach(img => io.observe(img));
};
