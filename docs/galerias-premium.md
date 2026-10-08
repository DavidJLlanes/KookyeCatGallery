# Galerías premium

Una **galería premium** es un diseño completo (maquetación, animación e interacción) que **sustituye** al bloque «Galería» estándar. A diferencia de las galerías estándar, no se compone con columnas ni cuadrículas configurables: cada una es un diseño cerrado y cuidado como un todo.

Se elige en el panel: **Textos y diseño → Diseño → Galería premium**.

## Catálogo

| Nombre | Clave | Resumen |
| --- | --- | --- |
| **Estilo Baraja** | `deck` | Pantalla completa sobre el fondo de la web. Las fotos son cartas apiladas: cada una sale hacia la izquierda, se encoge y se desvanece, y el mazo de debajo asoma en 3D. |
| **Estilo Burbujas** | `bubbles` | Pantalla completa. Las fotos son círculos de distintos tamaños, con un pequeño marco, repartidos al azar por toda la pantalla y flotando lentamente. Se paginan. |
| **Estilo Cuadrados** | `squares` | Igual que «Estilo Burbujas», pero con cuadrados de distintos tamaños (esquinas suavemente redondeadas). |
| **Estilo Tambor** | `drum` | Pantalla completa. Las fotos giran como un tambor 3D (carrusel cilíndrico): la activa de frente y las vecinas curvadas hacia atrás a ambos lados. Misma interfaz que «Estilo Baraja». |
| **Estilo Cilindro** | `cylinder` | Pantalla completa. Varias filas de fotos de distintos tamaños forman un cilindro giratorio que ocupa todo el ancho (en escritorio, estirado hacia los bordes). Misma interfaz que «Estilo Baraja». |

## Qué cambia al activar una galería premium

- Se **ignoran** estos ajustes de la galería estándar, y el panel **desactiva** sus campos (cada galería puede respetar alguno: «Estilo Burbujas» sigue usando «Fotos visibles a la vez», clave `honors` de su definición):
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
- Las fotos son **cartas apiladas con perspectiva 3D**, todas con el mismo marco, sobre el **fondo de la web** (el de la paleta, no uno con los colores de la foto). La carta de arriba está centrada; las tres de debajo asoman por la derecha y por abajo, cada una más al fondo y con un giro distinto, como un mazo de cartas.
- Al pasar de foto, la carta sale **hacia la izquierda, se hace más pequeña y se desvanece** (≈ 0,85 s, arranque ágil y final muy suave: pausado para que se aprecie el efecto de baraja), mientras la siguiente sube a primer plano.
- **Cómo se avanza**
  - Móvil y tablet: deslizar el dedo **hacia arriba** (o hacia la izquierda). Hacia abajo, retrocede.
  - Escritorio: **rueda del ratón hacia abajo**. Hacia arriba, retrocede. En trackpad también funcionan los gestos horizontales.
  - Teclado: flechas, RePág/AvPág, Espacio (Mayús+Espacio retrocede), Inicio y Fin.
  - Botones: flechas y contador «3 / 14» en la barra inferior.
