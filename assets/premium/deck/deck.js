/* =============================================================================
   GALERÍA PREMIUM · ESTILO BARAJA (clave `deck`) · comportamiento
   -----------------------------------------------------------------------------
   Marcado: inc/premium/deck.php · Estilos y animación: deck.css
   Documentación: docs/galerias-premium.md y README.md

   Qué hace
     · Mantiene una posición activa (`index`) y le dice a cada carta dónde está respecto a ella
       (data-pos). El CSS se encarga de colocarla y animarla en 3D.
     · Avanza con: rueda del ratón hacia abajo, deslizar el dedo hacia arriba (o hacia la izquierda),
       flechas del teclado, RePág/AvPág, Espacio y los botones de la barra inferior.
     · Retrocede con los gestos contrarios.
     · Mientras mueve cartas la página NO se desplaza. En el límite (primera o última foto) deja de
       capturar el gesto y la página sigue su scroll normal, así se puede salir de la galería.
     · Filtra por categoría (chips del bloque «Categorías») y por favoritas (botón «Favoritas»).
     · Ajusta cada foto a la pantalla: la cubre entera si su proporción es parecida a la de la
       pantalla y, si no, la muestra completa sobre su propio desenfoque (sin recortes absurdos).

   Organización del archivo
     1. Estado y utilidades        4. Entrada: rueda
     2. Filtros                    5. Entrada: táctil
     3. Pintado (render)           6. Entrada: teclado, botones, filtros y arranque
   ============================================================================= */
