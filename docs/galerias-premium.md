# Galerías premium

Una **galería premium** es un diseño completo (maquetación, animación e interacción) que **sustituye** al bloque «Galería» estándar. A diferencia de las galerías estándar, no se compone con columnas ni cuadrículas configurables: cada una es un diseño cerrado y cuidado como un todo.

Se elige en el panel: **Textos y diseño → Diseño → Galería premium**.

## Catálogo

| Nombre | Clave | Resumen |
| --- | --- | --- |
| **Estilo Baraja** | `deck` | Pantalla completa. Las fotos se reparten como una baraja de cartas: cada una sale hacia la izquierda, se encoge y se desvanece, dejando ver el mazo en 3D. |

## Qué cambia al activar una galería premium

- Se **ignoran** estos ajustes de la galería estándar, y el panel **desactiva** sus campos:
  - Tipo de cuadrícula
  - Galería móvil
  - Galería de escritorio
  - Forma de la paginación
  - Columnas en móvil y en escritorio
  - Fotos visibles a la vez en móvil y en escritorio
- Los valores que tenían se **conservan** guardados: al volver a «Ninguna · usar la galería estándar» reaparecen tal como estaban.
- Se mantienen los demás ajustes: título de la web, paleta, efecto hover, cabeceras (estilo y visibilidad), orden y visibilidad de los bloques, categorías, mapa, etc.
- El `<body>` marca `data-grid`, `data-gallery-mobile` y `data-gallery-desktop` como `premium` y añade `data-gallery-premium="<clave>"`. Los estilos de la galería estándar no se aplican.
- Los estilos y el JavaScript de la galería premium **solo se cargan si está activa** y solo en la portada (no en la página de una foto).

## Integración con la web

- **Paleta de colores:** las galerías premium usan solo las variables de la paleta (`--bg`, `--bg-card`, `--fg`, `--fg-soft`, `--accent`, `--accent-soft`, `--line`, `--serif`, `--sans`). Cambiar la paleta en el panel cambia la galería.
- **Cabeceras:** la cabecera activa (móvil o escritorio) va siempre encima del bloque. Se puede ocultar con los interruptores de **Cabecera**; la galería sigue funcionando igual.
- **Resto de bloques:** el bloque de galería se ordena como los demás en **Estructura de la página**. El bloque «Categorías» sigue funcionando (filtra la galería premium) y el botón «Favoritas» y la «Presentación» están dentro de la galería.

## Estilo Baraja (`deck`)

### Qué ve el visitante

- La galería ocupa **el 100 % del ancho y del alto** de la pantalla (`100dvh`: respeta las barras de los navegadores móviles). Funciona en escritorio, tablet, Android e iPhone, vertical y apaisado.
- Las fotos son **cartas apiladas con perspectiva 3D**. La carta de arriba ocupa toda la pantalla; las de debajo forman el mazo.
- Al pasar de foto, la carta sale **hacia la izquierda, se hace más pequeña y se desvanece** (≈ 0,5 s, arranque rápido y final suave), mientras la siguiente sube a primer plano.
- **Cómo se avanza**
  - Móvil y tablet: deslizar el dedo **hacia arriba** (o hacia la izquierda). Hacia abajo, retrocede.
  - Escritorio: **rueda del ratón hacia abajo**. Hacia arriba, retrocede. En trackpad también funcionan los gestos horizontales.
  - Teclado: flechas, RePág/AvPág, Espacio (Mayús+Espacio retrocede), Inicio y Fin.
  - Botones: flechas y contador «3 / 14» en la barra inferior.
- **El fondo de la web no se mueve** mientras se pasan cartas. En la **primera** y en la **última** foto la galería deja de capturar el gesto y la página continúa su scroll normal, así se puede salir de la galería hacia arriba o hacia abajo.
- Si la galería no está alineada con la pantalla, el primer gesto solo la encaja; los siguientes pasan cartas.
- Pulsar una foto abre su ficha (`/foto/<slug>`).
- **Ajuste a la pantalla:** si la proporción de la foto se parece a la de la pantalla, la cubre entera; si no (por ejemplo una foto vertical en un monitor), se muestra **completa sobre su propio desenfoque**, sin recortes.
- **Filtros:** los botones del bloque «Categorías» y el botón «Favoritas» filtran la baraja; si no hay fotos, avisa.
- **Movimiento reducido:** con `prefers-reduced-motion` el paso es instantáneo.
- **Accesibilidad:** región con `role="region"`, cartas como diapositivas, solo la activa es interactiva (`inert` en el resto), contador con `aria-live` y controles con etiqueta.