- **Escritorio (ratón/trackpad): el scroll de la página se detiene al llegar a la galería.** Al bajar desde arriba (o subir desde abajo), la página frena justo cuando la baraja ocupa toda la pantalla, aunque el gesto traiga inercia, y esa inercia no pasa fotos. A partir de ahí la rueda mueve las fotos y el fondo de la web no se mueve. En la **primera** y en la **última** foto la galería deja pasar la rueda y la página continúa su scroll normal. Si la galería no está alineada, el primer gesto solo la encaja.
- **Móvil y tablet: la galería se queda fija.** Al llegar, el cuerpo de la página se fija (`position: fixed`): no hay scroll, así que nada se desplaza ni vibra, ni con la inercia del dedo ni con la barra de direcciones del navegador. Los gestos solo mueven fotos; deslizar más allá de la última foto sale hacia abajo y más allá de la primera, hacia arriba.
- **Botones «Salir»** (a ambos lados de la barra inferior): la flecha doble hacia abajo («Salir») baja la página hasta lo que sigue a la galería; la flecha doble hacia arriba sube hasta lo que hay antes (solo aparece si hay algo encima). Tras salir, la galería no vuelve a frenar ni a fijarse hasta que se vuelva a ella: basta con desplazarse hacia ella.
- **Botones flotantes de la web** («Añadir foto» y «Subir»): se apartan mientras la baraja llena la pantalla, porque se pisaban con la barra inferior, y vuelven al salir.
- **Pies de foto:** solo se ve el de la carta activa; el de la que sale se oculta enseguida y el de la que llega aparece cuando casi ha terminado de moverse, para que nunca se solapen. En pantallas bajas (móvil apaisado) el pie y los controles se reducen.
- Pulsar una foto abre su ficha (`/foto/<slug>`). En la ficha, **«Volver a la galería»** (y el botón «Atrás» del navegador) regresa a **esa misma foto**, en la baraja a pantalla completa. El enlace usa `/#baraja=<slug>`; la dirección se limpia al llegar.
- **Ajuste al marco:** si la proporción de la foto se parece a la del marco, lo cubre entero; si no (por ejemplo una foto vertical en un marco apaisado), se muestra **completa sobre el fondo de la carta**, sin recortes.
- **Filtros:** los botones del bloque «Categorías» y el botón «Favoritas» filtran la baraja; si no hay fotos, avisa.
- **Movimiento reducido:** con `prefers-reduced-motion` el paso es instantáneo.
- **Accesibilidad:** región con `role="region"`, cartas como diapositivas, solo la activa es interactiva (`inert` en el resto), contador con `aria-live` y controles con etiqueta.

### Ajustes de la animación

Están en variables CSS al principio de `assets/premium/deck/deck.css` (clase `.deck`):

| Variable | Por defecto | Efecto |
| --- | --- | --- |
| `--deck-duration` | `850ms` | Duración del paso de una carta. |
| `--deck-ease` | `cubic-bezier(.22,.8,.24,1)` | Curva: arranque ágil, final muy suave. |
| `--deck-radius` | `22px` | Esquinas de las cartas del mazo. |

El mínimo entre dos pasos con la rueda (`STEP_COOLDOWN`, 600 ms) está al principio de `deck.js`. Las posiciones del mazo (`data-pos="1..3"`) están en la sección 2 del CSS: desplazamiento (en múltiplos de `--side`, el margen lateral reservado al mazo), profundidad y giro de cada carta. El marco de las cartas se calcula con unidades de contenedor (`cqw`/`cqh`) a partir de `--pad` (reserva igual arriba y abajo, para el centrado vertical) y `--side` (reserva lateral del mazo) en `.deck__stage`. El conjunto carta + mazo queda centrado en horizontal y en vertical en cualquier pantalla (el test lo mide en 14 tamaños, de 320×568 a 2560×1440).

### Limitaciones conocidas

- Las fotos sin `slug` (sin ficha propia) se ven, pero no se pueden abrir con un clic.
- El nombre de la app instalada (`manifest.json`) no cambia con el título de la web.

## Estilo Burbujas (`bubbles`)

Reutiliza de la baraja todo el funcionamiento (fijación en móvil, parada del scroll en escritorio, botones de salir hacia abajo y hacia arriba, filtros, botones flotantes que se apartan, vuelta desde la ficha con `/#baraja=<slug>`, centrado) y cambia cómo se muestran y se mueven las fotos.

### Qué ve el visitante

