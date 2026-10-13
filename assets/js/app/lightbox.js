/* Lightbox con transición FLIP desde la galería. */
import { $, $$, CARDS, galleryRoot, prefersReducedMotion } from './helpers.js';
import { bindPhotoNavigation } from './photo-gestures.js';
import { slideImage } from './photo-slide.js';
import { updateShare } from './share.js';

/* ---------- Lightbox con FLIP ---------- */
export const initLightbox = () => {
    const lightbox = $('#lightbox');
    if (!lightbox) return;
    const imgEl     = $('.lightbox__img', lightbox);
    const stage     = $('.lightbox__stage', lightbox);
    const titleEl   = $('.lightbox__title', lightbox);
    const descEl    = $('.lightbox__description', lightbox);
    const counterEl = $('.lightbox__counter', lightbox);
    const coordsEl  = $('.lightbox__coords', lightbox);
    const coordsTxt = $('.lightbox__coords-text', lightbox);
    const shareEl   = $('.share--js', lightbox);
    const closeBtn  = $('.lightbox__close', lightbox);
    const fullBtn   = $('.lightbox__fullscreen', lightbox);
    const prevBtn   = $('.lightbox__nav--prev', lightbox);
    const nextBtn   = $('.lightbox__nav--next', lightbox);

    if (!$$(CARDS).length) return;

    // Siempre usa las fotos visibles en el momento (respeta filtro + página activa)
    const getCards = () => $$('.card:not(.is-hidden):not([hidden])');

    // Elemento "flyer" que vuela
    const flyer = document.createElement('img');
    flyer.className = 'lightbox__flyer';
    flyer.setAttribute('aria-hidden', 'true');
    flyer.alt = '';
    document.body.appendChild(flyer);

    let currentIndex = 0;
    let animating = false;
    let openedFromMap = false;
    let returningToPhoto = false;

    const fitRect = (natW, natH, area) => {
        const ar = (natW && natH) ? natW / natH : 1.5;
        let w = area.width;
        let h = w / ar;
        if (h > area.height) { h = area.height; w = h * ar; }
        return {
            left: area.left + (area.width - w) / 2,
            top: area.top + (area.height - h) / 2,
            width: w,
            height: h,
        };
    };

    const setText = (card) => {
        titleEl.textContent = card.dataset.title || '';
        descEl.textContent = card.dataset.description || '';

        // Contador x / y según orden de subida (la más antigua = 1).
        // Con filtro de categoría activo: numeración y total DENTRO de la categoría.
        // Sin filtro: numeración y total GLOBALES.
        const activeChip = document.querySelector('.categories-filter__chip.is-active');
        const cat = activeChip ? (activeChip.dataset.category || '') : '';
        if (cat) {
            counterEl.textContent = `${card.dataset.numCat} / ${card.dataset.catTotal}`;
        } else {
            const totalGlobal = $$('.card').length;
            counterEl.textContent = `${card.dataset.numGlobal} / ${totalGlobal}`;
        }

        // Coordenadas GPS → enlace a Google Maps
        if (coordsEl && coordsTxt) {
            const lat = parseFloat(card.dataset.lat);
            const lng = parseFloat(card.dataset.lng);
            if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
                coordsTxt.textContent = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                coordsEl.href = `https://www.google.com/maps?q=${lat},${lng}`;
                coordsEl.hidden = false;
            } else {
                coordsEl.hidden = true;
            }
        }

        // Botones de compartir → URL propia de esta foto (si tiene slug)
        if (shareEl) {
            const slug = card.dataset.slug;
            if (slug) {
                shareEl.hidden = false;
                updateShare(shareEl, `${location.origin}/foto/${slug}`, card.dataset.title || '');
            } else {
                shareEl.hidden = true;
            }
        }
    };

    const preloadNeighbors = () => {
        const cards = getCards();
        [-1, 1].forEach(d => {
            const n = cards[(currentIndex + d + cards.length) % cards.length];
            if (n) new Image().src = n.dataset.full;
        });
    };

    const placeFlyer = (rect, src) => {
        flyer.src = src;
        flyer.style.transition = 'none';
        flyer.style.top = rect.top + 'px';
        flyer.style.left = rect.left + 'px';
        flyer.style.width = rect.width + 'px';
        flyer.style.height = rect.height + 'px';
        flyer.classList.add('is-active');
        void flyer.offsetWidth; // reflow
    };

    const moveFlyer = (rect) => {
        flyer.style.transition = '';
        requestAnimationFrame(() => {
            flyer.style.top = rect.top + 'px';
            flyer.style.left = rect.left + 'px';
            flyer.style.width = rect.width + 'px';
            flyer.style.height = rect.height + 'px';
        });
    };

    const onFlyerEnd = (cb) => {
        let fired = false;
        const handler = (e) => {
            if (e.propertyName !== 'width' && e.propertyName !== 'top') return;
            if (fired) return;
            fired = true;
            flyer.removeEventListener('transitionend', handler);
            cb();
        };
        flyer.addEventListener('transitionend', handler);
        setTimeout(() => { if (!fired) { fired = true; flyer.removeEventListener('transitionend', handler); cb(); } }, 720);
    };

    const open = (index, card, skipTransition = false) => {
        if (animating) return;
        currentIndex = index;
        openedFromMap = skipTransition;
        const srcImg = $('.card__img', card);
        setText(card);
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        imgEl.classList.remove('is-shown');

        // Actualiza la URL con el slug de esta foto (sin recargar)
        const slug = card.dataset.slug;
        if (slug) history.pushState({ slug }, '', `/foto/${slug}`);
        document.dispatchEvent(new CustomEvent('photo:changed', { detail: { slug } }));

        // Sin FLIP: fade simple
        if (skipTransition || prefersReducedMotion || !srcImg || !srcImg.naturalWidth) {
            showReal(card);
            return;
        }

        // Neutraliza el tilt al instante para medir la posición real
        const btnEl = $('.card__btn', card);
        if (btnEl) {
            btnEl.style.transition = 'none';
            btnEl.style.transform = '';
            card.classList.remove('is-tilting');
            requestAnimationFrame(() => { btnEl.style.transition = ''; });
        }

        animating = true;
        const rect = srcImg.getBoundingClientRect();
        placeFlyer(rect, srcImg.currentSrc || srcImg.src);

        const area = stage.getBoundingClientRect();
        const target = fitRect(srcImg.naturalWidth, srcImg.naturalHeight, area);
        moveFlyer(target);

        // Carga la versión grande en paralelo
        const full = card.dataset.full;
        const pre = new Image();
        pre.src = full;

        onFlyerEnd(() => {
            imgEl.src = full;
            imgEl.alt = card.dataset.title || window.siteText("Fotografía de autor");
            const reveal = () => {
                requestAnimationFrame(() => imgEl.classList.add('is-shown'));
                setTimeout(() => flyer.classList.remove('is-active'), 60);
                animating = false;
            };
            if (imgEl.complete && imgEl.naturalWidth > 0) reveal();
            else { imgEl.onload = reveal; imgEl.onerror = reveal; }
        });

        preloadNeighbors();
    };

    const showReal = (card) => {
        const full = card.dataset.full;
        const pre = new Image();
        pre.onload = () => {
            imgEl.src = full;
            imgEl.alt = card.dataset.title || window.siteText("Fotografía de autor");
            requestAnimationFrame(() => imgEl.classList.add('is-shown'));
        };
        pre.src = full;
        preloadNeighbors();
    };

    const requestNativeFs = () => {
        const fn = lightbox.requestFullscreen || lightbox.webkitRequestFullscreen;
        if (!fn) return;
        try { const p = fn.call(lightbox); if (p && p.catch) p.catch(() => {}); } catch (e) {}
    };
    const exitNativeFs = () => {
        const active = document.fullscreenElement || document.webkitFullscreenElement;
        if (active !== lightbox) return;
        const fn = document.exitFullscreen || document.webkitExitFullscreen;
        if (fn) { try { const p = fn.call(document); if (p && p.catch) p.catch(() => {}); } catch (e) {} }
    };
    const enterImmersive = () => {
        if (animating || !lightbox.classList.contains('is-open')) return;
        lightbox.classList.add('lightbox--immersive');
        document.body.classList.add('photo-immersive-open');
        requestNativeFs();
        closeBtn.focus({ preventScroll: true });
    };

    const close = (fromHistory = false) => {
        if (animating || returningToPhoto) return;
        const card = getCards()[currentIndex];
        const slug = card && card.dataset.slug;
        if (slug && !fromHistory) {
            // La URL del lightbox solo era un pushState: cargar la ficha real de la foto.
            returningToPhoto = true;
            exitNativeFs();
            const target = `/foto/${encodeURIComponent(slug)}`;
            if (location.pathname === target) location.reload();
            else location.replace(target);
            return;
        }
        const immersive = lightbox.classList.contains('lightbox--immersive');
        if (immersive) {
            exitNativeFs();
            lightbox.classList.remove('lightbox--immersive');
            document.body.classList.remove('photo-immersive-open');
        }
        const srcImg = card && $('.card__img', card);
        const imgRect = imgEl.getBoundingClientRect();

        const plainClose = () => {
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            imgEl.classList.remove('is-shown');
            // Atrás del navegador ya cambió la URL; no añadir otra entrada al historial.
            if (!fromHistory && location.pathname.startsWith('/foto/')) history.replaceState(null, '', '/');
        };

        if (immersive || openedFromMap || prefersReducedMotion || !srcImg || !imgEl.classList.contains('is-shown') || imgRect.width < 2) {
            plainClose();
            return;
        }

        animating = true;
        placeFlyer(imgRect, imgEl.currentSrc || imgEl.src);
        imgEl.classList.remove('is-shown');

        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        const r = srcImg.getBoundingClientRect();
        moveFlyer(r);
        onFlyerEnd(() => {
            flyer.classList.remove('is-active');
            animating = false;
        });
    };

    const go = (dir) => {
        if (animating) return;
        const cards = getCards();
        if (cards.length < 2) return;
        currentIndex = (currentIndex + dir + cards.length) % cards.length;
        const card = cards[currentIndex];
        setText(card);
        const slug = card.dataset.slug;
        if (slug) history.replaceState({ slug }, '', `/foto/${slug}`);
        else if (location.pathname.startsWith('/foto/')) history.replaceState(null, '', '/');
        document.dispatchEvent(new CustomEvent('photo:changed', { detail: { slug } }));
        // Deslizamiento continuo entre las dos fotos (sin pasar por el fondo): ver slideImage.
        slideImage(imgEl, card.dataset.full, card.dataset.title || window.siteText("Fotografía de autor"), dir);
        preloadNeighbors();
    };

    // Delegación: cualquier carta clicada (visible) abre el lightbox en la posición correcta
    const masonry = galleryRoot();
    if (masonry) {
        masonry.addEventListener('click', (e) => {
            // Las fotos con URL propia son enlaces <a> → navegan solas a /foto/slug.
            // Aquí solo gestionamos las fotos SIN slug (botón), que abren el visor rápido.
            const btn = e.target.closest('.card__btn');
            if (!btn) return;
            if (btn.tagName === 'A') return; // La ficha de la foto se sirve desde el servidor.
            const card = btn.closest('.card');
            if (!card || card.classList.contains('is-hidden')) return;
            const visCards = getCards();
            const idx = visCards.indexOf(card);
            if (idx !== -1) open(idx, card);
        });
    }

    if (masonry) {
        masonry.addEventListener('map:open-photo', (event) => {
            const card = event.detail?.card;
            if (!card) return;
            const slug = card.dataset.slug;
            if (slug) {
                window.location.assign(`/foto/${encodeURIComponent(slug)}`);
                return;
            }
            const index = getCards().indexOf(card);
            if (index !== -1) open(index, card, true);
        });
    }

    closeBtn.addEventListener('click', () => close());
    fullBtn.addEventListener('click', enterImmersive);
    const onNativeFsChange = () => {
        const active = document.fullscreenElement || document.webkitFullscreenElement;
        if (!active && lightbox.classList.contains('is-open') && lightbox.classList.contains('lightbox--immersive')) {
            close();
        }
    };
    document.addEventListener('fullscreenchange', onNativeFsChange);
    document.addEventListener('webkitfullscreenchange', onNativeFsChange);
    prevBtn.addEventListener('click', () => go(-1));
    nextBtn.addEventListener('click', () => go(1));

    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox || e.target.classList.contains('lightbox__stage')) close();
    });

    document.addEventListener('keydown', (e) => {
        if (!lightbox.classList.contains('is-open')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') go(-1);
        if (e.key === 'ArrowRight') go(1);
    });

    bindPhotoNavigation(stage, (direction) => {
        if (lightbox.classList.contains('is-open')) go(direction);
    });

    // Botón atrás del navegador: cierra el lightbox si está abierto
    window.addEventListener('popstate', () => {
        if (lightbox.classList.contains('is-open')) close(true);
    });
};
