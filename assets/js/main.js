/* =========================================================
   Kookye Cat Gallery — Kookye Cat Gallery
   Preloader · scroll suave · parallax · FLIP lightbox ·
   tilt 3D · blur-up · reveal · cursor · barra de scroll
   ========================================================= */

(() => {
    'use strict';

    // Marca que JS está activo (para revelados que no deben dejar contenido oculto sin JS)
    document.documentElement.classList.add('js');

    /* ---------- Helpers ---------- */
    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    // Tarjetas de foto: las de la galería estándar (.card) y las de la galería premium «Estilo Baraja» (.deck__card).
    // La raíz de la galería es #masonry (estándar) o #deck (premium). Ver docs/galerias-premium.md.
    const CARDS = '.card, .deck__card';
    const galleryRoot = () => $('#masonry') || $('#deck');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    // Horizontal photo gestures navigate; browser page zoom is disabled outside the editor.
    const bindPhotoNavigation = (surface, navigate) => {
        if (!surface) return;
        const pointers = new Set();
        let start = null;
        let suppressClickUntil = 0;
        surface.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            const control = event.target.closest('a, button, input, select, textarea');
            if (control && !control.matches('.photo-detail__btn')) return;
            pointers.add(event.pointerId);
            if (pointers.size !== 1) { start = null; return; }
            start = { id: event.pointerId, x: event.clientX, y: event.clientY };
            try { surface.setPointerCapture(event.pointerId); } catch (_) {}
        });
        surface.addEventListener('pointerup', (event) => {
            const gesture = start;
            pointers.delete(event.pointerId);
            start = null;
            if (!gesture || gesture.id !== event.pointerId) return;
            const dx = event.clientX - gesture.x;
            const dy = event.clientY - gesture.y;
            if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
            suppressClickUntil = performance.now() + 400;
            navigate(dx > 0 ? -1 : 1);
        });
        const cancel = (event) => { pointers.delete(event.pointerId); start = null; };
        surface.addEventListener('pointercancel', cancel);
        surface.addEventListener('lostpointercapture', cancel);
        surface.addEventListener('click', (event) => {
            if (performance.now() < suppressClickUntil) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
        surface.addEventListener('dragstart', (event) => event.preventDefault());
    };

    /* ---------- Preloader ---------- */
    const initPreloader = () => {
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

    /* ---------- Barra de progreso de scroll ---------- */
    const initScrollProgress = () => {
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

    /* ---------- Parallax del hero (escritorio) ---------- */
    const initHeroParallax = () => {
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

    /* ---------- Reveal on scroll ---------- */
    const initReveal = () => {
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

    /* ---------- Lazy / blur-up ---------- */
    const initLazyImages = () => {
        const imgs = $$('.card__img');
        const markLoaded = (img) => {
            if (img.complete && img.naturalWidth > 0) {
                img.classList.add('is-loaded');
            } else {
                img.addEventListener('load', () => img.classList.add('is-loaded'), { once: true });
                img.addEventListener('error', () => img.classList.add('is-loaded'), { once: true });
            }
        };
        if (!('IntersectionObserver' in window)) { imgs.forEach(markLoaded); return; }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) { markLoaded(entry.target); io.unobserve(entry.target); }
            });
        }, { rootMargin: '300px 0px' });
        imgs.forEach(img => io.observe(img));
    };

    /* ---------- Tilt 3D + reflejo (escritorio) ---------- */
    const initTilt = () => {
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

    /* ---------- Compartir URL de una foto ---------- */
    // Rellena los hrefs de un bloque .share--js con la URL de la foto actual.
    const updateShare = (container, url, title) => {
        if (!container) return;
        const u = encodeURIComponent(url);
        const t = encodeURIComponent((title || window.siteText("Fotografía de gatos")) + window.siteText(" · Kookye Cat Gallery"));
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
    const initCopyLinks = () => {
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

    /* ---------- Funciones de web app: favoritos, compartir, presentación y continuidad ---------- */
    const initAppPhotoFeatures = () => {
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
            paintHeart(slug, heartCounts.get(slug) || 0);
            refreshFavoriteButtons();
        });
        loadHearts();
        refreshFavoriteButtons();
    };

    /* ---------- Lightbox con FLIP ---------- */
    const initLightbox = () => {
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
        const getCards = () => $('.card:not(.is-hidden):not([hidden])');

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
        let navigationToken = 0;

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
                if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
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
                imgEl.alt = card.dataset.title || window.siteText("Fotografía de gatos");
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
                imgEl.alt = card.dataset.title || window.siteText("Fotografía de gatos");
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
            imgEl.classList.remove('is-shown');
            const full = card.dataset.full;
            const pre = new Image();
            const token = ++navigationToken;
            pre.onload = () => {
                if (token !== navigationToken) return;
                imgEl.src = full;
                imgEl.alt = card.dataset.title || window.siteText("Fotografía de gatos");
                requestAnimationFrame(() => imgEl.classList.add('is-shown'));
            };
            pre.src = full;
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

    /* ---------- Página de foto dedicada (/foto/slug) ---------- */
    // Visor a pantalla completa autónomo: no depende de la galería (que aquí no existe).
    const initPhotoPage = () => {
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

        const hasNeighbors = Boolean(detail.dataset.photoPrevious || detail.dataset.photoNext);
        if (prevBtn) { prevBtn.hidden = !hasNeighbors; prevBtn.disabled = !detail.dataset.photoPrevious; }
        if (nextBtn) { nextBtn.hidden = !hasNeighbors; nextBtn.disabled = !detail.dataset.photoNext; }

        // Each photo is fetched as a fresh server page, keeping metadata and admin actions current.
        const go = (direction) => {
            const path = direction < 0 ? detail.dataset.photoPrevious : detail.dataset.photoNext;
            if (!path) return;
            const url = new URL(path, location.origin);
            if (lightbox.classList.contains('is-open')) url.searchParams.set('viewer', '1');
            window.location.assign(url.href);
        };

        const detailImg  = $('.photo-detail__img', detail);
        const titleNode  = $('.photo-detail__title', detail);
        const descNode   = $('.photo-detail__desc', detail);
        const coordsNode = $('.photo-detail__coords', detail);
        const full = detailImg ? detailImg.src : '';
        const title = titleNode ? titleNode.textContent.trim() : '';
        const desc  = descNode ? descNode.textContent.trim() : '';

        const openFs = (nativeFullscreen = true) => {
            if (!full) return;
            imgEl.src = full;
            imgEl.alt = title;
            if (titleEl)   titleEl.textContent = title;
            if (descEl)    descEl.textContent = desc;
            if (counterEl) counterEl.textContent = '';
            if (coordsEl && coordsTxt) {
                if (coordsNode) {
                    coordsTxt.textContent = coordsNode.textContent.trim();
                    coordsEl.href = coordsNode.href;
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
            lightbox.classList.add('is-open');
            lightbox.classList.add('lightbox--immersive'); // solo foto + X, a pantalla completa
            document.body.classList.add('photo-immersive-open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            requestAnimationFrame(() => imgEl.classList.add('is-shown'));
            if (nativeFullscreen !== false) requestNativeFs(lightbox);
        };

        const closeFs = () => {
            exitNativeFs();
            lightbox.classList.remove('is-open');
            lightbox.classList.remove('lightbox--immersive');
            document.body.classList.remove('photo-immersive-open');
            lightbox.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            imgEl.classList.remove('is-shown');
            const url = new URL(location.href);
            if (url.searchParams.get('viewer') === '1') {
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
            if (!fsEl && lightbox.classList.contains('is-open')) closeFs();
        };
        document.addEventListener('fullscreenchange', onFsChange);
        document.addEventListener('webkitfullscreenchange', onFsChange);

        $$('[data-zoom-slug]', detail).forEach(btn => btn.addEventListener('click', openFs));
        if (closeBtn) closeBtn.addEventListener('click', closeFs);
        if (prevBtn) prevBtn.addEventListener('click', () => go(-1));
        if (nextBtn) nextBtn.addEventListener('click', () => go(1));
        bindPhotoNavigation($('.photo-detail__btn', detail), go);
        bindPhotoNavigation($('.lightbox__stage', lightbox), (direction) => {
            if (lightbox.classList.contains('is-open')) go(direction);
        });
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox || e.target.classList.contains('lightbox__stage')) closeFs();
        });
        document.addEventListener('keydown', (e) => {
            if (e.target.closest?.('input, textarea, select, [contenteditable="true"]') || e.ctrlKey || e.metaKey || e.altKey) return;
            if (e.key === 'Escape' && lightbox.classList.contains('is-open')) closeFs();
            if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                e.preventDefault();
                go(e.key === 'ArrowLeft' ? -1 : 1);
            }
        });
        if (new URL(location.href).searchParams.get('viewer') === '1') openFs(false);
    };

    /* ---------- Botón volver arriba ---------- */
    const initTopButton = () => {
        const btn = $('.top-btn');
        if (!btn) return;
        const onScroll = () => {
            btn.classList.toggle('is-visible', window.scrollY > window.innerHeight * 0.6);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        onScroll();
    };

    /* ---------- Cursor personalizado ---------- */
    const initCursor = () => {
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

    /* ---------- Galería: filtrado + paginación cliente ---------- */
    const escHtml = s => String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const initGallery = () => {
        const gallery = $('#masonry');
        if (!gallery) return;
        const getGalleryDesign = () => window.matchMedia('(max-width: 768px)').matches
            ? document.body.dataset.galleryMobile : document.body.dataset.galleryDesktop;
        const initialCards = $$('.card', gallery || document);
        let galleryDesign = getGalleryDesign();
        document.body.dataset.galleryActive = galleryDesign;
        if (gallery && galleryDesign === 'category-rails') {
            const groups = new Map();
            initialCards.forEach(card => {
                const category = card.dataset.category || 'Otras';
                if (!groups.has(category)) groups.set(category, []);
                groups.get(category).push(card);
            });
            const fragment = document.createDocumentFragment();
            groups.forEach((cards, category) => {
                const row = document.createElement('section');
                row.className = 'category-rail';
                row.dataset.category = category;
                const heading = document.createElement('h2');
                heading.className = 'category-rail__title';
                heading.textContent = category;
                const track = document.createElement('div');
                track.className = 'category-rail__track';
                cards.forEach(card => track.append(card));
                const header = document.createElement('div');
                header.className = 'category-rail__header';
                const controls = document.createElement('div');
                controls.className = 'category-rail__controls';
                track.id = 'category-track-' + fragment.childElementCount;
                track.setAttribute('aria-label', category);
                const arrows = [-1, 1].map(direction => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'category-rail__arrow';
                    button.textContent = direction < 0 ? '‹' : '›';
                    button.setAttribute('aria-label', (direction < 0 ? 'Fotos anteriores de ' : 'Más fotos de ') + category);
                    button.setAttribute('aria-controls', track.id);
                    button.addEventListener('click', () => track.scrollBy({
                        left: direction * track.clientWidth * .85,
                        behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
                    }));
                    controls.append(button);
                    return button;
                });
                const updateArrows = () => {
                    arrows[0].disabled = track.scrollLeft < 2;
                    arrows[1].disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
                };
                track.addEventListener('scroll', updateArrows, { passive: true });
                row.addEventListener('rail:resize', updateArrows);
                header.append(heading, controls);
                row.append(header, track);
                fragment.append(row);
            });
            gallery.replaceChildren(fragment);
        }
        const allCards = initialCards;
        if (!allCards.length) return;

        const filterEl      = $('#categoriesFilter');
        const categorySelect = $('#mapCategory');
        const jsPagination  = $('#jsPagination');
        // Fotos por página: `photos_mobile` / `photos_desktop` del panel (data-photos-* en <body>); 0 = todas.
        // Es independiente de las columnas. El modo «filas por categoría» siempre muestra todas.
        const perPage = () => {
            if (getGalleryDesign() === 'category-rails') return Number.MAX_SAFE_INTEGER;
            const mobile = window.matchMedia('(max-width: 768px)').matches;
            const configured = Number(document.body.dataset[mobile ? 'photosMobile' : 'photosDesktop']);
            if (configured === 0) return Number.MAX_SAFE_INTEGER;
            return configured > 0 ? configured : (mobile ? 12 : 20);
        };
        let   currentCat    = '';
        let   currentPage   = 1;

        const getFiltered = () => {
            let cards = currentCat ? allCards.filter(c => c.dataset.category === currentCat) : allCards;
            if (document.body.classList.contains('favorites-only')) {
                let saved = [];
                try { saved = JSON.parse(localStorage.getItem('djl-photo-favorites-v1') || '[]'); } catch (_) {}
                const favorites = new Set(Array.isArray(saved) ? saved : []);
                cards = cards.filter(card => favorites.has(card.dataset.slug || ''));
            }
            return cards;
        };

        const pageRange = (current, total) => {
            if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
            const r = [1];
            if (current > 3) r.push('...');
            for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) r.push(i);
            if (current < total - 2) r.push('...');
            r.push(total);
            return r;
        };

        const scrollToGallery = () => {
            const el = document.getElementById('galeria');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        const renderPagination = (total, totalPages) => {
            if (!jsPagination) return;
            if (totalPages <= 1) {
                jsPagination.hidden = true;
                jsPagination.style.display = 'none';
                return;
            }
            jsPagination.hidden = false;
            jsPagination.style.display = '';

            const hasPrev = currentPage > 1;
            const hasNext = currentPage < totalPages;
            const label   = currentCat
                ? `${escHtml(window.siteText(total === 1 ? '{count} foto en' : '{count} fotos en').replace('{count}', total))} <em>${escHtml(currentCat)}</em>`
                : escHtml(window.siteText(total === 1 ? '{count} fotografía en total' : '{count} fotografías en total').replace('{count}', total));

            jsPagination.innerHTML = `
                <div class="pagination__controls">
                    <button class="pagination__nav pagination__nav--prev${hasPrev ? '' : ' pagination__nav--disabled'}"
                        ${hasPrev ? '' : 'disabled'} aria-label="${window.siteTextHTML("Página anterior")}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 6l-6 6 6 6"/></svg>
                        <span>${window.siteTextHTML("Anterior")}</span>
                    </button>
                    <div class="pagination__pages">
                        ${pageRange(currentPage, totalPages).map(n => n === '...'
                            ? `<span class="pagination__ellipsis">…</span>`
                            : `<button class="pagination__page${n === currentPage ? ' is-current' : ''}" data-page="${n}"
                                   ${n === currentPage ? 'aria-current="page"' : ''}>${n}</button>`
                        ).join('')}
                    </div>
                    <button class="pagination__nav pagination__nav--next${hasNext ? '' : ' pagination__nav--disabled'}"
                        ${hasNext ? '' : 'disabled'} aria-label="${window.siteTextHTML("Página siguiente")}">
                        <span>${window.siteTextHTML("Siguiente")}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                </div>
                <p class="pagination__info">${window.siteTextHTML("\n                    Página ")}<strong>${currentPage}</strong>${window.siteTextHTML(" de ")}${totalPages}
                    <span class="pagination__sep">·</span> ${label}
                </p>`;

            jsPagination.querySelector('.pagination__nav--prev')?.addEventListener('click', () => {
                if (currentPage > 1) { currentPage--; render(); scrollToGallery(); }
            });
            jsPagination.querySelector('.pagination__nav--next')?.addEventListener('click', () => {
                if (currentPage < totalPages) { currentPage++; render(); scrollToGallery(); }
            });
            jsPagination.querySelectorAll('.pagination__page:not(.is-current)').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentPage = parseInt(btn.dataset.page, 10);
                    render();
                    scrollToGallery();
                });
            });
        };

        const render = () => {
            const activeDesign = getGalleryDesign();
            if (activeDesign !== galleryDesign) {
                if (activeDesign === 'category-rails' || galleryDesign === 'category-rails') {
                    window.location.reload();
                    return;
                }
                galleryDesign = activeDesign;
                document.body.dataset.galleryActive = activeDesign;
            }
            const filtered   = getFiltered();
            const total      = filtered.length;
            const totalPages = Math.max(1, Math.ceil(total / perPage()));
            if (currentPage > totalPages) currentPage = 1;

            const start     = (currentPage - 1) * perPage();
            const pageCards = filtered.slice(start, start + perPage());

            // Oculta todo, muestra solo la página actual del filtro
            allCards.forEach(c => { c.classList.add('is-hidden'); c.hidden = true; });
            pageCards.forEach((c, i) => {
                c.classList.remove('is-hidden');
                c.hidden = false;
                // Fuerza el reveal si no lo tiene aún (entraron al DOM sin pasar por el viewport)
                if (!c.classList.contains('is-revealed')) {
                    setTimeout(() => c.classList.add('is-revealed'), i * 50);
                }
            });

            if (gallery && activeDesign === 'category-rails') {
                $$('.category-rail', gallery).forEach(row => {
                    row.hidden = !$('.card:not(.is-hidden)', row);
                });
            }
            layoutMasonryCards(gallery, activeDesign);

            renderPagination(total, totalPages);
            document.dispatchEvent(new CustomEvent('gallery:rendered', { detail: { category: currentCat, page: currentPage } }));
            if (window.photoMap) updateMapForCategory(currentCat);
        };

        document.addEventListener('favorites:changed', () => {
            currentPage = 1;
            render();
        });

        // Chips de categoría
        if (filterEl) {
            const chips = $$('.categories-filter__chip', filterEl);
            chips.forEach(chip => {
                chip.addEventListener('click', () => {
                    chips.forEach(c => c.classList.remove('is-active'));
                    chip.classList.add('is-active');
                    currentCat  = chip.dataset.category || '';
                    currentPage = 1;
                    render();
                });
            });
        }

        if (categorySelect) {
            categorySelect.addEventListener('change', () => {
                currentCat = categorySelect.value || '';
                currentPage = 1;
                render();
            });
        }

        // Si la URL es /foto/slug, muestra la página que contiene esa foto
        const openSlug = document.body.dataset.openSlug;
        if (openSlug) {
            const targetIdx = allCards.findIndex(c => c.dataset.slug === openSlug);
            if (targetIdx !== -1) {
                currentPage = Math.ceil((targetIdx + 1) / perPage());
            }
        }

        render(); // render inicial
        gallery?.addEventListener('load', event => {
            if (!event.target.matches('.card__img')) return;
            requestAnimationFrame(() => layoutMasonryCards(gallery, getGalleryDesign()));
        }, true);
        window.addEventListener('resize', () => {
            requestAnimationFrame(() => layoutMasonryCards(gallery, getGalleryDesign()));
        }, { passive: true });
        let lastGalleryWidth = gallery.clientWidth;
        new ResizeObserver(() => {
            if (gallery.clientWidth === lastGalleryWidth) return;
            lastGalleryWidth = gallery.clientWidth;
            layoutMasonryCards(gallery, getGalleryDesign());
        }).observe(gallery);
        window.matchMedia('(max-width: 768px)').addEventListener('change', () => {
            const nextDesign = getGalleryDesign();
            if (nextDesign === 'category-rails' || galleryDesign === 'category-rails') {
                window.location.reload();
                return;
            }
            galleryDesign = nextDesign;
            document.body.dataset.galleryActive = nextDesign;
            currentPage = 1;
            render();
        });
    };

    const layoutMasonryCards = (gallery, design) => window.layoutPhotoGallery?.(gallery, design);

    const imageToneCache = new WeakMap();
    const imageTone = image => {
        if (!image || !image.complete || !image.naturalWidth) return null;
        if (imageToneCache.has(image)) return imageToneCache.get(image);
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 24; canvas.height = 24;
            const context = canvas.getContext('2d', { willReadFrequently: true });
            if (!context) return null;
            context.drawImage(image, 0, 0, 24, 24);
            const pixels = context.getImageData(0, 0, 24, 24).data;
            let red = 0, green = 0, blue = 0, count = 0;
            for (let i = 0; i < pixels.length; i += 4) {
                const brightness = (pixels[i] + pixels[i + 1] + pixels[i + 2]) / 3;
                if (pixels[i + 3] < 180 || brightness < 18 || brightness > 242) continue;
                red += pixels[i]; green += pixels[i + 1]; blue += pixels[i + 2]; count++;
            }
            if (!count) return null;
            const tone = `rgb(${Math.round(red / count)}, ${Math.round(green / count)}, ${Math.round(blue / count)})`;
            imageToneCache.set(image, tone);
            return tone;
        } catch (_) { return null; }
    };

    const initMosaicColor = () => {
        const gallery = $('#masonry');
        const toneDesigns = ['mosaic', 'scattered'];
        if (!gallery || !toneDesigns.includes(document.body.dataset.galleryMobile) && !toneDesigns.includes(document.body.dataset.galleryDesktop)) return;
        const applyTone = card => {
            const tone = imageTone($('.card__img', card));
            if (tone) card.style.setProperty('--photo-tone', tone);
        };
        const intersections = new Map();
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => { if (entry.isIntersecting && !entry.target.hidden) intersections.set(entry.target, entry); else intersections.delete(entry.target); });
            if (!toneDesigns.includes(document.body.dataset.galleryActive)) return;
            const visible = [...intersections.values()].filter(entry => !entry.target.hidden).sort((a, b) => b.intersectionRatio - a.intersectionRatio);
            visible.forEach(entry => applyTone(entry.target));
            const active = visible[0];
            const tone = active && imageTone($('.card__img', active.target));
            if (tone && document.body.dataset.galleryActive === 'mosaic') {
                document.body.style.setProperty('--active-photo-tone', tone);
                document.body.dataset.activePhotoTone = 'true';
            }
        }, { threshold: [0.35, 0.6, 0.85] });
        $$('.card', gallery).forEach(card => {
            observer.observe(card);
            applyTone(card);
            $('.card__img', card)?.addEventListener('load', () => {
                applyTone(card);
                if (toneDesigns.includes(document.body.dataset.galleryActive)) {
                    observer.unobserve(card);
                    observer.observe(card);
                }
            });
        });
    };

    /* ---------- Mapa interactivo ---------- */
    let photoMap = null;
    const initMap = () => {
        const mapEl = $('#map');
        if (!mapEl) return;
        if (typeof L === 'undefined') {
            console.warn('Leaflet no cargó');
            return;
        }

        const cards = $$(CARDS);
        if (!cards.length) return;

        // Agrupa fotos por ubicación
        const locations = {};
        cards.forEach(card => {
            const lat = parseFloat(card.dataset.lat);
            const lng = parseFloat(card.dataset.lng);
            if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
                const key = `${lat},${lng}`;
                const cat = card.dataset.category || 'Otras';
                if (!locations[key]) locations[key] = { lat, lng, categories: {}, count: 0, cards: [] };
                locations[key].count++;
                locations[key].cards.push(card);
                locations[key].categories[cat] = (locations[key].categories[cat] || 0) + 1;
            }
        });

        if (Object.keys(locations).length === 0) return; // Sin ubicaciones válidas

        try {
            // Crea el mapa centrado en la primera foto con coordenadas
            const firstLocation = Object.values(locations)[0];
            photoMap = L.map(mapEl).setView([firstLocation.lat, firstLocation.lng], 9);

            // Tile layer oscuro
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
            }).addTo(photoMap);

            // Añade marcadores
            Object.values(locations).forEach(loc => {
                const marker = L.circleMarker([loc.lat, loc.lng], {
                    radius: 20,
                    fillColor: getComputedStyle(document.body).getPropertyValue('--accent').trim(),
                    color: getComputedStyle(document.body).getPropertyValue('--accent').trim(),
                    weight: 2,
                    opacity: 0.9,
                    fillOpacity: 0.85,
                }).addTo(photoMap);

                marker._photoCount = loc.count;
                marker._categories = loc.categories;
                marker._location = `${loc.lat},${loc.lng}`;

                marker.bindPopup(loc.count === 1
                    ? `<button class="map-popup__view" type="button">${window.siteTextHTML("1 foto")}<br><small>${window.siteTextHTML("(pincha para ver)")}</small></button>`
                    : `<div style="color: #0a0a0a; font-weight: 500; cursor: pointer;">
                        ${window.siteTextHTML("{count} fotos").replace("{count}", loc.count)}<br>
                        <small style="opacity: 0.7;">${window.siteTextHTML("(pincha para ver)")}</small>
                    </div>`);

                if (loc.count === 1) {
                    marker.on('popupopen', () => {
                        const button = marker.getPopup()?.getElement()?.querySelector('.map-popup__view');
                        if (!button) return;
                        button.onclick = () => {
                            marker.closePopup();
                            galleryRoot()?.dispatchEvent(new CustomEvent('map:open-photo', {
                                detail: { card: loc.cards[0] }
                            }));
                        };
                    });
                }

                // Al pinchar el marcador, filtra las fotos de esa ubicación
                marker.on('click', () => {
                    const cards = $$('.card');
                    const locKey = marker._location;
                    const activeChip = $('.categories-filter__chip.is-active');

                    cards.forEach(card => {
                        const cardLoc = `${card.dataset.lat},${card.dataset.lng}`;
                        if (cardLoc === locKey) {
                            card.classList.remove('is-hidden');
                        } else {
                            card.classList.add('is-hidden');
                        }
                    });

                    // Marca el chip "Todas" como activo visualmente (aunque no es exacto)
                    if (activeChip) activeChip.classList.remove('is-active');
                });
            });

            window.photoMap = photoMap;
        } catch (e) {
            console.error('Error inicializando mapa:', e);
        }
    };

    const updateMapForCategory = (category) => {
        if (!photoMap) return;
        photoMap.eachLayer(layer => {
            if (layer._photoCount !== undefined) {
                const hasCategory = category === '' || layer._categories[category];
                layer.setStyle({
                    opacity: hasCategory ? 0.9 : 0.2,
                    fillOpacity: hasCategory ? 0.85 : 0.1,
                });
            }
        });
    };

    /* ---------- Disuasión de descarga de imágenes ---------- */
    // Bloquea clic derecho y arrastre SOBRE imágenes (deja el resto de la web normal).
    // No es infalible (la imagen llega al navegador igual), pero frena al usuario casual.
    const initImageProtection = () => {
        const blockOnImg = (e) => {
            if (e.target && e.target.tagName === 'IMG') e.preventDefault();
        };
        document.addEventListener('contextmenu', blockOnImg); // clic derecho + pulsación larga (Android)
        document.addEventListener('dragstart', blockOnImg);   // arrastrar al escritorio
    };

    /* ---------- Init ---------- */
    const init = () => {
        initPreloader();
        initScrollProgress();
        initHeroParallax();
        initReveal();
        initLazyImages();
        initTilt();
        initLightbox();
        initTopButton();
        initCursor();
        initGallery();
        initMap();
        initPhotoPage();
        initImageProtection();
        initCopyLinks();
        initAppPhotoFeatures();
        initMosaicColor();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
