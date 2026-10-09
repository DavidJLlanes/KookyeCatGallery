/* =============================================================================
   GALERÍA PREMIUM · ESTILO TINDER (clave `swipe`) · comportamiento
   -----------------------------------------------------------------------------
   Marcado: inc/premium/swipe.php · Estilos: deck.css (interfaz) + swipe.css · Documentación: docs/galerias-premium.md

   Qué hace
     · Una carta a pantalla casi completa con las siguientes asomando debajo. Se arrastra a un lado (dedo o ratón):
         → hacia el lado del CORAZÓN (derecha): da un corazón a la foto y pasa a la siguiente.
         ← hacia el otro lado (izquierda): solo pasa a la siguiente (la flecha).
       En los dos casos se avanza a la siguiente foto. Si la foto ya tenía tu corazón, deslizar hacia el corazón solo avanza (nunca
       se quita un corazón por deslizar).
     · El corazón se da con el mismo mecanismo que el resto de la web: se pulsa un botón de corazón oculto de la carta ([data-heart-photo]),
       que gestiona main.js (contador, estado, servidor y «Favoritas»).
     · La interfaz de la baraja indica los dos lados con un corazón (derecha) y una flecha (izquierda), tanto en los botones de la barra
       como en los sellos que aparecen sobre la carta mientras se arrastra.
     · También con teclado (→ corazón, ← siguiente, ↓ siguiente, ↑ anterior) y con la rueda (abajo = siguiente, sin corazón).
     · Comparte con «Estilo Baraja» (deck.js): filtros, parada del scroll en escritorio, fijación en móvil (body fixed), botones de
       salir (abajo y arriba) y vuelta desde la ficha (/#baraja=<slug>).
     · Tras la última foto aparece «Has visto todas las fotos» con un botón para volver a empezar.

   Organización del archivo
     1. Estado y utilidades          6. Entrada: teclado, botones y filtros
     2. Filtros                      7. Fijar la galería (móvil) / parada del scroll (escritorio)
     3. Pintado                      8. Volver desde la ficha de una foto
     4. Entrada: rueda               9. Salir de la galería y estado «acoplada»
     5. Entrada: arrastrar la carta
   ============================================================================= */
