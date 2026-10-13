/* Tilt 3D y reflejo en las tarjetas (escritorio). */
import { $, $$, isFinePointer, prefersReducedMotion } from './helpers.js';

/* ---------- Tilt 3D + reflejo (escritorio) ---------- */
export const initTilt = () => {
    if (!isFinePointer || prefersReducedMotion) return;
    $$('.card').forEach(card => {
        const btn = $('.card__btn', card);
        const pic = $('.card__picture', card);
        if (!btn || !pic) return;

        const onMove = (e) => {
            const r = btn.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width;
            const py = (e.clientY - r.top) / r.height;
            const rotY = (px - 0.5) * 16;
            const rotX = (0.5 - py) * 16;
            btn.style.setProperty('--sheen-x', (px * 100).toFixed(1) + '%');
            btn.style.setProperty('--sheen-y', (py * 100).toFixed(1) + '%');
            btn.style.transform = `rotateX(${rotX.toFixed(2)}deg) rotateY(${rotY.toFixed(2)}deg)`;
        };
        const reset = () => {
            card.classList.remove('is-tilting');
            btn.style.transform = '';
        };

        btn.addEventListener('pointerenter', () => { if (isFinePointer) card.classList.add('is-tilting'); });
        btn.addEventListener('pointermove', onMove);
        btn.addEventListener('pointerleave', reset);
        btn.addEventListener('pointerdown', reset); // limpia antes de medir el FLIP
    });
};
