# Textos y diseño

Entra en `/admin.php`, inicia sesión y usa las secciones **Diseño** y **Textos**. Guarda para aplicar los cambios a la web pública.

- Paletas: Elegante, Noche (negro y blanco), Luz (blanco y tinta), Cyberpunk (neón) y Japón (papel y carmesí).
- Galería: adaptativa, masonry, cuadrada, horizontal 4:3 o vertical 3:4. Todas usan las columnas seleccionadas: 1–4 en móvil y 1–10 en escritorio.
- Fotos visibles a la vez: ajuste independiente para móvil y escritorio (6 a 100, o «Todas»). No depende de las columnas; si hay más fotos, se paginan. Por defecto: 12 en móvil y 20 en escritorio.
- Identidad: en **Textos → Nombre y marca** puedes cambiar el nombre de la web, quién hace las fotos, la presentación, el enlace personal y el crédito del pie. El año del copyright se actualiza automáticamente. El nombre de la app instalada (`manifest.json`) no cambia.
- Galería premium: en **Diseño → Galería premium** se elige un diseño completo. Los mensajes visibles propios de cada galería aparecen en **Textos → Textos de las plantillas**. Cada diseño conserva los ajustes estándar que utiliza y desactiva los demás. Ver [galerías premium](galerias-premium.md).
- Estructura de la página: en **Diseño → Estructura de la página** se ordenan con flechas los bloques de la portada (categorías, galería, mapa, «El proyecto» y enlaces sociales) y se activan o desactivan los opcionales. La galería siempre se muestra. El orden se guarda en `section_order`.
- Cabecera: la cabecera de móvil y la de escritorio se pueden ocultar por separado (`show_header_mobile`, `show_header_desktop`).
- Panel: el área privada usa una barra lateral en escritorio y una barra inferior en móvil; Perfil, Diseño y Textos son secciones independientes.
- Forma de la paginación: los números y flechas de página pueden ser círculos (por defecto) o cuadrados (`pagination_shape`). Los botones siempre tienen la misma anchura y altura, así que nunca se ovalan.
- Cabeceras: nueve para móvil y nueve para escritorio, con selectores independientes de la cuadrícula y de la paleta. Móvil corresponde a hasta 768 px y escritorio a partir de 769 px.
- Textos: en **Textos → Nombre y marca** edita el nombre de la web, el nombre del fotógrafo, sus descripciones y el crédito del pie. **Textos de las plantillas** cambia según la cabecera y la galería elegidas. Los controles técnicos y etiquetas de accesibilidad se generan automáticamente; títulos, descripciones y categorías de fotos se editan en **Gestionar fotos**.

La configuración se guarda en `/davidjimenezllanes.es/config/site-settings.json`, fuera del directorio público y de los archivos desplegados. El usuario que ejecuta PHP necesita permisos de escritura en esa carpeta. El archivo se crea al guardar por primera vez. Un error de guardado se muestra en el panel y conserva el archivo anterior. No se necesitan migraciones de base de datos.

Los valores por defecto usan la paleta Elegante y las cabeceras Perfil social en móvil y Tipográfica en escritorio; las columnas iniciales son tres en ambas pantallas. La opción adaptativa usa imágenes cuadradas en móvil y masonry en escritorio. Las cuadrículas de proporción fija recortan la miniatura; el visor abre la fotografía completa.

Para añadir textos en el futuro, incorpora una clave estable a `inc/site-texts.php` y utiliza `site_text_html()` en HTML o `site_text()` en contextos que ya escapen el valor. Los controles JavaScript usan `siteText()` y `siteTextHTML()`. No cambies claves existentes al cambiar sus valores por defecto.

Validación: `php tests/site-settings.php` y comprobaciones de sintaxis PHP/JavaScript del workflow de GitHub.

## El nombre de la web

El nombre de la web es **uno solo** y se ve igual en tres sitios: el título grande de la portada (la primera palabra arriba y el resto debajo, o una sola línea si es una palabra), el pie de página, junto al año, y el panel de administración (junto a «Administración»). Se cambia desde **Ajustes → Textos → Nombre de la web** o pulsándolo en la propia web con el lápiz de «Editar página» (en el título o en el pie): en cualquier caso cambia en todos los sitios. El nombre de quien hace las fotos (la línea en mayúsculas bajo el título) es un texto aparte.

Las webs que tenían el título en dos líneas o un «Título de la web» propios conservan ese nombre: se migra solo la primera vez que se abre el panel.

## Imágenes de la web

En **Ajustes → Contenido** se suben cuatro imágenes (foto de perfil, logo, icono de la aplicación y favicon, y la imagen para compartir en redes). Junto a cada campo aparece la imagen que hay ahora. Si el hosting limita el tamaño de la subida (`post_max_size`), el panel avisa y no guarda nada: sube las imágenes de una en una o pide subir el límite.