(() => {
    'use strict';

    const root = document.getElementById('deck');
    if (!root) return;

    /* -------------------------------------------------------------------------
       1. Estado y utilidades
       ------------------------------------------------------------------------- */
    const html = document.documentElement;
    const cards = [...root.querySelectorAll('.deck__card')];
    const counterCurrent = root.querySelector('[data-deck-current]');
    const counterTotal = root.querySelector('[data-deck-total]');
    const passButton = root.querySelector('[data-swipe-pass]');   // Flecha: pasar a la siguiente.
    const likeButton = root.querySelector('[data-swipe-like]');   // Corazón: dar un corazón y pasar.
    const restartButton = root.querySelector('[data-swipe-restart]');
    const exitDownButton = root.querySelector('[data-deck-exit]');
    const exitUpButton = root.querySelector('[data-deck-exit-up]');
    const emptyMessage = root.querySelector('[data-deck-empty]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const coarse = window.matchMedia('(hover: none) and (pointer: coarse)');   // Pantalla táctil sin ratón.

    let visible = cards.slice();   // Cartas que pasan los filtros, en orden.
    let index = 0;                 // Posición de la carta activa dentro de `visible` (== visible.length: ya se vieron todas).

    // Tiempos (ms). El paso de carta dura lo que diga --deck-duration en el CSS (850 ms).
    const STEP_COOLDOWN = 600;     // Mínimo entre dos pasos con la rueda: deja ver el efecto de baraja completo.
    const WHEEL_QUIET = 120;       // Pausa que separa un gesto de rueda del siguiente.

    html.classList.add('has-deck');   // Marca la página para que otros estilos puedan reaccionar.

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const rect = () => root.getBoundingClientRect();

    // Fijación táctil (sección 7): el cuerpo de la página se congela con position: fixed.
    let pinned = false;
    let pinY = 0;                  // Posición de scroll en la que se fijó la baraja.

    // Posición de la baraja en el documento. Fijada, el scroll vale 0 y se usa la guardada.
    const deckTop = () => (pinned ? pinY : rect().top + window.scrollY);

    // «Acoplada»: la baraja llena la pantalla. Solo entonces captura la rueda, el dedo y el teclado.
    //   · Táctil: lo está cuando está fijada.
    //   · Escritorio: cuando ocupa casi toda la pantalla.
    const isEngaged = () => {
        if (coarse.matches) return pinned;
        const r = rect();
        const vh = window.innerHeight;
        return r.width > 0 && r.top <= vh * 0.12 && r.bottom >= vh * 0.88;
    };
    const isAligned = () => Math.abs(rect().top) <= 2;

    // Salida (botones «Salir» o gesto en un límite): mientras dura, la baraja no captura ningún gesto.
    let exitUntil = 0;
    const exiting = () => performance.now() < exitUntil;

    // «Parada» (solo escritorio): mientras dura, se absorbe la rueda que sigue llegando de un gesto con inercia.
    let locked = false;
    let lockUntil = 0;
    const extendLock = ms => { lockUntil = Math.max(lockUntil, performance.now() + ms); };
    const startLock = ms => {
        extendLock(ms);
        if (locked) return;
        locked = true;
        const tick = () => {
            if (performance.now() >= lockUntil) { locked = false; return; }
            window.requestAnimationFrame(tick);
        };
        window.requestAnimationFrame(tick);
    };

    // Alinea la baraja con el borde superior de la pantalla (escritorio; una sola vez por gesto).
    let aligning = false;
    const align = () => {
        if (aligning || isAligned()) return;
        aligning = true;
        window.scrollBy({ top: rect().top, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
        window.setTimeout(() => { aligning = false; }, 450);
        startLock(450);   // Lo que quede del gesto que trajo hasta aquí no debe pasar fotos.
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
        fitCards();
    };

    /* -------------------------------------------------------------------------
       3. Pintado
       ------------------------------------------------------------------------- */
    // Todas las cartas tienen el mismo marco (lo fija el CSS). Si la proporción de la foto se parece a la del marco
    // la cubre (cover); si difiere más de ~35 % se muestra completa (contain) sobre el fondo de la carta.
    // El escenario de las cartas es el hueco libre entre las herramientas (arriba) y la barra de abajo, con el mismo margen a cada lado, medido
    // en la propia pantalla: así la carta queda exactamente centrada entre las dos (el CSS tiene un valor aproximado de reserva).
    const stage = root.querySelector('.deck__stage');
    const STAGE_GAP = 26;                 // Margen hasta las herramientas y la barra; deja sitio a las cartas que asoman por debajo.
    const fitStage = () => {
        const tools = root.querySelector('.deck__tools'), nav = root.querySelector('.deck__nav');
        if (!stage || !tools || !nav) return;
        const deck = rect();
        stage.style.setProperty('--swipe-top', `${Math.max(0, tools.getBoundingClientRect().bottom - deck.top + STAGE_GAP).toFixed(1)}px`);
        stage.style.setProperty('--swipe-bottom', `${Math.max(0, deck.bottom - nav.getBoundingClientRect().top + STAGE_GAP).toFixed(1)}px`);
    };

    const fitCards = () => {
        fitStage();
        const frame = visible[0];
        if (!frame || !frame.clientHeight) return;
        const ratio = frame.clientWidth / frame.clientHeight;
        cards.forEach(card => {
            const photo = Number(card.dataset.aspect) || 1.5;
            card.classList.toggle('is-contain', Math.abs(Math.log(photo / ratio)) > 0.3);
        });
    };

    const render = () => {
        const total = visible.length;
        const finished = total > 0 && index >= total;
        visible.forEach((card, i) => {
            // 0 = encima · 1, 2 = asoman debajo · 3 = oculta · -1 = ya pasada (sale por un lado).
            const pos = clamp(i - index, -1, 3);
            card.dataset.pos = String(pos);
            if (pos !== -1) {                                          // La carta que acaba de salir conserva su sello y su lado de salida.
                card.classList.remove('is-out-right', 'is-stamp-like', 'is-stamp-pass');
                card.style.removeProperty('--like');
                card.style.removeProperty('--pass');
            }
            const active = pos === 0;
            card.setAttribute('aria-hidden', active ? 'false' : 'true');
            card.toggleAttribute('inert', !active);
            // Las fotos cercanas se cargan ya, para que la siguiente nunca aparezca vacía.
            if (i >= index && i <= index + 3) {
                const image = card.querySelector('.deck__img');
                if (image && image.loading === 'lazy') image.loading = 'eager';
            }
        });
        if (counterCurrent) counterCurrent.textContent = String(total ? Math.min(index + 1, total) : 0);
        if (counterTotal) counterTotal.textContent = String(total);
        if (passButton) passButton.disabled = total === 0 || index >= total;
        if (likeButton) likeButton.disabled = total === 0 || index >= total;
        if (emptyMessage) emptyMessage.hidden = total > 0;
        root.classList.toggle('is-finished', finished);
        root.dataset.index = String(index);
    };

    // Avanza (delta 1) o retrocede (delta -1) una foto. Devuelve false si ya estaba en el límite.
    const step = delta => {
        const next = clamp(index + delta, 0, visible.length);
        if (next === index) return false;
        index = next;
        root.classList.add('is-used');   // Oculta la pista inicial.
        render();
        return true;
    };

    const canStep = delta => {
        const next = index + delta;
        return visible.length > 0 && next >= 0 && next <= visible.length;
    };

    // ¿La foto ya tiene el corazón de este visitante? (favoritas guardadas por main.js)
    const isLiked = slug => favorites().has(slug);

    // Da un corazón a la foto de la carta: pulsa su botón de corazón oculto, que gestiona main.js (contador, servidor, estado).
    // Si ya tenía corazón no se hace nada: deslizar hacia el corazón nunca lo quita.
    const giveHeart = card => {
        const slug = card.dataset.slug || '';
        if (!slug || isLiked(slug)) return;
        card.querySelector('[data-heart-photo]')?.click();
    };

    // Decide la carta activa: «like» (corazón + siguiente) o «pass» (solo siguiente). En los dos casos se avanza.
    const decide = kind => {
        if (index >= visible.length) return false;
        const card = visible[index];
        if (kind === 'like') giveHeart(card);
        card.classList.toggle('is-out-right', kind === 'like');
        card.classList.add(kind === 'like' ? 'is-stamp-like' : 'is-stamp-pass');   // El sello se ve mientras la carta sale.
        card.style.setProperty(kind === 'like' ? '--like' : '--pass', '1');
        return step(1);
    };

    /* -------------------------------------------------------------------------
       4. Entrada: rueda del ratón / trackpad
       La rueda hacia abajo avanza, hacia arriba retrocede. En los límites no se captura.
       ------------------------------------------------------------------------- */
    let lastStep = 0;
    let lastWheel = 0;
    let lastAbs = 0;

    window.addEventListener('wheel', event => {
        if (event.ctrlKey || event.defaultPrevented || exiting()) return;      // ctrl+rueda = zoom del navegador.
        if (pinned) { event.preventDefault(); return; }                        // Fijada: la página no se mueve.
        if (locked) {                      // Parada al llegar: se absorbe la inercia del gesto que trajo hasta aquí.
            event.preventDefault();
            extendLock(160);
            lastWheel = event.timeStamp;
            lastAbs = 1e9;                 // Lo que siga no cuenta como «impulso nuevo».
            return;
        }
        if (!isEngaged()) return;
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
       5. Entrada: arrastrar la carta (dedo o ratón)
       Se arrastra la carta activa en horizontal: sigue al dedo girando un poco y muestra el sello del lado al que va. Al soltar:
         · pasado un umbral (o con un gesto rápido) hacia la DERECHA → corazón y siguiente;
         · hacia la IZQUIERDA → solo siguiente;
         · si no, la carta vuelve a su sitio.
       Un gesto vertical solo sirve para salir de la galería cuando no hay más fotos en esa dirección (primera o «ya las has visto
       todas»); en móvil la página está fijada y no se mueve.
       ------------------------------------------------------------------------- */
    const COMMIT_RATIO = 0.26;     // Fracción del ancho de la carta que hay que arrastrar para decidir.
    const FLICK_SPEED = 0.55;      // px/ms: un gesto rápido decide aunque sea corto.
    const LEAVE_DISTANCE = 40;     // px de gesto vertical para salir de la galería en un límite.
    let drag = null;
    let suppressClickUntil = 0;

    const paintDrag = (card, dx, dy) => {
        const width = card.clientWidth || 1;
        card.style.transform = `translate3d(${dx}px, ${dy * 0.25}px, 0) rotate(${(dx / width * 18).toFixed(2)}deg)`;
        const strength = clamp(Math.abs(dx) / (width * COMMIT_RATIO), 0, 1);
        card.style.setProperty('--like', dx > 0 ? strength.toFixed(2) : '0');
        card.style.setProperty('--pass', dx < 0 ? strength.toFixed(2) : '0');
    };

    const releaseDrag = card => {                                  // Quita el estilo de arrastre: el CSS anima el resto.
        card.style.transition = '';
        card.style.transform = '';
        card.style.removeProperty('--like');
        card.style.removeProperty('--pass');
    };

    root.addEventListener('pointerdown', event => {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        if (!isEngaged() || exiting()) return;
        const card = event.target instanceof Element ? event.target.closest('.deck__card') : null;
        if (!card || card !== visible[index]) return;
        drag = { id: event.pointerId, card, x: event.clientX, y: event.clientY, lastX: event.clientX, lastT: event.timeStamp, vx: 0, dx: 0, dy: 0, axis: null };
    });

    root.addEventListener('pointermove', event => {
        if (!drag || event.pointerId !== drag.id) return;
        drag.dx = event.clientX - drag.x;
        drag.dy = event.clientY - drag.y;
        if (drag.axis === null) {
            if (Math.hypot(drag.dx, drag.dy) < 8) return;
            drag.axis = Math.abs(drag.dx) >= Math.abs(drag.dy) ? 'x' : 'y';
            if (drag.axis === 'x') {
                try { drag.card.setPointerCapture(drag.id); } catch (_) { /* sin captura: sigue funcionando dentro de la carta */ }
                drag.card.style.transition = 'none';               // La carta sigue al dedo sin retraso.
            }
        }
        if (drag.axis === 'x') {
            const dt = Math.max(1, event.timeStamp - drag.lastT);
            drag.vx = 0.7 * drag.vx + 0.3 * ((event.clientX - drag.lastX) / dt);
            drag.lastX = event.clientX; drag.lastT = event.timeStamp;
            paintDrag(drag.card, drag.dx, drag.dy);
        } else if (Math.abs(drag.dy) >= LEAVE_DISTANCE) {
            const direction = drag.dy < 0 ? 1 : -1;                // Dedo hacia arriba = seguir hacia abajo en la web.
            if (!canStep(direction)) { drag = null; leaveDeck(direction); }
        }
    });

    const endDrag = event => {
        if (!drag || event.pointerId !== drag.id) return;
        const { card, dx, axis } = drag;
        // La velocidad solo vale si el dedo sigue moviéndose al soltar: si se detuvo un momento antes, no es un gesto rápido.
        const vx = event.timeStamp - drag.lastT > 120 ? 0 : drag.vx;
        drag = null;
        if (axis !== 'x') return;
        suppressClickUntil = performance.now() + 60;               // Arrastrar no es pulsar: no abre la ficha.
        const far = Math.abs(dx) > card.clientWidth * COMMIT_RATIO;
        const fast = Math.abs(vx) > FLICK_SPEED && Math.abs(dx) > 30;
        if (event.type === 'pointercancel' || !(far || fast)) { releaseDrag(card); return; }
        const kind = (far ? dx : vx) > 0 ? 'like' : 'pass';
        // La carta sale desde donde está: se fija su posición actual y el CSS la lleva fuera con su transición.
        const current = card.style.transform;
        card.style.transition = 'none';
        card.style.transform = current;
        void card.offsetWidth;
        card.style.transition = '';
        card.style.transform = '';
        decide(kind);
    };
    root.addEventListener('pointerup', endDrag);
    root.addEventListener('pointercancel', endDrag);

    // Tras arrastrar, el clic que sigue al soltar no debe abrir la ficha.
    root.addEventListener('click', event => {
        if (event.target instanceof Element && event.target.closest('[data-heart-photo]')) return;   // El corazón que pulsa el propio JS sí pasa.
        if (performance.now() < suppressClickUntil) { event.preventDefault(); event.stopPropagation(); }
    }, true);

    /* -------------------------------------------------------------------------
       6. Entrada: teclado, botones y filtros
       → corazón y siguiente · ← siguiente · ↓ / RePág / Espacio siguiente · ↑ / Mayús+Espacio anterior · Inicio / Fin.
       ------------------------------------------------------------------------- */
    document.addEventListener('keydown', event => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
        if (document.body.classList.contains('slideshow-open') || exiting()) return;
        const target = event.target;
        if (target instanceof Element && target.closest('input, select, textarea, [contenteditable="true"]')) return;
        if (!isEngaged()) return;
        const onButton = target instanceof Element && target.closest('button, a');
        let handled = false;
        if (event.key === 'ArrowRight') handled = canStep(1) && (decide('like'), true);
        else if (event.key === 'ArrowLeft') handled = canStep(1) && (decide('pass'), true);
        else if (event.key === 'ArrowDown' || event.key === 'PageDown' || (event.key === ' ' && !onButton && !event.shiftKey)) handled = canStep(1) && (decide('pass'), true);
        else if (event.key === 'ArrowUp' || event.key === 'PageUp' || (event.key === ' ' && !onButton && event.shiftKey)) handled = canStep(-1) && step(-1);
        else if (event.key === 'Home' && index > 0) handled = step(-index);
        else if (event.key === 'End' && index < visible.length) handled = step(visible.length - index);
        if (handled) event.preventDefault();   // En el límite las teclas siguen moviendo la página.
    });

    passButton?.addEventListener('click', () => decide('pass'));
    likeButton?.addEventListener('click', () => decide('like'));
    restartButton?.addEventListener('click', () => { index = 0; root.classList.add('is-used'); render(); });

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

    /* -------------------------------------------------------------------------
       7. Fijar la galería (móvil) y parada del scroll (escritorio)

       MÓVIL / TABLET. En cuanto la baraja llena la pantalla se FIJA: se coloca el scroll exactamente en ella y el
       <body> pasa a position: fixed (con top negativo para no perder el sitio). Sin scroll no hay nada que se
       mueva, rebote ni «vibre», ni con la inercia del dedo, ni con la barra de direcciones del navegador. Es la
       técnica fiable en iOS y Android; luchar contra el scroll nativo con scrollTo() provoca temblores.
       Para llegar se vigila la posición de la baraja fotograma a fotograma mientras está cerca de la pantalla
       (en iOS los eventos de scroll durante la inercia no son fiables).

       ESCRITORIO. Cuando el scroll cruza la baraja se coloca la página justo ahí y se «congela» un instante
       (la rueda que sigue llegando se absorbe) para que la inercia no pase de largo ni mueva fotos.
       ------------------------------------------------------------------------- */
    let savedBodyStyle = '';
    let skipCatchUntil = 0;

    const setEngagedClass = () => html.classList.toggle('deck-engaged', isEngaged());

    const pin = () => {
        if (pinned || !coarse.matches) return;
        window.scrollTo({ top: deckTop(), behavior: 'instant' });
        pinY = window.scrollY;
        const body = document.body;
        savedBodyStyle = body.getAttribute('style') || '';
        body.style.position = 'fixed';
        body.style.top = `-${pinY}px`;
        body.style.left = '0';
        body.style.right = '0';
        body.style.width = '100%';
        pinned = true;
        html.classList.add('deck-pinned');
        setEngagedClass();
        updateExitUp();
    };

    const unpin = () => {
        if (!pinned) return;
        const body = document.body;
        if (savedBodyStyle) body.setAttribute('style', savedBodyStyle); else body.removeAttribute('style');
        pinned = false;
        html.classList.remove('deck-pinned');
        window.scrollTo({ top: pinY, behavior: 'instant' });
        setEngagedClass();
        updateExitUp();
    };

    // Mientras está fijada no hay gesto nativo que valga: se cancela todo (también el «tirar para recargar»).
    document.addEventListener('touchmove', event => {
        if (pinned && event.cancelable) event.preventDefault();
    }, { passive: false });

    // Táctil: vigilancia de llegada (solo mientras la baraja está a menos de una pantalla de distancia).
    let near = false;
    let watching = false;
    let lastTop = 0;
    const watch = () => {
        if (watching) return;
        watching = true;
        lastTop = rect().top;
        const loop = () => {
            if (!near && !pinned) { watching = false; return; }
            if (coarse.matches && !pinned && !exiting() && performance.now() >= skipCatchUntil) {
                const top = rect().top;
                // La baraja cruza el borde superior de la pantalla (bajando desde arriba o subiendo desde abajo) o ya está justo en él.
                if ((lastTop > 1 && top <= 1) || (lastTop < -1 && top >= -1) || Math.abs(top) <= 1) pin();
            }
            lastTop = rect().top;
            window.requestAnimationFrame(loop);
        };
        loop();
    };

    if ('IntersectionObserver' in window) {
        new IntersectionObserver(entries => {
            near = entries[entries.length - 1].isIntersecting;
            if (near) watch();
        }, { rootMargin: '100% 0px 100% 0px' }).observe(root);
    } else {
        near = true;
        watch();
    }

    // Cruce por eventos de scroll (táctil: fija; escritorio: para el scroll). Complementa la vigilancia fotograma a fotograma.
    let lastY = window.scrollY;
    window.addEventListener('scroll', () => {
        if (pinned) { lastY = window.scrollY; return; }
        const y = window.scrollY;
        const target = deckTop();
        if (coarse.matches) {                                   // Táctil: cruzar la baraja la fija (también si el salto es grande).
            const passed = (lastY < target - 1 && y >= target - 1) || (lastY > target + 1 && y <= target + 1);
            lastY = y;
            if (passed && root.clientHeight > 0 && !exiting() && performance.now() >= skipCatchUntil) pin();
            return;
        }
        if (locked) {                                           // Durante la parada la página no se mueve.
            if (Math.abs(y - target) > 1) window.scrollTo({ top: target, behavior: 'instant' });
            lastY = target;
            return;
        }
        const crossed = (lastY < target - 1 && y >= target - 1) || (lastY > target + 1 && y <= target + 1);
        lastY = y;
        if (!crossed || root.clientHeight === 0 || exiting() || performance.now() < skipCatchUntil) return;
        window.scrollTo({ top: target, behavior: 'instant' });
        lastY = target;
        startLock(450);
    }, { passive: true });

    /* -------------------------------------------------------------------------
       8. Volver desde la ficha de una foto
       «Volver a la galería» enlaza a /#baraja=<slug>; el botón «Atrás» del navegador usa la última foto
       abierta (sessionStorage). En ambos casos la baraja se abre en esa foto, a pantalla completa.
       ------------------------------------------------------------------------- */
    const SLUG_KEY = 'djl-deck-slug';

    root.addEventListener('click', event => {
        const card = event.target instanceof Element ? event.target.closest('.deck__card') : null;
        if (card?.dataset.slug) {
            try { sessionStorage.setItem(SLUG_KEY, card.dataset.slug); } catch (_) { /* sin almacenamiento: no pasa nada */ }
        }
    });

    const slugFromHash = () => {
        const match = /^#baraja=(.+)$/.exec(window.location.hash);
        if (!match) return '';
        try { return decodeURIComponent(match[1]); } catch (_) { return ''; }
    };

    const restoreFromSlug = () => {
        const fromHash = slugFromHash();
        let wanted = fromHash;
        if (!wanted) {
            const navigation = performance.getEntriesByType?.('navigation')?.[0];
            if (navigation?.type === 'back_forward') {
                try { wanted = sessionStorage.getItem(SLUG_KEY) || ''; } catch (_) { wanted = ''; }
            }
        }
        const target = wanted ? visible.findIndex(card => card.dataset.slug === wanted) : -1;
        if (target < 0) return;
        index = target;
        root.classList.add('is-used');
        render();
        if (fromHash) window.history.replaceState(null, '', window.location.pathname + window.location.search);
        // Lleva la baraja a pantalla completa. La página puede recolocarse mientras carga: se repite.
        const showDeck = () => {
            skipCatchUntil = performance.now() + 900;
            if (pinned) unpin();
            window.scrollTo({ top: deckTop(), behavior: 'instant' });
            pin();
        };
        showDeck();
        window.addEventListener('load', showDeck, { once: true });
        window.setTimeout(showDeck, 500);
    };

    /* -------------------------------------------------------------------------
       9. Salir de la galería y estado «acoplada»
       · «Salir» (abajo) baja la página hasta el final de la baraja: lo que sigue en la web.
       · «Salir hacia arriba» sube hasta lo que hay antes de la baraja (solo si hay algo encima).
       · En móvil, deslizar más allá de la última foto sale hacia abajo y más allá de la primera, hacia arriba.
       · Durante la salida la baraja no captura gestos ni vuelve a frenar o fijar la página. Para volver, basta
         con desplazarse hacia ella: al cruzarla se detiene o se fija otra vez (sección 7).
       · Mientras la baraja está acoplada, la página recibe html.deck-engaged: el CSS aparta los botones
         flotantes de la web (se pisaban con la barra inferior).
       ------------------------------------------------------------------------- */
    const leaveDeck = direction => {
        const smooth = !reducedMotion.matches;
        exitUntil = performance.now() + (smooth ? 1400 : 300);
        skipCatchUntil = exitUntil;
        lockUntil = 0;                                           // Cancela cualquier parada en curso.
        const from = deckTop();
        unpin();                                                 // Devuelve el scroll a la baraja antes de salir.
        const top = direction > 0 ? from + root.offsetHeight : Math.max(0, from - window.innerHeight);
        window.scrollTo({ top, behavior: smooth ? 'smooth' : 'instant' });
    };

    // «Salir hacia arriba» solo tiene sentido si hay contenido encima de la baraja.
    function updateExitUp() {
        if (exitUpButton) exitUpButton.hidden = deckTop() < 4;
    }

    exitDownButton?.addEventListener('click', () => leaveDeck(1));
    exitUpButton?.addEventListener('click', () => leaveDeck(-1));

    window.addEventListener('scroll', setEngagedClass, { passive: true });

    // Ajuste a la pantalla al cargar, al girar el dispositivo y al redimensionar.
    let resizeTimer = 0;
    const onResize = () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => {
            fitCards();
            // Fijada, la baraja se adapta sola al nuevo tamaño (el cuerpo es fijo y el alto es 100dvh). Solo si ya no queda en el borde
            // superior (el contenido de encima cambió de alto) se vuelve a fijar. Volver a fijar siempre provocaba, en iPhone, un bucle
            // de «soltar y fijar» con cada cambio de la barra del navegador.
            if (pinned && Math.abs(rect().top) > 2) {
                unpin();
                window.requestAnimationFrame(() => pin());
            }
            setEngagedClass();
            updateExitUp();
        }, 120);
    };
    window.addEventListener('resize', onResize);
    window.addEventListener('orientationchange', onResize);

    fitCards();
    render();
    restoreFromSlug();
    setEngagedClass();
    updateExitUp();
    // Si la web arranca con la baraja ya a pantalla completa (p. ej. cabecera oculta), en táctil se fija desde el principio.
    if (coarse.matches && Math.abs(rect().top) <= 1 && window.scrollY <= 1) pin();
})();
