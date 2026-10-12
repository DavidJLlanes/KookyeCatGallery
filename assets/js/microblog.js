/* =============================================================================
   MICROBLOG · comportamiento público
   -----------------------------------------------------------------------------
     1. Corazones (comparten /hearts.php con las fotos; identificador «mb-{id}»).
     2. Compartir e incrustar (código <iframe> para pegar en otras webs).
     3. Imagen ampliada.
     4. «Cargar más» y aparición suave de los hilos.
   Todo usa delegación de eventos, así que también funciona con los hilos cargados después.
   ============================================================================= */
(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const toast = (() => {
        let node = null, timer = 0;
        return message => {
            if (!node) {
                node = document.createElement('div');
                node.className = 'mb-toast';
                node.setAttribute('role', 'status');
                document.body.append(node);
            }
            node.textContent = message;
            node.classList.add('is-visible');
            clearTimeout(timer);
            timer = setTimeout(() => node.classList.remove('is-visible'), 2200);
        };
    })();

    // 1. CORAZONES ---------------------------------------------------------------
    const paintHeart = (button, count, liked) => {
        const formatted = Math.max(0, Number(count) || 0).toLocaleString('es-ES');
        const counter = $('[data-mb-heart-count]', button);
        if (counter) counter.textContent = formatted;
        button.classList.toggle('is-liked', liked);
        button.setAttribute('aria-pressed', liked ? 'true' : 'false');
        button.setAttribute('aria-label', (liked ? 'Quitar corazón. ' : 'Dar un corazón. ') + formatted + ' corazones');
        if (button.firstChild?.nodeType === 3) button.firstChild.textContent = liked ? '♥ ' : '♡ ';
    };
    const loadHearts = async root => {
        const buttons = $$('[data-mb-heart]:not([data-mb-ready])', root);
        if (!buttons.length) return;
        buttons.forEach(button => button.setAttribute('data-mb-ready', ''));
        const slugs = [...new Set(buttons.map(button => button.dataset.mbHeart))];
        for (let i = 0; i < slugs.length; i += 100) {
            const chunk = slugs.slice(i, i + 100);
            try {
                const response = await fetch('/hearts.php?slugs=' + chunk.join(','), { credentials: 'same-origin', cache: 'no-store' });
                const data = await response.json();
                if (!response.ok || !data.ok) continue;
                buttons.filter(button => chunk.includes(button.dataset.mbHeart))
                    .forEach(button => paintHeart(button, data.counts?.[button.dataset.mbHeart], !!data.liked?.[button.dataset.mbHeart]));
            } catch (_) { /* sin conexión: los corazones se quedan a cero */ }
        }
    };
    const toggleHeart = async button => {
        if (button.disabled) return;
        const slug = button.dataset.mbHeart;
        const action = button.classList.contains('is-liked') ? 'unlike' : 'like';
        button.disabled = true;
        try {
            const response = await fetch('/hearts.php', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: new URLSearchParams({ slug, action })
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo guardar el corazón.');
            $$('[data-mb-heart="' + slug + '"]').forEach(other => paintHeart(other, data.count, !!data.liked));
            if (data.liked) {
                button.classList.remove('is-pulse');
                void button.offsetWidth;
                button.classList.add('is-pulse');
            }
        } catch (error) {
            toast(error?.message || 'No se pudo guardar el corazón.');
        } finally {
            button.disabled = false;
        }
    };

    // 2. COMPARTIR E INCRUSTAR ---------------------------------------------------------
    const copyText = async text => {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (_) {
            const area = document.createElement('textarea');
            area.value = text;
            area.style.cssText = 'position:fixed;opacity:0';
            document.body.append(area);
            area.select();
            let ok = false;
            try { ok = document.execCommand('copy'); } catch (_) {}
            area.remove();
            return ok;
        }
    };
    const share = async button => {
        const url = new URL(button.dataset.url, location.origin).href;
        if (navigator.share) {
            try { await navigator.share({ url }); return; } catch (error) { if (error?.name === 'AbortError') return; }
        }
        toast((await copyText(url)) ? 'Enlace copiado' : 'No se pudo copiar el enlace');
    };
    const embedCode = id => {
        const origin = location.origin;
        return '<iframe class="djl-microblog-embed" src="' + origin + '/microblog.php?id=' + id + '&embed=1" width="540" height="320" '
            + 'style="border:0;max-width:100%;width:540px" loading="lazy" title="Post del microblog"></iframe>\n'
            + '<script async src="' + origin + '/assets/js/microblog-embed.js"><\/script>';
    };
    const openEmbedDialog = id => {
        const scrim = document.createElement('div');
        scrim.className = 'mb-dialog-scrim';
        scrim.innerHTML = '<div class="mb-dialog" role="dialog" aria-modal="true" aria-labelledby="mb-embed-title">'
            + '<h3 id="mb-embed-title">Incrustar este post</h3>'
            + '<p>Copia este código y pégalo en el HTML de tu web o blog. El tamaño se ajusta solo.</p>'
            + '<textarea readonly spellcheck="false"></textarea>'
            + '<div class="mb-dialog__actions"><button type="button" data-close>Cerrar</button><button type="button" class="is-primary" data-copy>Copiar código</button></div></div>';
        const area = $('textarea', scrim);
        area.value = embedCode(id);
        const onKey = event => { if (event.key === 'Escape') close(); };
        const close = () => { scrim.remove(); document.removeEventListener('keydown', onKey); };
        scrim.addEventListener('click', async event => {
            if (event.target === scrim || event.target.closest('[data-close]')) close();
            if (event.target.closest('[data-copy]')) toast((await copyText(area.value)) ? 'Código copiado' : 'Selecciona el texto y cópialo a mano');
        });
        document.addEventListener('keydown', onKey);
        document.body.append(scrim);
        area.focus();
        area.select();
    };

    // 3. IMAGEN AMPLIADA ------------------------------------------------------------------
    const openLightbox = link => {
        const group = $$('[data-mb-lightbox]', link.closest('.mb-images') || link.parentElement);
        let index = Math.max(0, group.indexOf(link));
        const box = document.createElement('div');
        box.className = 'mb-lightbox';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-label', 'Imagen ampliada');
        if (group.length < 2) box.dataset.single = '';
        const canFullscreen = !!(box.requestFullscreen || box.webkitRequestFullscreen);
        box.innerHTML = '<img class="mb-lightbox__img" alt="" draggable="false">'
            + '<div class="mb-lightbox__bar">'
            + (canFullscreen ? '<button type="button" class="mb-lightbox__full" aria-label="Pantalla completa" title="Pantalla completa (F)">'
                + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M3 16v5h5M21 16v5h-5"/></svg></button>' : '')
            + '<button type="button" class="mb-lightbox__close" aria-label="Cerrar" title="Cerrar (Esc)">×</button></div>'
            + '<button type="button" class="mb-lightbox__prev" aria-label="Anterior">‹</button>'
            + '<button type="button" class="mb-lightbox__next" aria-label="Siguiente">›</button>'
            + '<div class="mb-lightbox__caption"><span class="mb-lightbox__counter"></span><span class="mb-lightbox__alt"></span></div>';
        const image = $('.mb-lightbox__img', box);
        const counter = $('.mb-lightbox__counter', box);
        const caption = $('.mb-lightbox__alt', box);
        const preload = i => { const src = group[(i + group.length) % group.length]?.href; if (src) new Image().src = src; };
        const show = () => {
            const alt = $('img', group[index])?.alt || '';
            image.src = group[index].href;
            image.alt = alt;
            caption.textContent = alt;
            counter.textContent = group.length > 1 ? (index + 1) + ' / ' + group.length : '';
            preload(index + 1);
            preload(index - 1);
        };
        const step = delta => { index = (index + delta + group.length) % group.length; show(); };
        const fullscreenElement = () => document.fullscreenElement || document.webkitFullscreenElement;
        const toggleFullscreen = () => {
            try {
                if (fullscreenElement()) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
                else (box.requestFullscreen || box.webkitRequestFullscreen).call(box);
            } catch (_) { /* el navegador no lo permite */ }
        };
        const onKey = event => {
            if (event.key === 'Escape' && !fullscreenElement()) close();
            else if (event.key === 'ArrowLeft') step(-1);
            else if (event.key === 'ArrowRight') step(1);
            else if (event.key === 'f' || event.key === 'F') toggleFullscreen();
        };
        const close = () => {
            document.removeEventListener('keydown', onKey);
            document.documentElement.classList.remove('mb-lightbox-open');
            if (fullscreenElement()) { try { (document.exitFullscreen || document.webkitExitFullscreen).call(document); } catch (_) {} }
            box.classList.remove('is-open');
            setTimeout(() => box.remove(), 250);
        };
        box.addEventListener('click', event => {
            if (event.target.closest('.mb-lightbox__prev')) step(-1);
            else if (event.target.closest('.mb-lightbox__next')) step(1);
            else if (event.target.closest('.mb-lightbox__full')) toggleFullscreen();
            else if (event.target.closest('.mb-lightbox__close')) close();
            else if (!event.target.closest('.mb-lightbox__img')) close();
        });
        // Deslizar a los lados cambia de imagen.
        let startX = 0, startY = 0, tracking = false;
        box.addEventListener('touchstart', event => {
            tracking = event.touches.length === 1;
            startX = event.touches[0].clientX; startY = event.touches[0].clientY;
        }, { passive: true });
        box.addEventListener('touchend', event => {
            if (!tracking || group.length < 2) return;
            tracking = false;
            const dx = event.changedTouches[0].clientX - startX, dy = event.changedTouches[0].clientY - startY;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) step(dx < 0 ? 1 : -1);
        }, { passive: true });
        document.addEventListener('keydown', onKey);
        document.documentElement.classList.add('mb-lightbox-open');
        show();
        document.body.append(box);
        requestAnimationFrame(() => box.classList.add('is-open'));
    };

    document.addEventListener('click', event => {
        const heart = event.target.closest('[data-mb-heart]');
        if (heart) { event.preventDefault(); toggleHeart(heart); return; }
        const shareButton = event.target.closest('[data-mb-share]');
        if (shareButton) { event.preventDefault(); share(shareButton); return; }
        const embed = event.target.closest('[data-mb-embed]');
        if (embed) { event.preventDefault(); openEmbedDialog(embed.dataset.id); return; }
        const image = event.target.closest('[data-mb-lightbox]');
        if (image) { event.preventDefault(); openLightbox(image); }
    });

    // 4. CARGAR MÁS Y APARICIÓN ---------------------------------------------------------------
    const motionOk = 'IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches;
    const observer = motionOk ? new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) { entry.target.classList.add('is-in'); observer.unobserve(entry.target); }
    }), { rootMargin: '0px 0px -8% 0px' }) : null;
    const reveal = threads => threads.forEach(thread => observer ? observer.observe(thread) : thread.classList.add('is-in'));

    // API para el formulario flotante de la web (microblog-composer.js): recarga la primera página del feed.
    // Devuelve false si todavía no hay feed en la página (primer post): entonces se recarga la portada.
    window.djlMicroblog = { refresh: async () => false };

    const feed = $('[data-mb-feed]');
    if (feed) {
        window.djlMicroblog.refresh = async () => {
            const response = await fetch('/microblog.php?feed=1&offset=0', { cache: 'no-store' });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo actualizar el feed.');
            feed.innerHTML = data.html;
            feed.dataset.mbNext = data.next === null ? '' : String(data.next);
            const more = $('.mb-loadmore');
            if (more) more.hidden = data.next === null;
            feed.classList.remove('mb-feed--anim');
            $$('.mb-thread', feed).forEach(thread => thread.classList.add('is-in'));
            loadHearts(feed);
            return true;
        };
        if (motionOk) feed.classList.add('mb-feed--anim');
        loadHearts(feed);
        reveal($$('.mb-thread', feed));
        const button = $('[data-mb-loadmore]');
        button?.addEventListener('click', async () => {
            const next = feed.dataset.mbNext;
            if (!next) return;
            button.disabled = true;
            $('.mb-loadmore__error')?.remove();
            try {
                const response = await fetch('/microblog.php?feed=1&offset=' + encodeURIComponent(next), { cache: 'no-store' });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo cargar.');
                const before = new Set($$('.mb-thread', feed));
                feed.insertAdjacentHTML('beforeend', data.html);
                reveal($$('.mb-thread', feed).filter(thread => !before.has(thread)));
                loadHearts(feed);
                feed.dataset.mbNext = data.next === null ? '' : String(data.next);
                if (data.next === null) button.closest('.mb-loadmore').hidden = true;
            } catch (_) {
                const note = document.createElement('p');
                note.className = 'mb-loadmore__error';
                note.textContent = 'No se pudieron cargar más posts. Inténtalo de nuevo.';
                button.after(note);
            } finally {
                button.disabled = false;
            }
        });
    } else {
        loadHearts(document);
    }
})();
