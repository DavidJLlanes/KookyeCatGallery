/* Compartir la URL de una foto y copiar enlaces al portapapeles. */
import { $ } from './helpers.js';

/* ---------- Compartir URL de una foto ---------- */
// Rellena los hrefs de un bloque .share--js con la URL de la foto actual.
export const updateShare = (container, url, title) => {
    if (!container) return;
    const u = encodeURIComponent(url);
    const t = encodeURIComponent((title || window.siteText("Fotografía de autor")) + window.siteText(" · Kookye Cat Gallery"));
    const map = {
        wa: `https://wa.me/?text=${t}%20${u}`,
        x:  `https://twitter.com/intent/tweet?text=${t}&url=${u}`,
        tg: `https://t.me/share/url?url=${u}&text=${t}`,
        fb: `https://www.facebook.com/sharer/sharer.php?u=${u}`,
    };
    container.querySelectorAll('[data-share]').forEach(a => {
        const href = map[a.dataset.share];
        if (href) { a.href = href; a.target = '_blank'; a.rel = 'noopener'; }
    });
    const copy = container.querySelector('.share__btn--copy');
    if (copy) copy.dataset.copyUrl = url;
};

/* ---------- Copiar enlace al portapapeles ---------- */
export const initCopyLinks = () => {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.share__btn--copy');
        if (!btn) return;
        const url = btn.dataset.copyUrl;
        if (!url || !navigator.clipboard) return;
        navigator.clipboard.writeText(url).then(() => {
            btn.classList.add('is-copied');
            setTimeout(() => btn.classList.remove('is-copied'), 1700);
        }).catch(() => {});
    });
};
