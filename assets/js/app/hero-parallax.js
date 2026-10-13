/* Parallax del hero (escritorio). */
import { $, isFinePointer, prefersReducedMotion } from './helpers.js';

/* ---------- Parallax del hero (escritorio) ---------- */
export const initHeroParallax = () => {
    if (!isFinePointer || prefersReducedMotion) return;
    const hero = $('.hero');
    if (!hero) return;
    const content = $('.hero__content', hero);
    if (!content) return;

    let ticking = false;
    const update = () => {
        const y = window.scrollY;
        const vh = window.innerHeight;
        if (y <= vh) {
            const p = y / vh;
            content.style.transform = `translate3d(0, ${y * 0.16}px, 0)`;
            content.style.opacity = String(Math.max(0, 1 - p * 1.05));
        }
        ticking = false;
    };
    window.addEventListener('scroll', () => {
        if (!ticking) { requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
    update();
};