### Ajustes de la animación

Están en variables CSS al principio de `assets/premium/deck/deck.css` (clase `.deck`):

| Variable | Por defecto | Efecto |
| --- | --- | --- |
| `--deck-duration` | `520ms` | Duración del paso de una carta. |
| `--deck-ease` | `cubic-bezier(.2,.85,.25,1)` | Curva: arranque rápido, final suave. |
| `--deck-radius` | `22px` | Esquinas de las cartas del mazo. |

Las posiciones del mazo (`data-pos="1..3"`) están en la sección 2 del CSS: desplazamiento, profundidad, escala y giro de cada carta.

### Limitaciones conocidas

- Las fotos sin `slug` (sin ficha propia) se ven, pero no se pueden abrir con un clic.
- El nombre de la app instalada (`manifest.json`) no cambia con el título de la web.

## Arquitectura

```
inc/premium-galleries.php          Registro de galerías premium y utilidades (ajustes ignorados, etiquetas <link>/<script>)
inc/premium/<clave>.php            Marcado HTML de la galería
assets/premium/<clave>/<clave>.css Estilos y animación (solo se cargan si está activa)
assets/premium/<clave>/<clave>.js  Comportamiento (solo se carga si está activa)
inc/blocks/gallery.php             Si hay una galería premium activa, incluye su marcado en lugar de la galería estándar
inc/site-settings.php              Ajuste `gallery_premium` (validación, atributos del <body>)
inc/site-settings-form.php         Selector «Galería premium» y campos desactivados
assets/js/admin-ui.js              Desactiva los campos de la galería estándar al elegir una premium
```

Flujo: `index.php` → `page_block_render('gallery')` → `inc/blocks/gallery.php` → (si hay premium activa) `inc/premium/<clave>.php`. `premium_gallery_head_tags()` y `premium_gallery_script_tags()` cargan los recursos solo en la portada.

### Contrato del marcado de `deck`

| Elemento | Qué es |
| --- | --- |
| `#deck` | Raíz de la galería. `data-total` = número de fotos; el JS mantiene `data-index`. |
| `.deck__card` | Una carta por foto, con los `data-*` de la foto. El JS le pone `data-pos`: `0` activa, `1..3` mazo, `4` oculta, `-1/-2` ya pasadas. |
| `.deck__ui` | Controles superpuestos: herramientas, contador, flechas y pista. |
| `#favoritesToggle`, `#slideshowStart` | Mismos id que en la galería estándar: los gestiona `assets/js/main.js`. |

`assets/js/main.js` reconoce la galería premium: la raíz puede ser `#masonry` (estándar) o `#deck`, y las tarjetas `.card` o `.deck__card` (presentación, mapa y visor).

## Añadir una galería premium nueva

1. Crea el marcado en `inc/premium/<clave>.php`. Empieza con un comentario que explique el diseño y las variables que recibe (`$galleryItems`, `$author`, `$rootDir`).
2. Crea `assets/premium/<clave>/<clave>.css` y `<clave>.js`. Usa solo variables de la paleta para los colores y respeta `prefers-reduced-motion`.
3. Regístrala en `site_premium_gallery_definitions()` (`inc/premium-galleries.php`) con `label`, `description`, `partial`, `css` y `js`. Aparecerá sola en el selector del panel y en `data-gallery-premium`.
4. Si necesita textos, añade claves en `inc/site-texts.php` y usa `site_text_html()`.
5. Escribe un test con el marcado, el CSS y el JS reales (ver `tests/premium-deck.cjs`) y añádelo a `.github/workflows/validate.yml`.
6. Documenta el diseño en este archivo (catálogo y sección propia) y en el `README.md`.

Reglas del contrato: una galería premium debe ocupar su bloque entero, no depender de ningún ajuste de `site_premium_ignored_settings()`, integrarse con la paleta y las cabeceras, y no impedir que la página siga su scroll normal fuera de sus límites.

## Pruebas

- `php tests/site-settings.php`: validación del ajuste, campos opcionales con una galería premium y formulario.
- `node tests/premium-deck.cjs`: Estilo Baraja en 5 dispositivos (escritorio, portátil, tablet, móvil y móvil apaisado): pantalla completa, rueda, dedo, teclado, límites, ajuste de foto, filtros, paletas y movimiento reducido.
- `node tests/admin-layout.cjs`: el panel desactiva y reactiva los campos de la galería estándar.