- La galería ocupa **el 100 % del ancho y del alto** de la pantalla sobre el fondo de la web, con dos resplandores suaves del color de acento que se mueven despacio.
- Cada foto es un **círculo con un pequeño marco** (el color de acento, un aro del color del fondo y un hilo fino exterior). Los círculos tienen **tamaños distintos**, se reparten **al azar** por toda la zona útil, **sin solaparse ni salirse** de la pantalla y **sin ningún texto**.
- Los círculos **flotan lentamente** (cada uno con su propio ritmo). Su movimiento es menor que la separación mínima entre ellos, así que nunca se tocan.
- **«Fotos visibles a la vez»** (la única opción de la galería estándar que respeta) fija cuántos círculos hay en cada página: el ajuste de escritorio por encima de 768 px de ancho y el de móvil por debajo; «Todas» muestra todos a la vez, con un tope para que ningún círculo sea diminuto. Si hay más fotos, se **pagina**.
- **Paginación minimalista** sobre la interfaz de la baraja: flechas finas y «1 / 7», sin caja, entre dos hilos finos. A los lados, las flechas dobles de **salir hacia arriba** y **salir hacia abajo**.
- **Cambiar de página:** rueda del ratón hacia abajo o dedo hacia arriba (o izquierda) pasa a la página siguiente; el gesto contrario, a la anterior. También flechas, RePág/AvPág, Espacio, Inicio y Fin. Las burbujas de la página actual se encogen y desaparecen y las nuevas entran con un pequeño rebote.
- **Al pulsar un círculo** rebota (se encoge, se pasa de tamaño y se asienta) y se abre siempre su ficha (`/foto/<slug>`): la navegación la garantiza un temporizador, sin pasos intermedios. **«Volver a la galería»** regresa a la página de esa foto.
- **Móvil y tablet:** al llegar, la galería se fija (el cuerpo de la página pasa a `position: fixed`): nada se desplaza ni vibra. Deslizar más allá de la última o la primera página sale de la galería. **Escritorio:** el scroll de la página se detiene al llegar y la rueda cambia de página; en la primera y la última página deja pasar la rueda.
- **Filtros:** los botones del bloque «Categorías» y «Favoritas» filtran las fotos y recalculan las páginas. **Movimiento reducido:** sin flotación y con cambios instantáneos.

### Cómo se reparten los círculos (`bubbles.js`, sección 3)

1. Se calcula una lista de pesos aleatorios (con una semilla fija por página, así el reparto no «salta» al redimensionar).
2. Se busca, por búsqueda binaria, el mayor tamaño base con el que caben todos los círculos colocándolos al azar con una separación mínima (`GAP`, 24 px).
3. Cada círculo crece después hasta casi tocar a sus vecinos o el borde, para que no queden huecos grandes.

Las constantes `GAP`, `FLOAT`, `MIN_RADIUS`, `STEP_COOLDOWN` y `OUT_TIME` están al principio de `bubbles.js`. Las reservas arriba y abajo (iguales, para que el conjunto quede centrado) están en `--bubbles-pad` de `bubbles.css`.

### Contrato del marcado de `bubbles`

| Elemento | Qué es |
| --- | --- |
| `#bubbles` | Raíz. `data-total` = número de fotos; el JS mantiene `data-page`. |
| `.bubbles__field` | Zona donde el JS reparte los círculos. |
| `.bubbles__item` | Un círculo por foto, con los `data-*` de la foto. El JS le pone `left/top/width/height` y lo oculta si no está en la página actual. |
| `.bubbles__ui` | Controles superpuestos: herramientas, paginación, botones de salida y pista. |
| `[data-bubbles-prev]`, `[data-bubbles-next]`, `[data-bubbles-current]`, `[data-bubbles-total]` | Paginación («1 / 7»). |
| `[data-bubbles-exit]`, `[data-bubbles-exit-up]` | Salir hacia abajo / hacia arriba (este último oculto si no hay nada encima). |
| `#favoritesToggle`, `#slideshowStart` | Mismos id que en la galería estándar: los gestiona `assets/js/main.js` (que reconoce `#bubbles` y `.bubbles__item`). |

## Estilo Cuadrados (`squares`)

Es **«Estilo Burbujas» con cuadrados**: comparte el marcado (`inc/premium/bubbles.php`), los estilos (`bubbles.css`) y el comportamiento (`bubbles.js`), y se comporta exactamente igual (tamaños distintos, reparto al azar sin solaparse, flotación, «Fotos visibles a la vez», paginación minimalista, rebote al pulsar y apertura de la ficha, fijación en móvil, botones de salir, filtros y vuelta desde la ficha). Solo cambia la forma:

