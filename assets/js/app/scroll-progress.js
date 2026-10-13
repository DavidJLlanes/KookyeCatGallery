/* Barra de progreso de scroll. */
import { $ } from './helpers.js';

/* ---------- Barra de progreso de scroll ---------- */
export const initScrollProgress = () => {
    const bar = $('#scrollProgress');
    if (!bar) return;
    let ticking = false;
    const update = () => {
        const h = document.documentElement;
        const max = h.scrollHeight - h.clientHeight;
        const p = max > 0 ? (window.scrollY / max) * 100 : 0;
        bar.style.width = p.toFixed(2) + '%';
        ticking = false;
    };
    window.addEventListener('scroll', () => {
        if (!ticking) { requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    update();
};
