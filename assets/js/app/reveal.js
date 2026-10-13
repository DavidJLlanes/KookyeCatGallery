/* Revelado de elementos al hacer scroll. */
import { $$, prefersReducedMotion } from './helpers.js';

/* ---------- Reveal on scroll ---------- */
export const initReveal = () => {
    const targets = $$('[data-reveal]');
    if (!('IntersectionObserver' in window) || prefersReducedMotion) {
        targets.forEach(el => el.classList.add('is-revealed'));
        return;
    }

    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                if (entry.target.classList.contains('card')) {
                    setTimeout(() => entry.target.classList.add('is-revealed'), i * 60);
                } else {
                    entry.target.classList.add('is-revealed');
                }
                io.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.05 });

    targets.forEach(el => io.observe(el));
    $$('.hero [data-reveal]').forEach(el => el.classList.add('is-revealed'));
};