- El marcado lleva `data-shape="square"` (lo decide el partial según la galería premium activa) y el CSS (`.bubbles[data-shape="square"]`) redondea suavemente las esquinas en lugar de hacer un círculo.
- El reparto usa la distancia de **Chebyshev** (el mayor desplazamiento en un eje) en lugar de la euclídea para separar los cuadrados y hacerlos crecer hasta casi tocarse. Como un cuadrado ocupa más que un círculo del mismo radio, el tope de burbujas por página según el espacio es algo menor (p. ej. 18 en lugar de 20 en un móvil apaisado).
- Los textos propios (`squares_label`) están en el catálogo «Galería premium · Estilo Cuadrados»; el resto de textos son los de la galería de burbujas.

## Estilo Tambor (`drum`)

Carrusel cilíndrico: las fotos están en un cilindro 3D que gira en horizontal. Reutiliza **toda la interfaz y el comportamiento de «Estilo Baraja»** (el mismo marcado `inc/premium/deck.php`, `deck.css` y `deck.js`): herramientas «Favoritas» y «Presentación», barra con flechas y contador «3 / 14», botones «Salir» y «Salir hacia arriba», pista inicial, rueda, dedo y teclado, parada del scroll en escritorio, fijación en móvil, filtros, botones flotantes que se apartan y vuelta desde la ficha. Solo cambia la disposición de las cartas.

- **Disposición:** `data-layout="drum"` en el marcado (lo decide el partial según la galería activa) y `assets/premium/drum/drum.css`, que se carga **después** de `deck.css` y solo recoloca las cartas. Cada carta se aleja del centro del cilindro (`translateZ(-R)`), gira sobre su eje (`rotateY(±38°·n)`) y vuelve a acercarse (`translateZ(R)`): todas las posiciones usan la misma lista de funciones, así que el giro entre posiciones es continuo.
- **Posiciones (`data-pos`):** `0` de frente · `±1`, `±2` a los lados, cada vez más hacia atrás y más oscuras · `±3` ocultas detrás del cilindro. A diferencia de la baraja, las cartas pasadas **no salen**: siguen en el tambor, a la izquierda.
- **Centrado:** el conjunto es simétrico respecto al centro de la pantalla, así que la carta activa queda centrada en horizontal y en vertical en cualquier pantalla (reserva igual arriba y abajo: `--pad`). En pantallas anchas se ven enteras las tres cartas centrales; en estrechas (≤ 768 px) la activa es mayor y las vecinas asoman por los lados.
- **Ajustes:** `--drum-angle` (giro entre cartas, 38°) y `--drum-r` (radio) en `.deck__stage` de `drum.css`; el tamaño del marco, en `--frame-w`. Duración y curva del giro: las de la baraja (`--deck-duration`, `--deck-ease`).
- **Panel:** ignora los mismos ajustes de la galería estándar que la baraja (8 campos desactivados).

## Estilo Cilindro (`cylinder`)

Variación del tambor: en vez de una carta por posición, hay **varias filas de fotos de distintos tamaños** alrededor de un **cilindro** de eje vertical que gira. Lleva la **misma interfaz que la baraja** (herramientas «Favoritas» y «Presentación», barra con flechas y contador, botones «Salir» y «Salir hacia arriba», pista) y se comporta igual (rueda, dedo, teclado, parada del scroll en escritorio, fijación en móvil, filtros, vuelta a la misma foto).

