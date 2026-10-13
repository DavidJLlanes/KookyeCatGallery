/* Botón volver arriba. */
import { $ } from './helpers.js';

/* ---------- Botón volver arriba ---------- */
export const initTopButton = () => {
    const btn = $('.top-btn');
    if (!btn) return;
    const onScroll = () => {
        btn.classList.toggle('is-visible', window.scrollY > window.innerHeight * 0.6);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    onScroll();
};
