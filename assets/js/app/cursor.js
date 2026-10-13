/* Cursor personalizado (escritorio). */
import { $, $$, isFinePointer, prefersReducedMotion } from './helpers.js';

/* ---------- Cursor personalizado ---------- */
export const initCursor = () => {
    if (!isFinePointer || prefersReducedMotion) return;
    const dot  = $('.cursor-dot');
    const ring = $('.cursor-ring');
    if (!dot || !ring) return;

    document.body.classList.add('cursor-ready');

    let mx = 0, my = 0, rx = 0, ry = 0;
    document.addEventListener('mousemove', (e) => {
        mx = e.clientX; my = e.clientY;
        dot.style.transform = `translate(${mx}px, ${my}px) translate(-50%, -50%)`;
    });
    const loop = () => {
        rx += (mx - rx) * 0.18;
        ry += (my - ry) * 0.18;
        ring.style.transform = `translate(${rx}px, ${ry}px) translate(-50%, -50%)`;
        requestAnimationFrame(loop);
    };
    loop();

    // "Ver" sobre las fotos
    $$('.card__btn').forEach(el => {
        el.addEventListener('mouseenter', () => ring.classList.add('is-view'));
        el.addEventListener('mouseleave', () => ring.classList.remove('is-view'));
    });
    // Aumento normal sobre el resto de interactivos
    $$('a, button:not(.card__btn)').forEach(el => {
        el.addEventListener('mouseenter', () => ring.classList.add('is-hover'));
        el.addEventListener('mouseleave', () => ring.classList.remove('is-hover'));
    });
};
