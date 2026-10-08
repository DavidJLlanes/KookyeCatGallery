/* =============================================================================
   GALERÍA PREMIUM · ESTILO BURBUJAS (clave `bubbles`) · comportamiento
   -----------------------------------------------------------------------------
   Marcado: inc/premium/bubbles.php · Estilos y animación: bubbles.css
   Documentación: docs/galerias-premium.md y README.md

   Qué hace
     · Reparte las fotos de la página actual como círculos de distintos tamaños, al azar pero sin solaparse ni salirse de la
       zona útil, de modo que llenan la pantalla entera en cualquier dispositivo. Cada círculo flota lentamente (CSS).
     · Pagina según «Fotos visibles a la vez» (data-photos-mobile / data-photos-desktop del <body>; 0 = todas, con un tope
       para que los círculos no se hagan diminutos). Cambia de página con: rueda del ratón, deslizar el dedo hacia arriba (o la
       izquierda), flechas del teclado, RePág/AvPág, Espacio y la paginación minimalista.
     · Filtra por categoría (chips del bloque «Categorías») y por favoritas (botón «Favoritas»).
     · Al pulsar un círculo rebota y se abre siempre su ficha (/foto/<slug>), sin pasos intermedios.

   Lo que comparte con «Estilo Baraja» (deck.js): fijación en móvil (body fixed), parada del scroll en escritorio, botones
   de salir (abajo y arriba), vuelta desde la ficha (/#baraja=<slug>) y estado «acoplada» (html.deck-engaged).

   Organización del archivo
     1. Estado y utilidades          6. Entrada: teclado, botones y filtros
     2. Filtros                      7. Fijar la galería (móvil) / parada del scroll (escritorio)
     3. Reparto y pintado            8. Abrir una foto (rebote) y volver desde la ficha
     4. Entrada: rueda               9. Salir de la galería y estado «acoplada»
     5. Entrada: táctil
   ============================================================================= */