- **Marcado y estilos:** `inc/premium/cylinder.php` (la raíz es `#deck.deck[data-layout="cylinder"]`, así sirve la interfaz de `deck.css`) y `assets/premium/cylinder/cylinder.css` (el escenario 3D y el aspecto de cada foto). Se cargan `deck.css` y `cylinder.css`.
- **Geometría calculada (`cylinder.js`, sección 3):** como el cilindro no es redondo, la posición de cada foto no está en el CSS: el JS la calcula y la pinta en cada fotograma de la animación.
  - **Filas:** de 1 a 4 bandas apiladas (móvil vertical: unas 3-4; escritorio: 2). Las filas impares van medio hueco desplazadas, como un ladrillo.
  - **Tamaños distintos:** cada foto tiene un ancho (78-100 % del hueco) y un alto (64-100 % de la fila) estables, derivados de su posición.
  - **Ocupar la pantalla:** el radio horizontal se calcula (búsqueda binaria, ya con la perspectiva) para que la silueta del cilindro llegue justo a los bordes de la pantalla. En móvil vertical el cilindro es más redondo (profundidad = 0,78 del radio) y en escritorio / apaisado es una **elipse estirada hacia los bordes** (profundidad = 0,5 del radio).
  - **3D:** cada foto de la mitad delantera se coloca con `translate3d` y `rotateY` según la normal de la elipse; los laterales se curvan hacia atrás y se oscurecen (`brightness`); el orden de apilado sale de la profundidad.
- **Giro:** cada paso gira el cilindro una foto (todas las filas a la vez) en 850 ms. El anillo se cierra sobre sí mismo (tras la última foto aparece la primera por detrás), pero los pasos se detienen en la primera y la última para poder salir de la galería. El contador es «paso / pasos» (pasos = fotos ÷ filas).
- **Ajustes:** `PERSPECTIVE` (debe coincidir con `perspective` de `.cyl__stage`), `STEP_TIME` y las proporciones del cilindro (`ratio`) están en `cylinder.js`; la reserva arriba y abajo (`--cyl-pad`), en `cylinder.css`.
- **Panel:** ignora los mismos 8 ajustes de la galería estándar que la baraja.

## Arquitectura

```
inc/premium-galleries.php          Registro de galerías premium y utilidades (ajustes ignorados, etiquetas <link>/<script>)
inc/premium/<clave>.php            Marcado HTML de la galería
assets/premium/<clave>/<clave>.css Estilos y animación (solo se cargan si está activa)
assets/premium/<clave>/<clave>.js  Comportamiento (solo se carga si está activa)
inc/blocks/gallery.php             Si hay una galería premium activa, incluye su marcado en lugar de la galería estándar
index.php                          «Volver a la galería» de la ficha apunta a /#baraja=<slug> si hay una galería premium activa
inc/site-settings.php              Ajuste `gallery_premium` (validación, atributos del <body>)
assets/premium/cylinder/           Estilo Cilindro (cylinder.css y cylinder.js; marcado en inc/premium/cylinder.php; interfaz de deck.css)
assets/premium/drum/               Estilo Tambor (drum.css; reutiliza deck.js y el marcado de la baraja)
assets/premium/bubbles/            Estilo Burbujas (bubbles.css y bubbles.js); marcado en inc/premium/bubbles.php
inc/site-settings-form.php         Selector «Galería premium» y campos desactivados
assets/js/admin-ui.js              Desactiva los campos de la galería estándar al elegir una premium
```

Flujo: `index.php` → `page_block_render('gallery')` → `inc/blocks/gallery.php` → (si hay premium activa) `inc/premium/<clave>.php`. `premium_gallery_head_tags()` y `premium_gallery_script_tags()` cargan los recursos solo en la portada.

### Contrato del marcado de `deck`

| Elemento | Qué es |
| --- | --- |
| `#deck` | Raíz de la galería. `data-total` = número de fotos; el JS mantiene `data-index`. |
| `.deck__card` | Una carta por foto, con los `data-*` de la foto. El JS le pone `data-pos`: `0` activa, `1..3` mazo, `4` oculta, `-1/-2` ya pasadas. |
| `.deck__ui` | Controles superpuestos: herramientas, contador, flechas, botones de salida y pista. |
| `[data-deck-exit]`, `[data-deck-exit-up]` | Botones «Salir» (abajo) y «Salir hacia arriba» (oculto si no hay nada encima). |
| `#favoritesToggle`, `#slideshowStart` | Mismos id que en la galería estándar: los gestiona `assets/js/main.js`. |