(() => {
    'use strict';

    const root = document.getElementById('deck');
    if (!root) return;

    /* -------------------------------------------------------------------------
       1. Estado y utilidades
       ------------------------------------------------------------------------- */
    const cards = [...root.querySelectorAll('.deck__card')];
    const counterCurrent = root.querySelector('[data-deck-current]');
    const counterTotal = root.querySelector('[data-deck-total]');
    const prevButton = root.querySelector('[data-deck-prev]');
    const nextButton = root.querySelector('[data-deck-next]');
    const emptyMessage = root.querySelector('[data-deck-empty]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    let visible = cards.slice();   // Cartas que pasan los filtros, en orden.
    let index = 0;                 // Posición de la carta activa dentro de `visible`.

    // Tiempos (ms). El paso de carta dura lo que diga --deck-duration en el CSS (520 ms).
    const STEP_COOLDOWN = 340;     // Mínimo entre dos pasos con la rueda: suave pero reactivo.
    const WHEEL_QUIET = 120;       // Pausa que separa un gesto de rueda del siguiente.

    document.documentElement.classList.add('has-deck');   // Marca la página para que otros estilos puedan reaccionar (no se usa scroll-snap: dificultaría salir de la baraja).

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

    // «Acoplada»: la baraja llena la pantalla. Solo entonces captura la rueda, el dedo y el teclado.
    const rect = () => root.getBoundingClientRect();
    const isEngaged = () => {
        const r = rect();
        const vh = window.innerHeight;
        return r.width > 0 && r.top <= vh * 0.12 && r.bottom >= vh * 0.88;
    };
    const isAligned = () => Math.abs(rect().top) <= 2;

    // Alinea la baraja con el borde superior de la pantalla (una sola vez por gesto).
    let aligning = false;
    const align = () => {
        if (aligning || isAligned()) return;
        aligning = true;
        window.scrollBy({ top: rect().top, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
        window.setTimeout(() => { aligning = false; }, 450);
    };

    /* -------------------------------------------------------------------------
       2. Filtros (categoría y favoritas)
       ------------------------------------------------------------------------- */
    let category = '';

    const favorites = () => {
        try {
            const saved = JSON.parse(localStorage.getItem('djl-photo-favorites-v1') || '[]');
            return new Set(Array.isArray(saved) ? saved : []);
        } catch (_) { return new Set(); }
    };

    const applyFilters = () => {
        const onlyFavorites = document.body.classList.contains('favorites-only');
        const saved = onlyFavorites ? favorites() : null;
        visible = cards.filter(card =>
            (!category || card.dataset.category === category) &&
            (!saved || saved.has(card.dataset.slug || '')));
        cards.forEach(card => { card.hidden = !visible.includes(card); });
        index = 0;
        render();
    };

    /* -------------------------------------------------------------------------
       3. Pintado
       ------------------------------------------------------------------------- */
    const fitCard = card => {
        // Proporción de la foto (ancho/alto) frente a la de la pantalla. Si difieren más de ~35 %, se
        // muestra completa (contain) para no recortarla; si no, cubre la carta (cover).
        const photo = Number(card.dataset.aspect) || 1.5;
        const screen = root.clientWidth / Math.max(1, root.clientHeight);
        card.classList.toggle('is-contain', Math.abs(Math.log(photo / screen)) > 0.3);
    };

    const setBackdrop = card => {
        const image = card.querySelector('.deck__img');
        const backdrop = card.querySelector('.deck__backdrop');
        if (!image || !backdrop || !image.currentSrc) return;
        backdrop.style.backgroundImage = `url("${image.currentSrc.replace(/"/g, '%22')}")`;
    };

    const updateFit = () => cards.forEach(fitCard);

    const render = () => {
        const total = visible.length;
        visible.forEach((card, i) => {
            const pos = clamp(i - index, -2, 4);
            card.dataset.pos = String(pos);
            const active = pos === 0;
            card.setAttribute('aria-hidden', active ? 'false' : 'true');
            card.toggleAttribute('inert', !active);
            // Las fotos cercanas se cargan ya, para que el mazo nunca enseñe huecos.
            if (i >= index && i <= index + 3) {
                const image = card.querySelector('.deck__img');
                if (image && image.loading === 'lazy') image.loading = 'eager';
            }
        });
        if (counterCurrent) counterCurrent.textContent = String(total ? index + 1 : 0);
        if (counterTotal) counterTotal.textContent = String(total);
        if (prevButton) prevButton.disabled = index <= 0;
        if (nextButton) nextButton.disabled = index >= total - 1;
        if (emptyMessage) emptyMessage.hidden = total > 0;
        root.dataset.index = String(index);
    };

    // Mueve la carta activa. Devuelve false si ya estaba en el límite.
    const step = delta => {
        const next = clamp(index + delta, 0, visible.length - 1);
        if (next === index) return false;
        index = next;
        root.classList.add('is-used');   // Oculta la pista inicial.
        render();
        return true;
    };

    const canStep = delta => {
        const next = index + delta;
        return next >= 0 && next <= visible.length - 1;
    };

    /* -------------------------------------------------------------------------
       4. Entrada: rueda del ratón / trackpad
       La rueda hacia abajo avanza, hacia arriba retrocede. En los límites no se captura.
       ------------------------------------------------------------------------- */
    let lastStep = 0;
    let lastWheel = 0;
    let lastAbs = 0;

    window.addEventListener('wheel', event => {
        if (event.ctrlKey || event.defaultPrevented || !isEngaged()) return;   // ctrl+rueda = zoom del navegador.
        // El eje dominante manda: el trackpad también navega con gestos horizontales.
        const raw = Math.abs(event.deltaY) >= Math.abs(event.deltaX) ? event.deltaY : event.deltaX;
        if (!raw) return;
        const direction = raw > 0 ? 1 : -1;
        if (!canStep(direction)) return;   // Límite: la página sigue su scroll normal.

        event.preventDefault();            // El fondo de la web no se mueve.
        if (!isAligned()) { align(); lastWheel = event.timeStamp; lastAbs = 0; return; }   // El primer gesto solo encaja la baraja.

        const abs = Math.abs(raw) * (event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? 400 : 1);
        const now = event.timeStamp;
        const quiet = now - lastWheel > WHEEL_QUIET;       // Gesto nuevo tras una pausa.
        const rising = abs > lastAbs * 1.15 + 2;           // Impulso nuevo dentro de la inercia del trackpad.
        const notch = abs >= 40 && abs === lastAbs;        // Rueda de ratón: muescas idénticas seguidas.
        lastWheel = now;
        lastAbs = abs;

        if (abs < 4 || now - lastStep < STEP_COOLDOWN) return;
        if (!(quiet || rising || notch)) return;           // Cola de inercia del gesto anterior: se ignora.
        if (step(direction)) lastStep = now;
    }, { passive: false });

    /* -------------------------------------------------------------------------
       5. Entrada: táctil
       Dedo hacia arriba (o hacia la izquierda) = siguiente. El gesto contrario = anterior.
       preventDefault solo si hay carta a la que ir: en los límites el dedo sigue moviendo la página.
       ------------------------------------------------------------------------- */
    const STEP_DISTANCE = 42;   // px de recorrido para pasar de carta durante el gesto.
    const FLICK_DISTANCE = 18;  // px mínimos de un gesto rápido.
    const FLICK_TIME = 220;     // ms máximos de un gesto rápido.
    let touch = null;

    root.addEventListener('touchstart', event => {
        const point = event.touches[0];
        touch = event.touches.length === 1 && isEngaged()
            ? { x: point.clientX, y: point.clientY, time: event.timeStamp, axis: null, done: false, direction: 0, distance: 0 }
            : null;
    }, { passive: true });

    root.addEventListener('touchmove', event => {
        if (!touch || event.touches.length !== 1) return;
        // Un gesto que ya movió cartas se queda con TODO el recorrido del dedo: si no, el resto del gesto
        // desplazaría la página de fondo.
        if (touch.done) {
            if (touch.captured && event.cancelable) event.preventDefault();   // Paso ya dado: se sigue bloqueando la página.
            return;                                                           // done sin captured = gesto cedido a la página.
        }
        const point = event.touches[0];
        const dx = point.clientX - touch.x;
        const dy = point.clientY - touch.y;
        if (touch.axis === null) {
            if (Math.hypot(dx, dy) < 8) return;
            touch.axis = Math.abs(dy) >= Math.abs(dx) ? 'y' : 'x';
        }
        const delta = touch.axis === 'y' ? dy : dx;
        const direction = delta < 0 ? 1 : -1;
        if (!canStep(direction)) { touch.done = true; return; }   // Límite: se deja pasar el scroll.
        if (event.cancelable) event.preventDefault();             // El fondo de la web no se mueve.
        touch.captured = true;                                    // A partir de aquí el gesto es de la baraja.
        if (!isAligned()) { align(); touch.done = true; return; }
        touch.direction = direction;
        touch.distance = Math.abs(delta);
        if (touch.distance >= STEP_DISTANCE) { step(direction); touch.done = true; }
    }, { passive: false });

    const endTouch = event => {
        if (touch && !touch.done && touch.direction && touch.distance >= FLICK_DISTANCE
            && event.timeStamp - touch.time <= FLICK_TIME) step(touch.direction);
        touch = null;
    };
    root.addEventListener('touchend', endTouch, { passive: true });
    root.addEventListener('touchcancel', () => { touch = null; }, { passive: true });

    /* -------------------------------------------------------------------------
       6. Entrada: teclado, botones, filtros y arranque
       ------------------------------------------------------------------------- */
    document.addEventListener('keydown', event => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
        if (document.body.classList.contains('slideshow-open')) return;
        const target = event.target;
        if (target instanceof Element && target.closest('input, select, textarea, [contenteditable="true"]')) return;
        if (!isEngaged()) return;
        let direction = 0;
        if (['ArrowDown', 'ArrowRight', 'PageDown'].includes(event.key)) direction = 1;
        else if (['ArrowUp', 'ArrowLeft', 'PageUp'].includes(event.key)) direction = -1;
        else if (event.key === ' ' && !(target instanceof Element && target.closest('button, a'))) direction = event.shiftKey ? -1 : 1;
        else if (event.key === 'Home' && index > 0) { event.preventDefault(); step(-index); return; }
        else if (event.key === 'End' && index < visible.length - 1) { event.preventDefault(); step(visible.length - 1 - index); return; }
        if (!direction || !canStep(direction)) return;   // En el límite, las teclas siguen moviendo la página.
        event.preventDefault();
        step(direction);
    });

    prevButton?.addEventListener('click', () => step(-1));
    nextButton?.addEventListener('click', () => step(1));

    // Chips del bloque «Categorías» (main.js no los gestiona porque no hay galería estándar).
    document.addEventListener('click', event => {
        const chip = event.target instanceof Element ? event.target.closest('.categories-filter__chip') : null;
        if (!chip) return;
        document.querySelectorAll('.categories-filter__chip').forEach(other => other.classList.toggle('is-active', other === chip));
        category = chip.dataset.category || '';
        applyFilters();
    });

    // main.js alterna body.favorites-only y avisa con este evento.
    document.addEventListener('favorites:changed', applyFilters);

    // Ajuste a la pantalla al cargar, al girar el dispositivo y al redimensionar.
    let resizeTimer = 0;
    const onResize = () => { window.clearTimeout(resizeTimer); resizeTimer = window.setTimeout(updateFit, 80); };
    window.addEventListener('resize', onResize);
    window.addEventListener('orientationchange', onResize);

    cards.forEach(card => {
        const image = card.querySelector('.deck__img');
        if (!image) return;
        if (image.complete) setBackdrop(card);
        else image.addEventListener('load', () => setBackdrop(card), { once: true });
    });

    updateFit();
    render();
})();