(() => {
    'use strict';

    const root = document.getElementById('bubbles');
    if (!root) return;

    /* -------------------------------------------------------------------------
       1. Estado y utilidades
       ------------------------------------------------------------------------- */
    const html = document.documentElement;
    const field = root.querySelector('[data-bubbles-field]');
    const items = [...root.querySelectorAll('.bubbles__item')];
    const counterCurrent = root.querySelector('[data-bubbles-current]');
    const counterTotal = root.querySelector('[data-bubbles-total]');
    const prevButton = root.querySelector('[data-bubbles-prev]');
    const nextButton = root.querySelector('[data-bubbles-next]');
    const exitDownButton = root.querySelector('[data-bubbles-exit]');
    const exitUpButton = root.querySelector('[data-bubbles-exit-up]');
    const emptyMessage = root.querySelector('[data-bubbles-empty]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const coarse = window.matchMedia('(hover: none) and (pointer: coarse)');   // Pantalla táctil sin ratón.
    const narrow = window.matchMedia('(max-width: 768px)');

    let visible = items.slice();   // Fotos que pasan los filtros, en orden.
    let page = 0;                  // Página actual (empieza en 0).
    let transitioning = false;     // Cambio de página en curso (salen unas burbujas y entran otras).

    // Tiempos (ms).
    const STEP_COOLDOWN = 800;     // Mínimo entre dos cambios de página con la rueda.
    const WHEEL_QUIET = 120;       // Pausa que separa un gesto de rueda del siguiente.
    const OUT_TIME = 420;          // Duración de la salida de las burbujas (bubbles.css: bubbles-out).

    // Reparto de círculos (px).
    const GAP = 24;                // Separación mínima entre círculos (más que lo que se desplazan al flotar).
    const FLOAT = 4;               // Amplitud máxima de la flotación (bubbles.css la usa en --fx / --fy).
    const MIN_RADIUS = 30;         // Radio mínimo razonable: limita cuántas burbujas caben en una página.

    html.classList.add('has-deck');   // Marca la página para que otros estilos puedan reaccionar.

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const rect = () => root.getBoundingClientRect();

    // Fijación táctil (sección 7): el cuerpo de la página se congela con position: fixed.
    let pinned = false;
    let pinY = 0;                  // Posición de scroll en la que se fijó la galería.

    // Posición de la galería en el documento. Fijada, el scroll vale 0 y se usa la guardada.
    const deckTop = () => (pinned ? pinY : rect().top + window.scrollY);

    // «Acoplada»: la galería llena la pantalla. Solo entonces captura la rueda, el dedo y el teclado.
    //   · Táctil: lo está cuando está fijada.
    //   · Escritorio: cuando ocupa casi toda la pantalla.
    const isEngaged = () => {
        if (coarse.matches) return pinned;
        const r = rect();
        const vh = window.innerHeight;
        return r.width > 0 && r.top <= vh * 0.12 && r.bottom >= vh * 0.88;
    };
    const isAligned = () => Math.abs(rect().top) <= 2;

    // Salida (botones «Salir» o gesto en un límite): mientras dura, la galería no captura ningún gesto.
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

    // Alinea la galería con el borde superior de la pantalla (escritorio; una sola vez por gesto).
    let aligning = false;
    const align = () => {
        if (aligning || isAligned()) return;
        aligning = true;
        window.scrollBy({ top: rect().top, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
        window.setTimeout(() => { aligning = false; }, 450);
        startLock(450);   // Lo que quede del gesto que trajo hasta aquí no debe cambiar de página.
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
        visible = items.filter(item =>
            (!category || item.dataset.category === category) &&
            (!saved || saved.has(item.dataset.slug || '')));
        items.forEach(item => { item.hidden = true; item.classList.remove('is-in', 'is-out'); });
        page = 0;
        firstShown = 0;
        showPage();
    };

    /* -------------------------------------------------------------------------
       3. Reparto y pintado
       ------------------------------------------------------------------------- */
    // Generador pseudoaleatorio con semilla: el reparto de una página es siempre el mismo (no «salta» al redimensionar).
    const seeded = seed => () => {
        seed = (seed + 0x6D2B79F5) | 0;
        let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };

    const fieldSize = () => ({ width: field.clientWidth, height: field.clientHeight });

    // Cuántas burbujas por página: «Fotos visibles a la vez» del panel (0 = todas), con un tope según el espacio.
    const perPage = () => {
        const configured = Number(document.body.dataset[narrow.matches ? 'photosMobile' : 'photosDesktop']);
        const wanted = configured === 0 ? Infinity : (configured > 0 ? configured : (narrow.matches ? 12 : 20));
        const { width, height } = fieldSize();
        const fits = Math.max(1, Math.floor((width * height * 0.58) / (Math.PI * (MIN_RADIUS + GAP / 2) ** 2)));
        return Math.max(1, Math.min(wanted, fits));
    };
    const pageCount = () => Math.max(1, Math.ceil(visible.length / perPage()));

    // Reparte `count` círculos en una zona de width × height: tamaños distintos, al azar, sin solaparse ni salirse, ocupando
    // todo el espacio. 1) Busca el mayor tamaño base con el que caben todos (búsqueda binaria + colocación aleatoria).
    // 2) Los hace crecer hasta casi tocar a sus vecinos o el borde, para que no queden huecos grandes.
    const pack = (count, width, height, seed) => {
        const margin = FLOAT + 6;                         // Los círculos flotan: nunca tocan el borde de la zona.
        const W = Math.max(10, width - margin * 2);
        const H = Math.max(10, height - margin * 2);
        const rng = seeded(seed);
        const weights = Array.from({ length: count }, () => 0.55 + rng() * 0.9).sort((a, b) => b - a);
        const fits = (a, circles) => circles.every(b => Math.hypot(a.x - b.x, a.y - b.y) >= a.r + b.r + GAP);

        const attempt = (scale, attemptSeed) => {
            const random = seeded(attemptSeed);
            const placed = [];
            for (const weight of weights) {
                const r = scale * weight;
                if (2 * r > W || 2 * r > H) return null;
                let ok = false;
                for (let tries = 0; tries < 140 && !ok; tries++) {
                    const circle = { x: r + random() * (W - 2 * r), y: r + random() * (H - 2 * r), r };
                    if (fits(circle, placed)) { placed.push(circle); ok = true; }
                }
                if (!ok) return null;
            }
            return placed;
        };

        let low = 6, high = Math.min(W, H) / 2 / 0.55, best = null;
        for (let step = 0; step < 16; step++) {
            const mid = (low + high) / 2;
            let result = null;
            for (let k = 0; k < 3 && !result; k++) result = attempt(mid, seed + k * 7919);
            if (result) { best = result; low = mid; } else { high = mid; }
        }
        if (!best) {                                       // Último recurso: cuadrícula de círculos iguales.
            const cols = Math.ceil(Math.sqrt(count * W / H)), rows = Math.ceil(count / cols);
            const r = Math.max(4, Math.min(W / cols, H / rows) / 2 - GAP / 2);
            best = Array.from({ length: count }, (_, i) => ({ x: ((i % cols) + 0.5) * W / cols, y: (Math.floor(i / cols) + 0.5) * H / rows, r }));
        }

        // Crecimiento: cada círculo se agranda mientras no toque a otro (con su separación) ni el borde.
        const limit = Math.min(W, H) * 0.4;
        const order = best.map((_, i) => i);
        for (let pass = 0; pass < 6; pass++) {
            for (let i = order.length - 1; i > 0; i--) { const j = Math.floor(rng() * (i + 1)); [order[i], order[j]] = [order[j], order[i]]; }
            for (const i of order) {
                const c = best[i];
                let room = Math.min(c.x, W - c.x, c.y, H - c.y, limit);
                for (let j = 0; j < best.length; j++) if (j !== i) room = Math.min(room, Math.hypot(c.x - best[j].x, c.y - best[j].y) - best[j].r - GAP);
                if (room > c.r) c.r += (room - c.r) * (pass < 5 ? 0.7 : 1);
            }
        }
        return best.map(c => ({ x: c.x + margin, y: c.y + margin, r: c.r }));
    };

    const pageItems = () => {
        const size = perPage();
        return visible.slice(page * size, page * size + size);
    };

    // Coloca las burbujas de la página actual (sin animarlas).
    const place = list => {
        const { width, height } = fieldSize();
        if (!list.length || width < 40 || height < 40) return;
        const circles = pack(list.length, width, height, (page + 1) * 7919 + visible.length * 104729);
        const random = seeded(page * 31 + list.length);
        // Los círculos grandes reciben las primeras posiciones del orden de la página: reparto variado, no por orden de tamaño.
        const bySize = circles.slice().sort((a, b) => b.r - a.r);
        const shuffled = list.map((_, i) => i).sort(() => random() - 0.5);
        list.forEach((item, i) => {
            const c = bySize[shuffled[i]];
            item.style.left = `${(c.x - c.r).toFixed(1)}px`;
            item.style.top = `${(c.y - c.r).toFixed(1)}px`;
            item.style.width = item.style.height = `${(2 * c.r).toFixed(1)}px`;
            item.style.setProperty('--fx', `${((random() * 0.6 + 0.4) * FLOAT * (random() < 0.5 ? -1 : 1)).toFixed(1)}px`);
            item.style.setProperty('--fy', `${((random() * 0.6 + 0.4) * FLOAT * (random() < 0.5 ? -1 : 1)).toFixed(1)}px`);
            item.style.setProperty('--fd', `${(9 + random() * 7).toFixed(1)}s`);
            item.style.setProperty('--fl', `${(-random() * 12).toFixed(1)}s`);
        });
    };

    const updatePager = () => {
        const pages = pageCount();
        if (counterCurrent) counterCurrent.textContent = String(visible.length ? page + 1 : 0);
        if (counterTotal) counterTotal.textContent = String(visible.length ? pages : 0);
        if (prevButton) prevButton.disabled = page <= 0;
        if (nextButton) nextButton.disabled = page >= pages - 1;
        if (emptyMessage) emptyMessage.hidden = visible.length > 0;
        root.dataset.page = String(page);
    };

    // Muestra la página actual: coloca sus burbujas y las hace entrar con rebote.
    const showPage = () => {
        page = clamp(page, 0, pageCount() - 1);
        const list = pageItems();
        items.forEach(item => { if (!list.includes(item)) item.hidden = true; });
        list.forEach((item, i) => {
            item.hidden = false;
            item.classList.remove('is-out');
            item.style.setProperty('--i', String(i));
            const image = item.querySelector('.bubbles__img');
            if (image && image.loading === 'lazy') image.loading = 'eager';
        });
        place(list);
        list.forEach(item => { item.classList.remove('is-in'); void item.offsetWidth; item.classList.add('is-in'); });
        updatePager();
    };

    // Recoloca sin animar (al redimensionar o girar). Si cambia el número de burbujas por página, conserva la primera foto.
    let firstShown = 0;
    const relayout = () => {
        page = Math.floor(firstShown / perPage());
        const list = pageItems();
        items.forEach(item => { item.hidden = !list.includes(item); });
        place(list);
        updatePager();
    };

    // Cambia de página: las burbujas actuales salen y entran las de la nueva. Devuelve false si no hay página en esa dirección.
    const step = delta => {
        const next = clamp(page + delta, 0, pageCount() - 1);
        if (next === page || transitioning) return false;
        root.classList.add('is-used');   // Oculta la pista inicial.
        const leaving = pageItems();
        transitioning = true;
        leaving.forEach((item, i) => { item.style.setProperty('--i', String(i)); item.classList.remove('is-in'); item.classList.add('is-out'); });
        window.setTimeout(() => {
            leaving.forEach(item => { item.hidden = true; item.classList.remove('is-out'); });
            page = next;
            firstShown = page * perPage();
            showPage();
            transitioning = false;
        }, reducedMotion.matches ? 0 : OUT_TIME + leaving.length * 18);
        return true;
    };

    const canStep = delta => {
        const next = page + delta;
        return !transitioning && next >= 0 && next <= pageCount() - 1;
    };

    /* -------------------------------------------------------------------------
       4. Entrada: rueda del ratón / trackpad
       La rueda hacia abajo pasa a la página siguiente, hacia arriba a la anterior. En la primera y la última página no se captura.
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
       Dedo hacia arriba (o hacia la izquierda) = página siguiente. El gesto contrario = anterior.
       En móvil la baraja está fijada (sección 7): el navegador no desplaza nada. Si el gesto va más allá de la
       última o la primera página, se sale de la galería por script (sección 9).
       ------------------------------------------------------------------------- */
    const STEP_DISTANCE = 42;   // px de recorrido para pasar de página durante el gesto.
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
            // Límite (primera o última página): se sale de la galería hacia ese lado.
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
        else if (event.key === 'Home' && page > 0) { event.preventDefault(); step(-page); return; }
        else if (event.key === 'End' && page < pageCount() - 1) { event.preventDefault(); step(pageCount() - 1 - page); return; }
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
       8. Abrir una foto (rebote) y volver desde la ficha
       Al pulsar un círculo rebota y se abre su ficha. «Volver a la galería» enlaza a /#baraja=<slug>; el botón «Atrás» del navegador usa la última foto abierta
       (sessionStorage). En ambos casos la galería se abre en la página de esa foto, a pantalla completa.
       ------------------------------------------------------------------------- */
    const SLUG_KEY = 'djl-deck-slug';
    let opening = false;

    const BOUNCE_TIME = 420;       // Duración del rebote antes de abrir la ficha (ms).

    // Pulsar un círculo: rebota y, pasado ese tiempo, SIEMPRE se abre la ficha de la foto (/foto/<slug>). La navegación
    // no depende de que termine la animación (un temporizador la garantiza), y no se muestra la foto a pantalla completa.
    const open = (item, link) => {
        if (opening) return;
        opening = true;
        if (item.dataset.slug) {
            try { sessionStorage.setItem(SLUG_KEY, item.dataset.slug); } catch (_) { /* sin almacenamiento: no pasa nada */ }
        }
        const href = link.getAttribute('href');
        const smooth = !reducedMotion.matches && typeof link.animate === 'function';
        if (smooth) {
            // Rebote del círculo: se encoge, se pasa de tamaño y se asienta.
            link.animate([
                { transform: 'scale(1)' }, { transform: 'scale(.82)', offset: .24 }, { transform: 'scale(1.16)', offset: .58 },
                { transform: 'scale(.96)', offset: .8 }, { transform: 'scale(1)' }]
            , { duration: BOUNCE_TIME, easing: 'ease-out' });
        }
        window.setTimeout(() => {
            if (href) window.location.href = href;
            else opening = false;                          // Foto sin ficha propia: solo rebota.
        }, smooth ? BOUNCE_TIME : 0);
    };

    // Si se vuelve con «Atrás» (caché del navegador), se puede volver a pulsar.
    window.addEventListener('pageshow', () => { opening = false; });

    root.addEventListener('click', event => {
        const link = event.target instanceof Element ? event.target.closest('.bubbles__link') : null;
        if (!link) return;
        const item = link.closest('.bubbles__item');
        if (!item) return;
        if (link.tagName === 'A' && (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) return;
        event.preventDefault();
        open(item, link);
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
        const target = wanted ? visible.findIndex(item => item.dataset.slug === wanted) : -1;
        if (target < 0) return;
        page = Math.floor(target / perPage());
        firstShown = page * perPage();
        root.classList.add('is-used');
        showPage();
        if (fromHash) window.history.replaceState(null, '', window.location.pathname + window.location.search);
        // Lleva la galería a pantalla completa. La página puede recolocarse mientras carga: se repite.
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

    firstShown = 0;
    showPage();
    restoreFromSlug();
    setEngagedClass();
    updateExitUp();
    // Si la web arranca con la galería ya a pantalla completa (p. ej. cabecera oculta), en táctil se fija desde el principio.
    if (coarse.matches && Math.abs(rect().top) <= 1 && window.scrollY <= 1) pin();
})();
