/* =============================================================================
   GALERÍA PREMIUM · ESTILO CILINDRO (clave `cylinder`) · comportamiento
   -----------------------------------------------------------------------------
   Marcado: inc/premium/cylinder.php · Estilos: deck.css (interfaz) + cylinder.css · Documentación: docs/galerias-premium.md

   Qué hace
     · Coloca las fotos en un cilindro giratorio: varias filas (bandas) de fotos de distintos tamaños alrededor de un eje vertical.
       La geometría se calcula aquí y se pinta en cada fotograma, porque el cilindro no es redondo: en pantallas anchas es una
       elipse estirada hacia los bordes y en móvil ocupa el ancho entero (el radio se ajusta para que la silueta llegue a los bordes).
     · Cada «paso» gira el cilindro una foto (todas las filas a la vez). Las filas se cierran sobre sí mismas: tras la última foto
       vuelve la primera por detrás, pero el paso se detiene en la primera y la última (como en la baraja) para poder salir de la galería.
     · Comparte con «Estilo Baraja» (deck.js) la interfaz y el comportamiento: rueda, dedo, teclado, flechas y contador, filtros,
       parada del scroll en escritorio, fijación en móvil (body fixed), botones de salir (abajo y arriba) y vuelta desde la ficha
       (/#baraja=<slug>).

   Organización del archivo
     1. Estado y utilidades          6. Entrada: teclado, botones y filtros
     2. Filtros                      7. Fijar la galería (móvil) / parada del scroll (escritorio)
     3. Geometría y pintado          8. Volver desde la ficha de una foto
     4. Entrada: rueda               9. Salir de la galería y estado «acoplada»
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
    const cards = [...root.querySelectorAll('.cyl__item')];
    const stage = root.querySelector('[data-cyl-stage]');
    const counterCurrent = root.querySelector('[data-deck-current]');
    const counterTotal = root.querySelector('[data-deck-total]');
    const prevButton = root.querySelector('[data-deck-prev]');
    const nextButton = root.querySelector('[data-deck-next]');
    const exitDownButton = root.querySelector('[data-deck-exit]');
    const exitUpButton = root.querySelector('[data-deck-exit-up]');
    const emptyMessage = root.querySelector('[data-deck-empty]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const coarse = window.matchMedia('(hover: none) and (pointer: coarse)');   // Pantalla táctil sin ratón.

    let visible = cards.slice();   // Fotos que pasan los filtros, en orden.
    let index = 0;                 // Paso actual: cuántas fotos ha girado el cilindro (destino de la animación).
    let shown = 0;                 // Paso mostrado ahora mismo (decimal mientras gira).

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
        index = 0;
        shown = 0;
        relayout();
        render();
    };

    /* -------------------------------------------------------------------------
       3. Geometría y pintado
       Cada foto vive en una fila (bandas horizontales apiladas) y en una posición de la fila (su «hueco» en el anillo). Una fila
       tiene `slots` huecos repartidos en 360°. El cilindro gira `shown` huecos. Cada fotograma se coloca en 3D solo lo que está
       en la mitad delantera del anillo, con su giro (rotateY), profundidad (z), oscurecimiento y orden.
       ------------------------------------------------------------------------- */
    const PERSPECTIVE = 1200;      // px: la misma que usa el CSS del escenario.
    const STEP_TIME = 850;         // ms que tarda en girar una foto (la misma que --deck-duration de la baraja).
    const RAD = Math.PI / 180;
    let geo = null;                // Geometría actual (se recalcula al cambiar el tamaño o los filtros).
    let strips = [];               // strips[fila] = fotos de esa fila, en orden.

    const mod = (n, m) => ((n % m) + m) % m;
    // Tamaños «aleatorios» pero estables de cada foto (no cambian al girar ni al redimensionar).
    const unit = (i, salt) => { const x = Math.sin((i + 1) * 12.9898 + salt * 78.233) * 43758.5453; return x - Math.floor(x); };

    const stepCount = () => Math.max(1, Math.ceil(visible.length / (geo ? geo.rows : 1)));

    // Radio horizontal para que la silueta del cilindro (ya con la perspectiva) llegue justo a los bordes de la pantalla.
    const radiusFor = (width, ratio) => {
        const reach = rx => {
            let best = 0;
            for (let deg = 0; deg <= 90; deg += 1.5) {
                const t = deg * RAD, depth = rx * ratio * (1 - Math.cos(t));
                best = Math.max(best, rx * Math.sin(t) * PERSPECTIVE / (PERSPECTIVE + depth));
            }
            return best;
        };
        let low = width * 0.2, high = width * 3;
        for (let i = 0; i < 22; i++) { const mid = (low + high) / 2; if (reach(mid) < width / 2 * 0.985) low = mid; else high = mid; }
        return (low + high) / 2;
    };

    const relayout = () => {
        const width = stage.clientWidth, height = stage.clientHeight;
        if (width < 40 || height < 40) { geo = null; return; }
        // Filas: bandas de ~la mitad del lado menor, entre 1 y 4 (móvil vertical: 3; escritorio: 2).
        const rows = clamp(Math.round(height / clamp(Math.min(width * 0.5, height * 0.5), 150, 460)), 1, 4);
        const rowH = height / rows;
        // Escritorio / apaisado: elipse estirada hacia los bordes (poca profundidad). Móvil vertical: cilindro más redondo.
        const wide = width / height > 1.2;
        const ratio = wide ? 0.5 : 0.78;
        const rx = radiusFor(width, ratio), rz = rx * ratio;
        const longest = Math.ceil(visible.length / rows);
        const slots = Math.max(1, Math.min(longest, Math.max(6, Math.round(2 * Math.PI * rx / rowH))));
        geo = { width, height, rows, rowH, rx, rz, slots };
        strips = Array.from({ length: rows }, (_, r) => visible.filter((_, i) => i % rows === r));
        cards.forEach(card => { if (!visible.includes(card)) card.style.display = 'none'; });
        draw();
    };

    // Coloca todas las fotos de la mitad delantera según el paso mostrado.
    const draw = () => {
        if (!geo) return;
        const { width, rows, rowH, rx, rz, slots } = geo;
        const placed = new Set();
        strips.forEach((strip, r) => {
            const length = strip.length;
            if (!length) return;
            const count = Math.min(slots, length), arc = 360 / count;
            const stagger = (r % 2) * arc / 2;                          // Las filas impares van medio hueco desplazadas (aspecto de ladrillo).
            const half = Math.ceil(count / 2) + 1;
            const base = Math.round(shown);
            const baseWidth = Math.min(rx * arc * RAD, rowH * 1.6);     // Ancho de un hueco (en el frente), con tope.
            const centerY = (r + 0.5) * rowH;
            for (let q = base - half; q <= base + half; q++) {
                const angle = ((((q - shown) * arc + stagger) % 360) + 540) % 360 - 180;
                if (Math.abs(angle) > 100) continue;
                const card = strip[mod(q, length)];
                if (placed.has(card)) continue;
                placed.add(card);
                const seed = cards.indexOf(card);
                const t = angle * RAD, sin = Math.sin(t), cos = Math.cos(t);
                const along = Math.hypot(rx * cos, rz * sin) / rx;       // Cuánto se estira el hueco según el ángulo (elipse).
                const w = baseWidth * along * (0.78 + 0.22 * unit(seed, 1));
                const h = rowH * 0.92 * (0.64 + 0.36 * unit(seed, 2));
                const facing = Math.atan2(sin / rx, cos / rz) / RAD;     // Normal de la elipse: hacia dónde mira la foto.
                card.style.display = 'block';
                card.style.width = `${w.toFixed(1)}px`;
                card.style.height = `${h.toFixed(1)}px`;
                card.style.transform = `translate3d(${(width / 2 + rx * sin - w / 2).toFixed(1)}px, ${(centerY - h / 2).toFixed(1)}px, ${(rz * (cos - 1)).toFixed(1)}px) rotateY(${facing.toFixed(2)}deg)`;
                card.style.filter = `brightness(${(0.4 + 0.6 * Math.max(0, cos)).toFixed(2)})`;
                card.style.zIndex = String(1000 + Math.round(cos * 1000));
                const image = card.querySelector('.cyl__img');
                if (image && image.loading === 'lazy') image.loading = 'eager';
            }
        });
        cards.forEach(card => { if (!placed.has(card)) card.style.display = 'none'; });
    };

    // Animación del giro: de `shown` a `index` en STEP_TIME ms (instantáneo con movimiento reducido).
    let spinFrame = 0;
    const spinTo = target => {
        window.cancelAnimationFrame(spinFrame);
        const from = shown, start = performance.now();
        if (reducedMotion.matches || from === target) { shown = target; draw(); return; }
        const ease = t => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
        const tick = now => {
            const t = Math.min(1, (now - start) / STEP_TIME);
            shown = from + (target - from) * ease(t);
            draw();
            if (t < 1) spinFrame = window.requestAnimationFrame(tick);
        };
        spinFrame = window.requestAnimationFrame(tick);
    };

    const render = () => {
        const total = stepCount();
        if (counterCurrent) counterCurrent.textContent = String(visible.length ? index + 1 : 0);
        if (counterTotal) counterTotal.textContent = String(visible.length ? total : 0);
        if (prevButton) prevButton.disabled = index <= 0;
        if (nextButton) nextButton.disabled = index >= total - 1;
        if (emptyMessage) emptyMessage.hidden = visible.length > 0;
        root.dataset.index = String(index);
        spinTo(index);
    };

    // Gira el cilindro. Devuelve false si ya estaba en el límite (primera o última foto).
    const step = delta => {
        const next = clamp(index + delta, 0, stepCount() - 1);
        if (next === index) return false;
        index = next;
        root.classList.add('is-used');   // Oculta la pista inicial.
        render();
        return true;
    };

    const canStep = delta => {
        const next = index + delta;
        return next >= 0 && next <= stepCount() - 1;
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
        else if (event.key === 'End' && index < stepCount() - 1) { event.preventDefault(); step(stepCount() - 1 - index); return; }
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
        const card = event.target instanceof Element ? event.target.closest('.cyl__item') : null;
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
        index = Math.floor(target / (geo ? geo.rows : 1));   // La foto queda al frente de su fila.
        shown = index;
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
            relayout();
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

    relayout();
    render();
    restoreFromSlug();
    setEngagedClass();
    updateExitUp();
    // Si la web arranca con la baraja ya a pantalla completa (p. ej. cabecera oculta), en táctil se fija desde el principio.
    if (coarse.matches && Math.abs(rect().top) <= 1 && window.scrollY <= 1) pin();
})();
