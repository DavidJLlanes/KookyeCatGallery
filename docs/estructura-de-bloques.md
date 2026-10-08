# Estructura de la portada por bloques

La portada se compone de piezas independientes. Cada una vive en su propio archivo y se configura desde el panel (**Diseño**).

## Mapa de archivos

| Qué | Dónde |
| --- | --- |
| Cabecera de escritorio y de móvil | `inc/blocks/header-desktop.php`, `inc/blocks/header-mobile.php` (fijas, siempre primero) |
| Bloques reordenables | `inc/blocks/categories.php`, `gallery.php`, `map.php`, `project.php`, `social.php` |
| Pintar bloques y orden | `inc/page-blocks.php` (`page_block_render()`, `page_blocks_render_home()`) |
| Definición de cada bloque (nombre, ayuda, interruptor) | `site_section_definitions()` en `inc/site-settings.php` |
| Galerías premium (sustituyen al bloque de galería) | `inc/premium-galleries.php`, `inc/premium/`, `assets/premium/` · ver [galerias-premium.md](galerias-premium.md) |
| Ajustes: valores por defecto, validación y guardado | `inc/site-settings.php` |
| Formularios del panel (Perfil y Diseño) | `inc/site-settings-form.php` |
| Armazón del panel (menú y barras) | `inc/admin-shell.php`, estilos en `assets/css/admin.css` |
| Acciones y páginas del panel | `admin.php` (índice al principio del archivo) |
| Portada (datos y estructura general) | `index.php`: calcula los datos, define `$pageContext` y llama a los bloques |

## Cómo funciona

1. `index.php` prepara los datos (fotos, categorías…) y los reúne en `$pageContext`.
2. `page_block_render('map', $pageContext)` incluye `inc/blocks/map.php` dentro de una función: el bloque solo ve las variables de `$pageContext`.
3. Cada bloque comprueba su propio interruptor (`show_map`, `show_project`…) y no pinta nada si está oculto.
4. `page_blocks_render_home()` pinta los bloques reordenables siguiendo `section_order`. Si falta alguno o hay claves repetidas o desconocidas, se normaliza (`site_section_order_normalize()`).
5. Las cabeceras se ocultan por CSS con `show_header_mobile` / `show_header_desktop` (atributos `data-show-header-*` en `<body>`).
6. El bloque «Enlaces sociales» también se muestra al final de la página de una foto (`/foto/slug`).

## Añadir un bloque nuevo

1. Crea `inc/blocks/<nombre>.php`. Empieza con un comentario que indique qué hace y qué variables recibe.
2. Si necesita datos nuevos, añádelos a `$pageContext` en `index.php`.
3. Regístralo en `site_section_definitions()` (`label`, `hint` y `toggle`; usa `null` si no se puede ocultar). La clave debe coincidir con el nombre del archivo.
4. Si tiene interruptor, añade su valor por defecto (`show_<nombre>`) en `site_settings_defaults()`. La validación y el atributo `data-show-*` salen solos de `site_toggle_keys()`.
5. Añade el texto de ayuda si hace falta y comprueba `php tests/site-settings.php`.

El panel (lista de **Estructura de la página**) y el orden por defecto se generan a partir de `site_section_definitions()`, no hay que tocar el formulario.

## Pruebas

- `php tests/site-settings.php`: ajustes, orden y cabeceras.
- `node tests/admin-layout.cjs`: armazón del panel y reordenación en siete anchos.
- `node tests/site-design.cjs` y `node tests/gallery-layout.cjs`: diseño y galería de la web.
