/* Preloader cinematográfico de la portada. */
import { $, $$ } from './helpers.js';

/* ---------- Preloader ---------- */
export const initPreloader = () => {
    const pre = $('#preloader');
    if (!pre) return;
    const fill = $('.preloader__bar-fill', pre);
    const html = document.documentElement;
    html.classList.add('is-loading');

    let done = false;
    const finish = () => {
        if (done) return;
        done = true;
        if (fill) fill.style.width = '100%';
        setTimeout(() => {
            pre.classList.add('is-done');
            html.classList.remove('is-loading');
            setTimeout(() => pre.remove(), 900);
        }, 180);
    };

    // Progreso real basado en las primeras imágenes (hero + primeras cards)
    const imgs = $$('.card__img').slice(0, 8);
    if (imgs.length === 0) { finish(); return; } // sin galería (p. ej. página de foto)
    const total = Math.max(1, imgs.length);
    let loaded = 0;
    const bump = () => {
        loaded++;
        if (fill) fill.style.width = Math.min(92, (loaded / total) * 92) + '%';
        if (loaded >= total) finish();
    };
    imgs.forEach(img => {
        if (img.complete && img.naturalWidth > 0) bump();
        else {
            img.addEventListener('load', bump, { once: true });
            img.addEventListener('error', bump, { once: true });
        }
    });

    // Garantías de cierre
    window.addEventListener('load', () => setTimeout(finish, 250), { once: true });
    setTimeout(finish, 3200); // tope absoluto
};
