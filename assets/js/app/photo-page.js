/* Página de foto dedicada (/foto/slug). */
import { $, prefersReducedMotion } from './helpers.js';
import { bindPhotoNavigation } from './photo-gestures.js';
import { SLIDE_DIM, slideImage } from './photo-slide.js';
import { updateShare } from './share.js';

/* ---------- Página de foto dedicada (/foto/slug) ---------- */
// Visor a pantalla completa autónomo: no depende de la galería (que aquí no existe).
// Pasar de una foto a otra (flechas de la ficha o del visor, teclado, gestos) NO recarga la página: se pide la ficha de la foto
// vecina al servidor (así título, descripción, enlaces y opciones privadas siguen siendo los del servidor), se precarga su imagen
// y se cambia todo en el sitio. En pantalla completa la foto nueva desliza con el dedo o la animación (ver slideImage); en la ficha
// se usa un fundido de página si el navegador lo permite. Si algo falla, se navega a la página como siempre.
export const initPhotoPage = () => {
    const detail   = $('.photo-detail');
    const lightbox = $('#lightbox');
    if (!detail || !lightbox) return;

    const imgEl     = $('.lightbox__img', lightbox);
    const titleEl   = $('.lightbox__title', lightbox);
    const descEl    = $('.lightbox__description', lightbox);
    const counterEl = $('.lightbox__counter', lightbox);
    const coordsEl  = $('.lightbox__coords', lightbox);
    const coordsTxt = $('.lightbox__coords-text', lightbox);
    const shareEl   = $('.share--js', lightbox);
    const closeBtn  = $('.lightbox__close', lightbox);
    const prevBtn   = $('.lightbox__nav--prev', lightbox);
    const nextBtn   = $('.lightbox__nav--next', lightbox);
    const isOpen    = () => lightbox.classList.contains('is-open');

    // Datos de la foto actual, siempre leídos de la ficha en pantalla (cambia al navegar).
    const current = () => {
        const img = $('.photo-detail__img', detail);
        const coords = $('.photo-detail__coords', detail);
        return {
            full: img ? img.src : '',
            title: ($('.photo-detail__title', detail)?.textContent || '').trim(),
            desc: ($('.photo-detail__desc', detail)?.textContent || '').trim(),
            coords,
        };
    };

    const updateViewerButtons = () => {
        const hasNeighbors = Boolean(detail.dataset.photoPrevious || detail.dataset.photoNext);
        if (prevBtn) { prevBtn.hidden = !hasNeighbors; prevBtn.disabled = !detail.dataset.photoPrevious; }
        if (nextBtn) { nextBtn.hidden = !hasNeighbors; nextBtn.disabled = !detail.dataset.photoNext; }
    };
    updateViewerButtons();

    // Texto del visor (título, descripción, coordenadas y enlaces de compartir) de la foto actual.
    const fillViewerText = () => {
        const { title, desc, coords } = current();
        if (titleEl)   titleEl.textContent = title;
        if (descEl)    descEl.textContent = desc;
        if (counterEl) counterEl.textContent = '';
        if (coordsEl && coordsTxt) {
            if (coords) {
                coordsTxt.textContent = coords.textContent.trim();
                coordsEl.href = coords.href;
                coordsEl.hidden = false;
            } else {
                coordsEl.hidden = true;
            }
        }
        if (shareEl) {
            const slug = document.body.dataset.openSlug;
            if (slug) {
                shareEl.hidden = false;
                updateShare(shareEl, `${location.origin}/foto/${slug}`, title);
            } else {
                shareEl.hidden = true;
            }
        }
    };

    /* ---- Fichas de las fotos vecinas: petición, caché y vista previa ---- */
    const pageCache = new Map();
    const fetchPhotoPage = (path) => {
        if (!pageCache.has(path)) {
            const request = fetch(path, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
                .then(response => { if (!response.ok) throw new Error('HTTP ' + response.status); return response.text(); })
                .then(html => new DOMParser().parseFromString(html, 'text/html'));
            request.catch(() => pageCache.delete(path));
            pageCache.set(path, request);
        }
        return pageCache.get(path);
    };
    const preloadImage = (src) => {
        if (!src) return Promise.resolve();
        const image = new Image();
        image.src = src;
        return image.decode().catch(() => {});
    };
    const prefetchNeighbors = () => {
        setTimeout(() => {
            [detail.dataset.photoPrevious, detail.dataset.photoNext].filter(Boolean).forEach(path => fetchPhotoPage(path).catch(() => {}));
            [detail.dataset.photoPreviousImage, detail.dataset.photoNextImage].filter(Boolean).forEach(src => preloadImage(src));   // Descargadas y decodificadas: listas para deslizar.
        }, 250);
    };

    // Pone en la página actual el contenido de la ficha `doc` (la de otra foto): datos, flechas, imagen, textos, relacionadas y cabecera.
    const applyPhoto = (doc) => {
        const next = $('.photo-detail', doc);
        if (!next) throw new Error('Sin ficha');
        const adopt = (node) => document.importNode(node, true);
        ['data-photo-previous', 'data-photo-next', 'data-photo-previous-image', 'data-photo-next-image', 'aria-label']
            .forEach(name => detail.setAttribute(name, next.getAttribute(name) || ''));
        const swapNode = (selector) => {
            const oldNode = $(selector, detail), newNode = $(selector, next);
            if (oldNode && newNode) oldNode.replaceWith(adopt(newNode));
        };
        swapNode('.photo-detail__back');
        swapNode('.photo-detail__navigation');
        swapNode('.photo-detail__info');
        const button = $('.photo-detail__btn', detail), nextButton = $('.photo-detail__btn', next);
        if (button && nextButton) {
            button.dataset.zoomSlug = nextButton.dataset.zoomSlug || '';
            button.setAttribute('aria-label', nextButton.getAttribute('aria-label') || '');
        }
        const img = $('.photo-detail__img', detail), nextImg = $('.photo-detail__img', next);
        if (img && nextImg) ['src', 'alt', 'width', 'height'].forEach(name => {
            if (nextImg.hasAttribute(name)) img.setAttribute(name, nextImg.getAttribute(name)); else img.removeAttribute(name);
        });
        const related = $('.related'), nextRelated = $('.related', doc);
        if (related && nextRelated) related.replaceWith(adopt(nextRelated));
        else if (related) related.remove();
        else if (nextRelated) detail.insertAdjacentElement('afterend', adopt(nextRelated));
        // Cabecera: título, descripción, dirección canónica y etiquetas para redes.
        document.title = doc.title;
        const link = document.head.querySelector('link[rel="canonical"]'), nextLink = doc.head.querySelector('link[rel="canonical"]');
        if (link && nextLink) link.setAttribute('href', nextLink.getAttribute('href'));
        document.head.querySelectorAll('meta[name="description"], meta[property^="og:"], meta[name^="twitter:"]').forEach(meta => {
            const key = meta.getAttribute('name') ? 'name' : 'property';
            const other = doc.head.querySelector(`meta[${key}="${meta.getAttribute(key)}"]`);
            if (other) meta.setAttribute('content', other.getAttribute('content') || '');
        });
        if (doc.body.dataset.openSlug) document.body.dataset.openSlug = doc.body.dataset.openSlug;
        updateViewerButtons();
    };

    /* The photo and its text form one layout. Fade the entire detail out before
       changing its image and measurements, then reveal the new layout. This avoids
       overlapping snapshots when consecutive photos have different aspect ratios. */
    const detailImage = () => $('.photo-detail__img', detail);
    /* Transicion profesional de la ficha: deslizamiento horizontal + fade.
       La direccion del gesto define el sentido (siguiente = entra por la derecha).
       Curvas de easing tipo iOS/Apple Photos: salida rapida, entrada con inercia suave. */
    const transitionDetail = async (commit, direction = 1) => {
        if (prefersReducedMotion || !detail.animate) { commit(); return; }

        const dist = Math.min(56, Math.round(window.innerWidth * 0.09));
        const outMs = 260;
        const inMs  = 480;
        const outEase = 'cubic-bezier(0.4, 0.0, 0.6, 1)';
        const inEase  = 'cubic-bezier(0.16, 1, 0.3, 1)';

        detail.style.willChange = 'transform, opacity';

        const leaving = detail.animate([
            { opacity: 1, transform: 'translate3d(0, 0, 0)' },
            { opacity: 0, transform: `translate3d(${-direction * dist}px, 0, 0)` }
        ], { duration: outMs, easing: outEase, fill: 'forwards' });
        await leaving.finished.catch(() => {});
        detail.style.opacity = '0';
        detail.style.transform = `translate3d(${-direction * dist}px, 0, 0)`;
        leaving.cancel();

        commit();

        const img = detailImage();
        if (img) { img.style.transform = ''; img.style.opacity = ''; }

        const entering = detail.animate([
            { opacity: 0, transform: `translate3d(${direction * dist}px, 0, 0)` },
            { opacity: 1, transform: 'translate3d(0, 0, 0)' }
        ], { duration: inMs, easing: inEase, fill: 'forwards' });
        detail.style.opacity = '';
        detail.style.transform = '';
        await entering.finished.catch(() => {});
        entering.cancel();
        detail.style.willChange = '';
    };

            // Muestra la foto de `path`. `push` añade una entrada al historial (navegar) o no (Atrás / Adelante). Con el visor abierto, la foto
    // desliza de inmediato con la imagen vecina que ya está precargada (sin esperar a la ficha), desde donde el dedo la dejó (`from`) y
    // con su velocidad (`velocity`), y mientras tanto se pide la ficha para actualizar los textos y los datos.
    let busy = false;
    let queued = 0;
    const showPhoto = async (path, { push = true, viewer = isOpen(), direction = 1, from = 0, layer = null, velocity = 0 } = {}) => {
        const open = isOpen();
        const early = open && path === (direction < 0 ? detail.dataset.photoPrevious : detail.dataset.photoNext)
            ? (direction < 0 ? detail.dataset.photoPreviousImage : detail.dataset.photoNextImage) : '';
        const slide = early ? slideImage(imgEl, early, '', direction, { from, layer, velocity }) : null;
        if (!slide) layer?.remove();
        const doc = await fetchPhotoPage(path);
        const nextImg = $('.photo-detail__img', doc);
        if (!slide) await preloadImage(nextImg ? new URL(nextImg.getAttribute('src'), location.href).href : '');   // La foto nueva está lista antes de tocar nada.
        pageCache.delete(path);
        const commit = () => {
            applyPhoto(doc);
            if (push) history.pushState(null, '', path + (viewer ? '?viewer=1' : ''));
            document.dispatchEvent(new CustomEvent('photo:changed', { detail: { slug: document.body.dataset.openSlug || '' } }));
        };
        if (open) {
            commit();                                    // La ficha de debajo se actualiza sin que se vea.
            fillViewerText();
            const now = current();
            if (slide) { await slide; imgEl.alt = now.title; }
            else await slideImage(imgEl, now.full, now.title, direction);
        } else {
            await transitionDetail(commit, direction);
        }
        prefetchNeighbors();
    };

    // Foto anterior (-1) o siguiente (1). Pulsaciones rápidas: se encadenan, sin perder la última.
    const go = async (direction, options = {}) => {
        const path = direction < 0 ? detail.dataset.photoPrevious : detail.dataset.photoNext;
        if (!path) { options.layer?.remove(); return; }
        if (busy) { queued = direction; options.layer?.remove(); return; }
        busy = true;
        try {
            await showPhoto(path, { direction, ...options });
        } catch (_) {
            const url = new URL(path, location.origin);
            if (isOpen()) url.searchParams.set('viewer', '1');
            window.location.assign(url.href);          // Respaldo: la página completa, como siempre.
            return;
        } finally {
            busy = false;
        }
        if (queued) { const again = queued; queued = 0; go(again); }
    };

    // Seguir al dedo en el visor: la foto se mueve con el gesto y, al lado hacia el que va, se ve ya la vecina (precargada). La que se
    // va se oscurece y la que llega se ilumina según avanza el gesto, igual que en el deslizamiento automático.
    const stage = $('.lightbox__stage', lightbox);
    let peek = null;
    const stageWidth = () => stage?.clientWidth || window.innerWidth;
    const peekFor = (direction) => {
        if (peek && peek.direction === direction) return peek;
        peek?.el.remove(); peek = null;
        const src = direction > 0 ? detail.dataset.photoNextImage : detail.dataset.photoPreviousImage;
        if (!src) return null;
        const el = document.createElement('img');
        el.className = 'lightbox__img lightbox__img--incoming is-shown';
        el.alt = '';
        el.src = src;
        imgEl.insertAdjacentElement('afterend', el);
        peek = { el, direction };
        return peek;
    };
    const follow = (dx) => {
        if (!isOpen() || busy) return;
        const direction = dx < 0 ? 1 : -1;
        imgEl.getAnimations?.().forEach(animation => animation.cancel());
        imgEl.style.transition = 'none';
        const next = peekFor(direction);
        if (next) stage?.classList.add('photo-sliding');
        if (!next) { imgEl.style.transform = `translate3d(${dx / 3}px, 0, 0)`; imgEl.style.filter = ''; return; }    // Sin foto hacia ese lado: la foto se resiste.
        const progress = Math.min(1, Math.abs(dx) / stageWidth());
        imgEl.style.transform = `translate3d(${dx}px, 0, 0)`;
        imgEl.style.filter = `brightness(${(1 - (1 - SLIDE_DIM) * progress).toFixed(3)})`;
        next.el.style.transform = `translate3d(${dx + direction * stageWidth()}px, 0, 0)`;
        next.el.style.filter = `brightness(${(0.75 + 0.25 * progress).toFixed(3)})`;
    };
    const release = (dx) => {                                    // El gesto no llega a pasar de foto: todo vuelve a su sitio.
        const taken = peek; peek = null;
        const timing = { duration: 320, easing: 'cubic-bezier(.16, 1, .3, 1)' };
        const progress = Math.min(1, Math.abs(dx) / stageWidth());
        imgEl.style.transition = '';
        imgEl.style.transform = '';
        imgEl.style.filter = '';
        if (dx && imgEl.animate) imgEl.animate([
            { transform: `translate3d(${taken ? dx : dx / 3}px, 0, 0)`, filter: `brightness(${taken ? 1 - (1 - SLIDE_DIM) * progress : 1})` },
            { transform: 'translate3d(0, 0, 0)', filter: 'brightness(1)' }], timing);
        if (taken) {
            const rest = taken.direction * stageWidth();
            const back = taken.el.animate?.([{ transform: `translate3d(${dx + rest}px, 0, 0)` }, { transform: `translate3d(${rest}px, 0, 0)` }], timing);
            if (back) back.finished.catch(() => {}).finally(() => { taken.el.remove(); stage?.classList.remove('photo-sliding'); });
            else { taken.el.remove(); stage?.classList.remove('photo-sliding'); }
        } else stage?.classList.remove('photo-sliding');
    };
    const swipe = (direction, dx, velocity = 0) => {
        if (!isOpen()) { go(direction); return; }
        const layer = peek && peek.direction === direction ? peek.el : null;
        if (layer) peek = null;
        if (!layer && !(direction < 0 ? detail.dataset.photoPrevious : detail.dataset.photoNext)) { release(dx); return; }
        imgEl.style.filter = '';
        go(direction, { from: layer ? dx : 0, layer, velocity: layer ? velocity : 0 });
    };

    // Deslizar hacia abajo (o arriba) para cerrar el visor: la foto sigue al dedo, se encoge un poco y el fondo negro se aclara dejando
    // ver la ficha. Al soltar lejos (o rápido) el visor se cierra con la foto saliendo; si no, todo vuelve a su sitio.
    const CLOSE_DISTANCE = 110;
    const chrome = () => [...lightbox.querySelectorAll('.lightbox__close, .lightbox__nav')];
    const pull = (dy) => {
        if (!isOpen() || busy) return;
        const amount = Math.min(1, Math.abs(dy) / 420);
        imgEl.getAnimations?.().forEach(animation => animation.cancel());
        imgEl.style.transition = 'none';
        imgEl.style.transform = `translate3d(0, ${dy}px, 0) scale(${(1 - amount * 0.22).toFixed(3)})`;
        lightbox.style.backgroundColor = `rgba(0, 0, 0, ${(1 - amount * 0.85).toFixed(3)})`;
        chrome().forEach(control => { control.style.transition = 'none'; control.style.opacity = String(Math.max(0, 1 - amount * 3)); });
    };
    const resetPull = () => {
        imgEl.style.transition = ''; imgEl.style.transform = '';
        lightbox.style.backgroundColor = '';
        chrome().forEach(control => { control.style.transition = ''; control.style.opacity = ''; });
    };
    const drop = (dy, vy) => {
        if (!isOpen()) return;
        const amount = Math.min(1, Math.abs(dy) / 420);
        const scale = 1 - amount * 0.22;
        const closing = Math.abs(dy) > CLOSE_DISTANCE || (Math.abs(vy) > 0.6 && Math.abs(dy) > 30);
        if (!imgEl.animate) { resetPull(); if (closing) closeFs(); return; }
        if (!closing) {
            const timing = { duration: 340, easing: 'cubic-bezier(.16, 1, .3, 1)' };
            imgEl.animate([{ transform: `translate3d(0, ${dy}px, 0) scale(${scale})` }, { transform: 'translate3d(0, 0, 0) scale(1)' }], timing);
            lightbox.animate([{ backgroundColor: `rgba(0, 0, 0, ${1 - amount * 0.85})` }, { backgroundColor: 'rgba(0, 0, 0, 1)' }], timing);
            resetPull();
            return;
        }
        const sign = dy < 0 ? -1 : 1;
        const timing = { duration: 260, easing: 'cubic-bezier(.4, 0, 1, 1)', fill: 'forwards' };
        const out = imgEl.animate([
            { transform: `translate3d(0, ${dy}px, 0) scale(${scale})`, opacity: 1 },
            { transform: `translate3d(0, ${dy + sign * window.innerHeight * 0.5}px, 0) scale(${scale * 0.9})`, opacity: 0 }], timing);
        lightbox.animate([{ backgroundColor: `rgba(0, 0, 0, ${1 - amount * 0.85})` }, { backgroundColor: 'rgba(0, 0, 0, 0)' }], timing);
        out.finished.catch(() => {}).finally(() => {
            closeFs();
            out.cancel();
            lightbox.getAnimations().forEach(animation => animation.cancel());
            resetPull();
        });
    };

    // On the detail page, keep image, arrows and text together while swiping.
    // The gesture chooses the next photo; the complete detail then fades as one.

    const openFs = (nativeFullscreen = true) => {
        const { full, title } = current();
        if (!full) return;
        imgEl.src = full;
        imgEl.alt = title;
        fillViewerText();
        lightbox.classList.add('is-open');
        lightbox.classList.add('lightbox--immersive'); // solo foto + X, a pantalla completa
        document.body.classList.add('photo-immersive-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => imgEl.classList.add('is-shown'));
        if (nativeFullscreen !== false) requestNativeFs(lightbox);
        document.documentElement.classList.remove('viewer-pending');
    };

    const closeFs = (syncUrl = true) => {
        exitNativeFs();
        lightbox.style.backgroundColor = '';
        imgEl.style.filter = '';
        lightbox.querySelectorAll('.lightbox__close, .lightbox__nav').forEach(control => { control.style.transition = ''; control.style.opacity = ''; });
        lightbox.classList.remove('is-open');
        lightbox.classList.remove('lightbox--immersive');
        document.body.classList.remove('photo-immersive-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        imgEl.classList.remove('is-shown');
        lightbox.querySelectorAll('.lightbox__img--incoming').forEach(layer => layer.remove());
        stage?.classList.remove('photo-sliding');
        peek = null;
        imgEl.getAnimations?.().forEach(animation => animation.cancel());
        imgEl.style.transform = ''; imgEl.style.transition = '';
        const url = new URL(location.href);
        if (syncUrl && url.searchParams.get('viewer') === '1') {
            url.searchParams.delete('viewer');
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        }
    };

    // ---- Pantalla completa nativa del navegador (edge-to-edge) ----
    const requestNativeFs = (el) => {
        const fn = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
        if (!fn) return;
        try { const p = fn.call(el); if (p && p.catch) p.catch(() => {}); } catch (e) {}
    };
    const exitNativeFs = () => {
        const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
        if (!fsEl) return;
        const fn = document.exitFullscreen || document.webkitExitFullscreen;
        if (fn) { try { fn.call(document); } catch (e) {} }
    };
    // Si el usuario sale de pantalla completa (Esc/gesto), cierra el visor
    const onFsChange = () => {
        const fsEl = document.fullscreenElement || document.webkitFullscreenElement;
        if (!fsEl && isOpen()) closeFs();
    };
    document.addEventListener('fullscreenchange', onFsChange);
    document.addEventListener('webkitfullscreenchange', onFsChange);

    // Los botones de ampliar y las flechas de la ficha se sustituyen al cambiar de foto: se gestionan por delegación.
    detail.addEventListener('click', (event) => {
        const arrow = event.target.closest('a.photo-detail__nav');
        if (arrow) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            go(arrow.classList.contains('photo-detail__nav--previous') ? -1 : 1);
            return;
        }
        if (event.target.closest('[data-zoom-slug]')) openFs();
    });
    if (closeBtn) closeBtn.addEventListener('click', () => closeFs());
    if (prevBtn) prevBtn.addEventListener('click', () => go(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => go(1));
    bindPhotoNavigation($('.photo-detail__btn', detail), (direction) => go(direction));
    bindPhotoNavigation(stage, swipe, { follow, release, pull, drop });
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox || e.target.classList.contains('lightbox__stage')) closeFs();
    });
    document.addEventListener('keydown', (e) => {
        if (e.target.closest?.('input, textarea, select, [contenteditable="true"]') || e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.key === 'Escape' && isOpen()) closeFs();
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            go(e.key === 'ArrowLeft' ? -1 : 1);
        }
    });

    // Atrás / Adelante del navegador: se muestra la foto de esa dirección (y el visor, si la dirección lo lleva) sin recargar.
    window.addEventListener('popstate', async () => {
        if (!/^\/foto\/[^/]+/.test(location.pathname)) { location.reload(); return; }
        const wantsViewer = new URL(location.href).searchParams.get('viewer') === '1';
        try {
            await showPhoto(location.pathname, { push: false, viewer: wantsViewer });
            if (wantsViewer && !isOpen()) openFs(false);
            else if (!wantsViewer && isOpen()) closeFs(false);
        } catch (_) { location.reload(); }
    });

    if (new URL(location.href).searchParams.get('viewer') === '1') openFs(false);
    else document.documentElement.classList.remove('viewer-pending');
    prefetchNeighbors();
};
