/* Funciones de web app: favoritos, compartir, presentación y continuidad. */
import { $, $$, CARDS, prefersReducedMotion } from './helpers.js';
import { bindPhotoNavigation } from './photo-gestures.js';

/* ---------- Funciones de web app: favoritos, compartir, presentación y continuidad ---------- */
export const initAppPhotoFeatures = () => {
    const FAVORITES_KEY = 'djl-photo-favorites-v1';
    const readFavorites = () => {
        try { return new Set(JSON.parse(localStorage.getItem(FAVORITES_KEY) || '[]')); }
        catch (_) { return new Set(); }
    };
    const writeFavorites = set => {
        try { localStorage.setItem(FAVORITES_KEY, JSON.stringify(Array.from(set))); } catch (_) {}
    };
    let favorites = readFavorites();
    let favoritesOnly = false;
    const favoriteButtons = () => $$('[data-favorite-photo], [data-favorite-current]');
    const currentSlug = () => {
        const detail = $('.photo-detail');
        if (detail) {
            const match = location.pathname.match(/^\/foto\/([^/?#]+)/);
            return match ? decodeURIComponent(match[1]) : '';
        }
        const open = $('.lightbox.is-open');
        if (open) {
            const match = location.pathname.match(/^\/foto\/([^/?#]+)/);
            return match ? decodeURIComponent(match[1]) : '';
        }
        return '';
    };
    const refreshFavoriteButtons = () => {
        favoriteButtons().forEach(btn => {
            const slug = btn.dataset.favoritePhoto || currentSlug();
            const active = !!slug && favorites.has(slug);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.classList.toggle('is-liked', active);
            btn.textContent = active ? '♥ Favorita' : '♡ Favorita';
        });
        heartButtons().forEach(button => {
            const active = favorites.has(heartSlug(button));
            button.classList.toggle('is-liked', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (button.firstChild?.nodeType === 3) button.firstChild.textContent = active ? '♥ ' : '♡ ';
        });
        heartDisplays().forEach(display => {
            const active = favorites.has(display.dataset.heartDisplay || '');
            display.classList.toggle('is-liked', active);
            if (display.firstChild?.nodeType === 3) display.firstChild.textContent = active ? '♥ ' : '♡ ';
        });
        $$('.card').forEach(card => card.classList.toggle('is-favorite', favorites.has(card.dataset.slug || '')));
    };
    const busyHearts = new Set();
    const showHeartError = (control, message) => {
        const container = control?.closest('.photo-app-actions') || $('.photo-detail .photo-app-actions') || $('.lightbox.is-open .photo-app-actions') || $('.gallery-app-tools');
        if (!container) return;
        let note = $('.photo-like-error', container);
        if (!note) {
            note = document.createElement('span');
            note.className = 'photo-like-error';
            note.setAttribute('role', 'alert');
            container.append(note);
        }
        note.textContent = message || 'No se pudo guardar el corazón. Inténtalo otra vez.';
    };
    const toggleLike = async (slug, control) => {
        if (!slug || busyHearts.has(slug)) return;
        busyHearts.add(slug);
        const controls = [...favoriteButtons(), ...heartButtons()]
            .filter(button => (button.dataset.favoritePhoto || button.dataset.heartPhoto || currentSlug()) === slug);
        controls.forEach(button => button.disabled = true);
        try {
            const action = favorites.has(slug) ? 'unlike' : 'like';
            const body = new URLSearchParams({ slug, action });
            const response = await fetch('/hearts.php', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                body,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo guardar el corazón.');
            if (data.liked) favorites.add(slug); else favorites.delete(slug);
            writeFavorites(favorites);
            paintHeart(slug, data.count);
            refreshFavoriteButtons();
            const note = control?.closest('.photo-app-actions')?.querySelector('.photo-like-error');
            if (note) note.remove();
            if (favoritesOnly) {
                document.dispatchEvent(new CustomEvent('favorites:changed'));
                applyFavoritesFilter();
            }
            if (data.liked && control) {
                control.classList.remove('is-heart-pulse');
                void control.offsetWidth;
                control.classList.add('is-heart-pulse');
            }
        } catch (error) {
            showHeartError(control, error?.message);
        } finally {
            controls.forEach(button => button.disabled = false);
            busyHearts.delete(slug);
        }
    };
    const applyFavoritesFilter = () => {
        let visible = 0;
        $$('.card').forEach(card => {
            const show = !card.classList.contains('is-hidden') && (!favoritesOnly || favorites.has(card.dataset.slug || ''));
            card.hidden = !show;
            if (show) visible++;
        });
        const toggle = $('#favoritesToggle');
        if (toggle) {
            toggle.setAttribute('aria-pressed', favoritesOnly ? 'true' : 'false');
            toggle.textContent = favoritesOnly ? '♥ Ver todas' : '♡ Favoritas';
        }
        let empty = $('#favoritesEmpty');
        if (favoritesOnly && visible === 0) {
            if (!empty) {
                empty = document.createElement('p'); empty.id = 'favoritesEmpty'; empty.className = 'favorites-empty';
                $('#favoritesToggle')?.closest('.gallery-app-tools')?.insertAdjacentElement('afterend', empty);
            }
            if (empty) { empty.textContent = favorites.size ? 'No hay favoritas dentro de este filtro.' : 'Todavía no has guardado ninguna fotografía como favorita.'; empty.hidden = false; }
        } else if (empty) empty.hidden = true;
    };
    document.addEventListener('click', async event => {
        const favorite = event.target.closest('[data-favorite-photo], [data-favorite-current]');
        if (favorite) {
            event.preventDefault();
            await toggleLike(favorite.dataset.favoritePhoto || currentSlug(), favorite);
            return;
        }
        const heart = event.target.closest('[data-heart-photo], [data-heart-current]');
        if (heart) {
            event.preventDefault();
            await toggleLike(heart.dataset.heartPhoto || currentSlug(), heart);
            return;
        }
        const share = event.target.closest('[data-native-share], [data-native-share-current]');
        if (share) {
            event.preventDefault();
            const slug = currentSlug();
            const card = slug ? $$('.card').find(item => item.dataset.slug === slug) : null;
            const url = share.dataset.shareUrl || (slug ? location.origin + '/foto/' + encodeURIComponent(slug) : location.href);
            const title = share.dataset.shareTitle || card?.dataset.title || document.title;
            if (navigator.share) {
                try { await navigator.share({title, url}); return; } catch (error) { if (error?.name === 'AbortError') return; }
            }
            try { await navigator.clipboard.writeText(url); share.textContent = 'Enlace copiado'; setTimeout(() => share.textContent = 'Compartir', 1600); } catch (_) {}
        }
    });
    const toggle = $('#favoritesToggle');
    if (toggle) toggle.addEventListener('click', () => {
        favoritesOnly = !favoritesOnly;
        document.body.classList.toggle('favorites-only', favoritesOnly);
        document.dispatchEvent(new CustomEvent('favorites:changed'));
        applyFavoritesFilter();
    });
    document.addEventListener('gallery:rendered', () => {
        if (favoritesOnly) applyFavoritesFilter();
    });
    const detailSlug = currentSlug();
    // La función «Continúa donde lo dejaste» se eliminó: se borra lo que guardaba.
    try { localStorage.removeItem('djl-last-photo-v1'); sessionStorage.removeItem('djl-last-photo-offered'); } catch (_) {}

    const preloadAround = slug => {
        const cards = $$('.card');
        const i = cards.findIndex(card => card.dataset.slug === slug);
        const sources = i >= 0
            ? [cards[i - 1]?.dataset.full, cards[i + 1]?.dataset.full]
            : (() => {
                const detail = $('.photo-detail');
                return detail ? [detail.dataset.photoPreviousImage, detail.dataset.photoNextImage] : [];
            })();
        sources.filter(Boolean).forEach(src => {
            const img = new Image();
            img.decoding = 'async';
            img.src = src;
        });
    };
    if (detailSlug) preloadAround(detailSlug);

    const slideButton = $('#slideshowStart');
    if (slideButton) slideButton.addEventListener('click', () => {
        const cards = $$(CARDS).filter(card => !card.hidden && !card.classList.contains('is-hidden') && card.dataset.slug);
        if (!cards.length) return;
        let index = 0, timer = 0, paused = false;
        const overlay = document.createElement('div');
        overlay.className = 'slideshow-viewer';
        overlay.innerHTML = '<button class="slideshow-viewer__close" type="button" aria-label="Cerrar presentación">×</button><img class="slideshow-viewer__img" alt=""><div class="slideshow-viewer__caption"></div><div class="slideshow-viewer__controls"><button type="button" data-slide-prev>‹</button><button type="button" data-slide-pause>Ⅱ</button><button type="button" data-slide-next>›</button></div>';
        document.body.append(overlay);
        document.body.classList.add('slideshow-open');
        const image = $('.slideshow-viewer__img', overlay);
        const caption = $('.slideshow-viewer__caption', overlay);
        const render = () => {
            const card = cards[index];
            image.classList.remove('is-ready');
            image.src = card.dataset.full;
            image.alt = card.dataset.title || 'Fotografía';
            caption.textContent = card.dataset.title || '';
            image.onload = () => image.classList.add('is-ready');
            preloadAround(card.dataset.slug || '');
        };
        const step = delta => { index = (index + delta + cards.length) % cards.length; render(); };
        const restart = () => { clearInterval(timer); if (!paused && !prefersReducedMotion) timer = window.setInterval(() => step(1), 5000); };
        let closing = false;
        const onFullscreenChange = () => {
            const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
            if (!closing && !fullscreenElement && overlay.isConnected) close(false);
        };
        const close = (exitFullscreen = true) => {
            if (closing) return;
            closing = true;
            clearInterval(timer);
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('fullscreenchange', onFullscreenChange);
            document.removeEventListener('webkitfullscreenchange', onFullscreenChange);
            const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
            if (exitFullscreen && fullscreenElement === overlay) {
                const exit = document.exitFullscreen || document.webkitExitFullscreen;
                if (exit) { try { const p = exit.call(document); if (p?.catch) p.catch(() => {}); } catch (_) {} }
            }
            overlay.remove();
            document.body.classList.remove('slideshow-open');
        };
        const togglePause = button => { paused = !paused; button.textContent = paused ? '▶' : 'Ⅱ'; restart(); };
        const onKey = event => {
            if (event.key === 'Escape') close();
            else if (event.key === 'ArrowLeft') { step(-1); restart(); }
            else if (event.key === 'ArrowRight') { step(1); restart(); }
            else if (event.key === ' ') { event.preventDefault(); togglePause($('[data-slide-pause]', overlay)); }
        };
        $('.slideshow-viewer__close', overlay).addEventListener('click', close);
        $('[data-slide-prev]', overlay).addEventListener('click', () => { step(-1); restart(); });
        $('[data-slide-next]', overlay).addEventListener('click', () => { step(1); restart(); });
        $('[data-slide-pause]', overlay).addEventListener('click', event => togglePause(event.currentTarget));
        bindPhotoNavigation(overlay, direction => { step(direction); restart(); });
        document.addEventListener('keydown', onKey);
        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', onFullscreenChange);
        render(); restart();
        const requestFs = overlay.requestFullscreen || overlay.webkitRequestFullscreen;
        if (requestFs) { try { const p = requestFs.call(overlay); if (p?.catch) p.catch(() => {}); } catch (_) {} }
    });
    const heartButtons = () => $$('[data-heart-photo], [data-heart-current]');
    const heartDisplays = () => $$('[data-heart-display]');
    const heartSlug = button => button.dataset.heartPhoto || currentSlug();
    const heartCounts = new Map();
    const paintHeart = (slug, count) => {
        const numericCount = Math.max(0, Number(count) || 0);
        heartCounts.set(slug, numericCount);
        const formatted = numericCount.toLocaleString('es-ES');
        heartButtons().forEach(button => {
            if (heartSlug(button) !== slug) return;
            const counter = $('[data-heart-count]', button);
            if (counter) counter.textContent = formatted;
            button.setAttribute('aria-label', (favorites.has(slug) ? 'Quitar corazón. ' : 'Dar un corazón. ') + formatted + ' corazones');
        });
        heartDisplays().forEach(display => {
            if (display.dataset.heartDisplay !== slug) return;
            const counter = $('[data-heart-count]', display);
            if (counter) counter.textContent = formatted;
        });
    };
    const loadHearts = async () => {
        const slugs = Array.from(new Set([
            ...heartButtons().map(heartSlug),
            ...heartDisplays().map(display => display.dataset.heartDisplay)
        ].filter(Boolean)));
        if (!slugs.length) return;
        // Favoritos guardados antes de esta versión: registrarlos una sola vez.
        const migrationKey = 'djl-photo-favorites-migrated-v2';
        let migrated = new Set();
        try { migrated = new Set(JSON.parse(localStorage.getItem(migrationKey) || '[]')); } catch (_) {}
        for (const slug of slugs) {
            if (!favorites.has(slug) || migrated.has(slug)) continue;
            try {
                const response = await fetch('/hearts.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    body: new URLSearchParams({ slug, action: 'like' }),
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo migrar el favorito.');
                migrated.add(slug);
                paintHeart(slug, data.count);
                try { localStorage.setItem(migrationKey, JSON.stringify([...migrated])); } catch (_) {}
            } catch (error) { showHeartError(null, error?.message); }
        }
        for (let offset = 0; offset < slugs.length; offset += 40) {
            const batch = slugs.slice(offset, offset + 40);
            try {
                const response = await fetch('/hearts.php?slugs=' + encodeURIComponent(batch.join(',')), {
                    cache: 'no-store', credentials: 'same-origin'
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudieron cargar los corazones.');
                for (const slug of batch) {
                    if (data.liked?.[slug]) favorites.add(slug);
                    else if (migrated.has(slug) || !favorites.has(slug)) favorites.delete(slug);
                    paintHeart(slug, data.counts?.[slug] || 0);
                }
            } catch (error) { showHeartError(null, error?.message); }
        }
        writeFavorites(favorites);
        refreshFavoriteButtons();
    };
    document.addEventListener('photo:changed', event => {
        const slug = event.detail?.slug;
        if (!slug) return;
        const known = heartCounts.has(slug);
        paintHeart(slug, heartCounts.get(slug) || 0);
        refreshFavoriteButtons();
        if (!known) loadHearts();     // Foto nueva (cambio en el sitio): cargar su contador y su estado.
    });
    loadHearts();
    refreshFavoriteButtons();
};
