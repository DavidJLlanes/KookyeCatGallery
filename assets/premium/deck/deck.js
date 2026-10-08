/* =============================================================================
   GALERÍA PREMIUM · ESTILO BARAJA (clave `deck`) · comportamiento
   -----------------------------------------------------------------------------
   Marcado: inc/premium/deck.php · Estilos y animación: deck.css
   Documentación: docs/galerias-premium.md y README.md

   Qué hace
     · Mantiene una posición activa (`index`) y le dice a cada carta dónde está respecto a ella
       (data-pos). El CSS se encarga de colocarla y animarla en 3D. «Estilo Tambor» (data-layout="drum") usa este mismo
       archivo: cambia el CSS (drum.css) y que las cartas pasadas no salen, sino que siguen en el cilindro.
     · Avanza con: rueda del ratón hacia abajo, deslizar el dedo hacia arriba (o hacia la izquierda),
       flechas del teclado, RePág/AvPág, Espacio y los botones de la barra inferior.
     · Retrocede con los gestos contrarios.
     · Filtra por categoría (chips del bloque «Categorías») y por favoritas (botón «Favoritas»).
     · Ajusta cada foto a su carta: la cubre entera si su proporción es parecida a la del marco y, si no,
       la muestra completa sobre el fondo de la carta (nada de recortes absurdos).

   Cómo se queda fija la galería
     · ESCRITORIO (ratón/trackpad): mientras la baraja llena la pantalla, la rueda mueve fotos y se cancela
       el scroll de la página. Al llegar a la baraja el scroll se detiene (aunque haya inercia).
     · MÓVIL/TABLET (táctil): al llegar, la baraja se FIJA: el cuerpo de la página pasa a position: fixed y
       deja de existir el scroll, así que no hay nada que se mueva ni «vibre». Los gestos solo mueven fotos.
       Se sale con el botón «Salir» (abajo o arriba) o deslizando más allá de la última o la primera foto.
     · Desde la ficha de una foto, «Volver a la galería» regresa a esa misma foto (enlace /#baraja=<slug>).

   Organización del archivo
     1. Estado y utilidades        6. Entrada: teclado, botones y filtros
     2. Filtros                    7. Fijar la galería (móvil) / parada del scroll (escritorio)
     3. Pintado (render)           8. Volver desde la ficha de una foto
     4. Entrada: rueda             9. Salir de la galería y estado «acoplada»
     5. Entrada: táctil
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
    const prevButton = root.querySelector('[data-deck-prev]');
    const nextButton = root.querySelector('[data-deck-next]');
    const exitDownButton = root.querySelector('[data-deck-exit]');
    const exitUpButton = root.querySelector('[data-deck-exit-up]');
    const emptyMessage = root.querySelector('[data-deck-empty]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const coarse = window.matchMedia('(hover: none) and (pointer: coarse)');   // Pantalla táctil sin ratón.
    const drum = root.dataset.layout === 'drum';   // «Estilo Tambor»: las cartas pasadas siguen visibles a la izquierda (cilindro).

    let visible = cards.slice();   // Cartas que pasan los filtros, en orden.
    let index = 0;                 // Posición de la carta activa dentro de `visible`.

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
    const fitCards = () => {
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
        visible.forEach((card, i) => {
            // Baraja: -2/-1 = ya pasadas (salen), 1..3 = mazo, 4 = oculta. Tambor: -3..3 a ambos lados (±3 = ocultas, atrás del cilindro).
            const pos = drum ? clamp(i - index, -3, 3) : clamp(i - index, -2, 4);
            card.dataset.pos = String(pos);
            const active = pos === 0;
            card.setAttribute('aria-hidden', active ? 'false' : 'true');
            card.toggleAttribute('inert', !active);
            // Las fotos cercanas se cargan ya, para que el mazo nunca enseñe huecos.
            if (i >= index - 2 && i <= index + 3) {
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
       5. Entrada: táctil
       Dedo hacia arriba (o hacia la izquierda) = siguiente. El gesto contrario = anterior.
       En móvil la baraja está fijada (sección 7): el navegador no desplaza nada. Si el gesto va más allá de la
       última o la primera foto, se sale de la galería por script (sección 9).
       ------------------------------------------------------------------------- */
    const STEP_DISTANCE = 42;   // px de recorrido para pasar de carta durante el gesto.
    const FLICK_DISTANCE = 18;  // px mínimos de un gesto rápido.
    const FLICK_TIME = 220;     // ms máximos de un gesto rápido.
    const LEAVE_DISTANCE = 24;  // px de recorrido para salir de la galería en un límite.
    let touch = null;

    root.addEventListener('touchstart', event => {
        const point = event.touches[0];
        touch = event.touches.length === 1 && isEngaged()
            ? { x: point.clientX, y: point.clientY, time: event.timeStamp, axis: null, done: false, direction: 0, distance: 0 }
            : null;
    }, { passive: true });

    root.addEventListener('touchmove', event => {
        if (exiting()) { touch = null; return; }   // Salida en curso: este gesto no mueve cartas.
        if (!touch || event.touches.length !== 1) return;
        if (touch.done) return;                    // El gesto ya hizo lo suyo; el documento cancela el resto.
        const point = event.touches[0];
        const dx = point.clientX - touch.x;
        const dy = point.clientY - touch.y;
        if (touch.axis === null) {
            if (Math.hypot(dx, dy) < 8) return;
            touch.axis = Math.abs(dy) >= Math.abs(dx) ? 'y' : 'x';
        }
        const delta = touch.axis === 'y' ? dy : dx;
        const direction = delta < 0 ? 1 : -1;
        if (!canStep(direction)) {
            // Límite (primera o última foto): se sale de la galería hacia ese lado.
            if (Math.abs(delta) >= LEAVE_DISTANCE) { touch.done = true; leaveDeck(direction); }
            return;
        }
        if (!isAligned()) { align(); touch.done = true; return; }
        touch.direction = direction;
        touch.distance = Math.abs(delta);
        if (touch.distance >= STEP_DISTANCE) { step(direction); touch.done = true; }
    }, { passive: true });

    const endTouch = event => {
        if (touch && !touch.done && touch.direction && touch.distance >= FLICK_DISTANCE
            && event.timeStamp - touch.time <= FLICK_TIME) step(touch.direction);
        touch = null;
    };
    root.addEventListener('touchend', endTouch, { passive: true });
    root.addEventListener('touchcancel', () => { touch = null; }, { passive: true });

    /* -------------------------------------------------------------------------
       6. Entrada: teclado, botones y filtros
       ------------------------------------------------------------------------- */
    document.addEventListener('keydown', event => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
        if (document.body.classList.contains('slideshow-open') || exiting()) return;
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
            if (pinned) {                                        // Cambió el tamaño: se vuelve a fijar en el sitio exacto.
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
