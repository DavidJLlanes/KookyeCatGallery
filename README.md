# Fotos de León

Galería fotográfica y portfolio de autor, autoalojada en PHP y JavaScript (sin framework ni proceso de compilación). Las fotos se suben desde un panel privado y la web se personaliza desde el navegador.

## Qué incluye

- **Panel de administración** (`/admin.php`): subir y editar fotos con editor integrado, gestionar fotos y categorías, perfil, diseño y textos de las páginas.
- **Diseño configurable:** título de la web, 7 paletas, cabeceras de móvil y de escritorio (ocultables), 10 modos de galería estándar, columnas, fotos visibles a la vez, paginación circular o cuadrada y 10 efectos hover.
- **Bloques de la portada ordenables:** categorías, galería, mapa, «El proyecto» y enlaces sociales. Ver [docs/estructura-de-bloques.md](docs/estructura-de-bloques.md).
- **Galerías premium:** diseños completos que sustituyen a la galería estándar. Ver más abajo.
- PWA instalable, favoritos, corazones, presentación de fotos y mapa de ubicaciones.

## Galerías premium

Diseños completos que **sustituyen** a la galería estándar. Se eligen en **Textos y diseño → Diseño → Galería premium**; al activar una se ignoran (y se desactivan en el panel) los ajustes de la galería estándar que ella no use: el tipo de cuadrícula, las galerías de móvil y de escritorio, la forma de la paginación, las columnas y, salvo en «Estilo Burbujas» y «Estilo Cuadrados», las fotos visibles a la vez. Se integran con la paleta de colores y con las cabeceras.

| Galería premium | Presentación |
| --- | --- |
| **Estilo Baraja** | Pantalla completa (100 % de ancho y alto en cualquier dispositivo). Las fotos son cartas apiladas en 3D: al avanzar —rueda del ratón hacia abajo o dedo hacia arriba— la carta sale hacia la izquierda, se encoge y se desvanece, dejando ver el mazo. La página de fondo no se mueve y, en la primera y última foto, el gesto vuelve a desplazar la página. |
| **Estilo Burbujas** | Pantalla completa. Las fotos son círculos de distintos tamaños con un pequeño marco, repartidos al azar por toda la pantalla (sin solaparse ni salirse) y flotando lentamente, sin texto. Respeta «Fotos visibles a la vez» y se pagina con una paginación minimalista («1 / 7»). Al pulsar un círculo rebota y se abre su ficha. Comparte con la baraja la fijación en móvil, la parada del scroll, los botones de salir, los filtros y la vuelta a la misma foto. |
| **Estilo Cuadrados** | Igual que «Estilo Burbujas» (mismo marcado, estilos y comportamiento), pero con cuadrados de distintos tamaños en lugar de círculos. |
| **Estilo Tambor** | Pantalla completa. Carrusel cilíndrico 3D: las fotos giran como un tambor, con la activa de frente y las vecinas curvadas hacia atrás a ambos lados. Misma interfaz y comportamiento que «Estilo Baraja» (rueda, dedo, teclado, botones de salir, fijación en móvil, filtros y vuelta a la misma foto). |
| **Estilo Cilindro** | Variación del tambor: varias filas de fotos de distintos tamaños forman un cilindro giratorio que ocupa todo el ancho de la pantalla (en escritorio, estirado hacia los bordes). Misma interfaz y comportamiento que «Estilo Baraja». |
| **Estilo Polaroids** | Polaroids esparcidas sobre una mesa: la activa se centra y se amplía y las demás quedan alrededor, giradas y algo desenfocadas. Misma interfaz y comportamiento que «Estilo Baraja». |
| **Estilo Tinder** | Una carta que se desliza a un lado: hacia el **corazón** (derecha) da un corazón a la foto y pasa a la siguiente; hacia la **flecha** (izquierda) solo pasa. En los dos casos avanza, y la interfaz lo indica con un corazón y una flecha. |

Todos los detalles (comportamiento, ajustes de la animación, arquitectura y cómo crear una galería premium nueva) están en [docs/galerias-premium.md](docs/galerias-premium.md).

## Estructura del código

| Ruta | Contenido |
| --- | --- |
| `index.php` | Portada y páginas de foto: calcula los datos y pinta los bloques. |
| `inc/blocks/` | Un archivo por bloque de la portada (cabeceras, categorías, galería, mapa, proyecto, social). |
| `inc/premium/`, `assets/premium/` | Galerías premium (marcado, estilos y comportamiento). |
| `inc/site-settings.php`, `inc/site-settings-form.php` | Ajustes del sitio, validación y formularios del panel. |
| `inc/admin-shell.php`, `admin.php` | Armazón del panel y sus acciones. |
| `docs/` | Documentación: [textos y diseño](docs/textos-y-diseno.md), [bloques](docs/estructura-de-bloques.md), [galerías premium](docs/galerias-premium.md) y [subida de fotografías](docs/subida-fotografias.md). |

## Comprobaciones

```sh
php tests/site-settings.php
php tests/photo-navigation.php
node tests/photo-presets.cjs
```

Con Playwright y Chromium: `node tests/premium-deck.cjs`, `node tests/premium-bubbles.cjs`, `node tests/premium-squares.cjs`, `node tests/premium-drum.cjs`, `node tests/premium-cylinder.cjs`, `node tests/premium-polaroid.cjs`, `node tests/premium-swipe.cjs`, `node tests/admin-layout.cjs`, `node tests/gallery-layout.cjs` y el resto de `tests/*.cjs`. Ver el flujo de [validación automática](.github/workflows/validate.yml).