El logo y la foto de perfil tienen su proporción de recorte (logo 1:1, perfil 4:5): si la imagen elegida no la tiene se abre un recortador (`assets/js/image-cropper.js`). El logo se muestra siempre redondo (cabecera móvil, hero de escritorio, precarga) y en la cabecera móvil va **al lado** de la foto de perfil, nunca encima, en todas las plantillas (`tests/logo-layout.cjs`). Si se sube un logo propio, también aparece en la marca del panel.

Dónde se guardan y con qué tamaño: **todo lo que subes desde el panel vive en `uploads/`** (nunca en la carpeta del código, así que actualizar la web no lo toca): `profile-photo-custom.webp` (lado mayor ≤ 1600 px), `site-logo-custom.webp` (≤ 1024 px, con transparencia), `icons/site-app-icon-N.png` (32, 180, 192 y 512 px) y `site-social-image.jpg` (1200 × 630). Cada imagen se reduce, se corrige su orientación, se pasa a WebP (el icono a PNG, la imagen social a JPEG) y pierde sus metadatos. El navegador copia además el archivo a memoria al elegirlo (así el envío no falla con `ERR_FILE_NOT_FOUND` si el archivo está en la nube o se ha movido) y reduce las imágenes con más de 2400 px. Las instalaciones antiguas, con estos archivos en la raíz o en `assets/`, se siguen leyendo. Si PHP no puede escribir en `uploads/`, el panel lo dice y señala `sudo sh scripts/preparar-servidor.sh`. Pruebas: `php tests/site-brand-assets.php`, `node tests/image-cropper.cjs`.

## Redes sociales

Cada red es un archivo de `themes/socials/` (Instagram, Threads, Facebook, X, YouTube, TikTok, Flickr, LinkedIn, Pinterest, 500px, Bluesky y Mastodon) con su **glifo oficial en una sola tinta** (Simple Icons, licencia CC0). El icono hereda el color del texto (`currentColor`), así que se adapta solo a cualquier paleta, actual o futura, y al pasar el ratón. Añadir una red nueva es crear `themes/socials/<id>.php` con `name` y `svg`; aparece sola en el panel y en la portada. Prueba: `php tests/catalogs.php`.

## Título y rótulo de «El proyecto»

En **Ajustes → Textos → Sección «El proyecto»** hay un campo para el rótulo pequeño y otro para el título de la sección (un solo texto cada uno; la migración 0005 une las dos líneas antiguas del título). El contenido de la sección se edita en «Páginas de texto».

## El editor de las páginas de texto

Las páginas de texto (aviso legal, privacidad, cookies y «El proyecto») se editan con [Quill](https://github.com/slab/quill) 2, servido en local desde `assets/vendor/quill/` (BSD-3, sin CDN). Los botones aplican el formato a la selección, lo quitan al pulsar de nuevo y marcan su estado activo, también en pantallas táctiles. Estilos de párrafo, negrita, cursiva, subrayado, tachado, cita, listas, alineación (como `text-align`) y enlaces (https, mailto y rutas internas `/…`). El resultado se vuelve a sanear en el servidor (`site_sanitize_page_html()`), que decide qué se guarda. Comportamiento en `assets/js/admin-settings.js`; prueba: `node tests/page-editor.cjs`.

## Insignia de verificación

**Ajustes → Diseño → Insignia de verificación**: interruptor para mostrarla u ocultarla junto al nombre (cabecera de perfil del móvil) y 13 insignias para elegir (círculo, sello, escudo, estrella, destello, diamante, corona, corazón, rayo, cámara, medalla, hoja y solo la marca). Cada una es un archivo de `themes/badges/<id>.php` con su SVG y toma los colores de la paleta (`assets/css/components/verified-badge.css`); añadir una nueva es crear un archivo.

## Fotos fijadas y destacadas

En el .txt de cada foto: `# Fijada: 1` y `# Destacada: 1` (casillas en la subida, la edición y la edición rápida de **Gestionar fotos**, que además filtra por «Fijadas»). Las **fijadas** van arriba del todo de la galería, en su orden relativo, con un marco muy fino (dos filetes) del color de acento de la paleta. Las **destacadas** no se mueven: llevan un distintivo de esquina (triángulo con estrella) con los colores de la paleta. Estilos en `assets/css/components/photo-highlights.css`; prueba: `php tests/photo-highlights.php`.

## El mapa

La sección del mapa, su marco y los puntos de localización usan la paleta elegida: relleno con el acento y borde con el fondo de la paleta; si la paleta cambia con la página abierta, los puntos se repintan.