`assets/js/main.js` reconoce la galería premium: la raíz puede ser `#masonry` (estándar) o `#deck`, y las tarjetas `.card` o `.deck__card` (presentación, mapa y visor).

## Añadir una galería premium nueva

1. Crea el marcado en `inc/premium/<clave>.php`. Empieza con un comentario que explique el diseño y las variables que recibe (`$galleryItems`, `$author`, `$rootDir`).
2. Crea `assets/premium/<clave>/<clave>.css` y `<clave>.js`. Usa solo variables de la paleta para los colores y respeta `prefers-reduced-motion`.
3. Regístrala en `site_premium_gallery_definitions()` (`inc/premium-galleries.php`) con `label`, `description`, `partial`, `css` y `js` (y `honors` si respeta algún ajuste de la galería estándar). `css` puede ser una lista, para cargar las hojas de otra galería más las propias (así hace «Estilo Tambor»). Aparecerá sola en el selector del panel y en `data-gallery-premium`.
4. Si necesita textos, añade claves en `inc/site-texts.php` y usa `site_text_html()`.
5. Escribe un test con el marcado, el CSS y el JS reales (ver `tests/premium-deck.cjs`) y añádelo a `.github/workflows/validate.yml`.
6. Documenta el diseño en este archivo (catálogo y sección propia) y en el `README.md`.

Reglas del contrato: una galería premium debe ocupar su bloque entero, no depender de ningún ajuste de `site_premium_ignored_settings()`, integrarse con la paleta y las cabeceras, y no impedir que la página siga su scroll normal fuera de sus límites.

## Pruebas

- `php tests/site-settings.php`: validación del ajuste, campos opcionales con una galería premium y formulario.
- `node tests/premium-cylinder.cjs`: Estilo Cilindro en 5 dispositivos y 13 tamaños de pantalla: el cilindro llega a los bordes y queda centrado, varias filas con fotos de tamaños distintos sin solaparse, curvatura y oscurecimiento 3D, cierre del anillo, giro, interfaz de la baraja (rueda, dedo, teclado, flechas, salidas, filtros), fijación en móvil, vuelta desde la ficha, paleta y movimiento reducido.
- `node tests/premium-drum.cjs`: Estilo Tambor en 5 dispositivos y 13 tamaños de pantalla: disposición cilíndrica simétrica y centrada (cartas ±1 y ±2, profundidad, oscurecimiento, ±3 ocultas), rueda, dedo, teclado, flechas, contador, botones de salir, filtros, fijación en móvil, vuelta desde la ficha, paleta y movimiento reducido.
- `node tests/premium-squares.cjs`: la misma batería que las burbujas con la forma cuadrada (`BUBBLES_SHAPE=square`): separación entre cuadrados, tamaños distintos, paginación, rebote, fijación en móvil, etc.
- `node tests/premium-bubbles.cjs`: Estilo Burbujas en 5 dispositivos: pantalla completa, «Fotos visibles a la vez» (20 / 12), círculos de tamaños distintos sin solaparse, sin salirse y repartidos por toda la pantalla, sin texto, flotación, paginación minimalista, teclado, rueda y dedo, fijación sin vibración en móvil, apertura con rebote, botones de salir, filtros, vuelta desde la ficha, paleta y movimiento reducido.
- `node tests/premium-deck.cjs`: Estilo Baraja en 5 dispositivos (escritorio, portátil, tablet, móvil y móvil apaisado): pantalla completa, parada del scroll al llegar (escritorio) y galería fijada sin vibración (móvil), rueda, dedo (también gestos lentos), teclado, límites, botones «Salir» y «Salir hacia arriba», botones flotantes, pies de foto sin solaparse, marco uniforme, mazo visible y ajuste de foto, filtros, vuelta desde la ficha, paletas y movimiento reducido.
- `node tests/admin-layout.cjs`: el panel desactiva y reactiva los campos de la galería estándar.
