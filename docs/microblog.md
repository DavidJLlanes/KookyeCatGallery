# Microblog

Feed de notas cortas estilo Tumblr / Threads en la portada. Solo el propietario publica; cualquiera puede dar corazón.

## Qué hace
- **Posts mixtos:** texto, una cita (con fuente), hasta 4 enlaces y hasta 4 imágenes, combinables.
- **Tres niveles:** normal, *Destacado* (franja y velo de color) y *Súper destacado* (borde luminoso, brillo y letra mayor). Se elige al crear o editar.
- **Enlaces dentro del texto:** `[texto](https://…)` muestra «texto» como enlace; el botón **Enlace** (o Ctrl+K) lo inserta sin escribir la sintaxis, usando como texto lo que tengas seleccionado. Las URLs sueltas también se enlazan solas. Solo se admiten `http` y `https`; todo lo demás se escapa.
- **Textos editables:** el rótulo («Microblog») y el título («Notas, citas y hallazgos») se editan en Ajustes → Publicación o pulsándolos en la web con «Editar página».
- **Nuevo post desde la web:** con sesión iniciada, «Nuevo post» del botón «+» abre un panel flotante en la misma página (hoja inferior en móvil, tarjeta en escritorio) con todas las opciones: texto con enlaces, cita, enlaces, imágenes, destacado, fijar y programar. Guarda un borrador del texto en el navegador y actualiza el feed sin recargar. Sin sesión, el botón lleva al acceso del panel. La pestaña «Microblog» del panel se conserva.
- **Fijar arriba:** un post raíz puede fijarse (distintivo «Fijado»). Solo hay uno a la vez: fijar otro quita el anterior.
- **Programar:** al crear un post o una respuesta se puede elegir fecha y hora (hora del sitio —la del servidor o la de `MICROBLOG_TIMEZONE`—, hasta un año vista). Hasta entonces no se ve en la web (ni en el feed, ni en su enlace, ni en el sitemap) y en el panel aparece como «Programado». La fecha se puede cambiar o vaciar (publica ahora) mientras siga programado.
- **Sitemap:** `sitemap.xml` incluye el hilo de cada post público (prioridad 0,6) cuando el microblog está visible, y se regenera cuando cambia la lista.
- **Hilos:** el propietario responde desde el panel; las respuestas cuelgan del post como en Threads (siempre nivel normal).
- **Corazones:** reutilizan `hearts.php` con el identificador `mb-{id}`.
- **Incrustar:** cada post tiene enlace permanente (`/microblog.php?id=ID`), botón de compartir y botón `</>` que da un `<iframe>` (`&embed=1`) más `assets/js/microblog-embed.js`, que ajusta su alto.
- **Visibilidad y orden:** interruptor «Mostrar Microblog» en Ajustes → Publicación y orden en Diseño → Estructura de la página. Sin posts, el bloque no se pinta.
- **Diseño:** usa solo variables de paleta y tipografía, así que sigue los ajustes de Diseño. Ancho de columna de 560 px.
- **Botón «+» de la portada:** se despliega con «Subir foto» y «Nuevo post» (`assets/js/fab-menu.js`).

## Dónde vive cada cosa
| Qué | Dónde |
| --- | --- |
| Lógica, almacén y pintado | `inc/microblog.php` |
| Panel flotante de la web | `inc/microblog-composer.php`, `assets/css/microblog-composer.css`, `assets/js/microblog-composer.js` |
| Panel (pestaña Microblog) | `inc/admin-microblog.php`, `assets/css/microblog-admin.css`, `assets/js/microblog-admin.js` |
| Bloque de la portada | `inc/blocks/microblog.php` |
| Hilo, versión incrustable y «Cargar más» (JSON) | `microblog.php` |
| Estilos y comportamiento públicos | `assets/css/microblog.css`, `assets/js/microblog.js` |
| **Imágenes** (separadas de la galería) | `/microblog/` (ignorada por git y por el despliegue) |
| **Datos** (`posts.json`) | La carpeta privada de la instalación (`../config/microblog/`, o `var/microblog/` si no se puede escribir fuera de la raíz pública). Se puede forzar con `MICROBLOG_DATA_DIR` |

**Imágenes:** el servidor las convierte siempre a WebP con calidad 80 y reduce su lado mayor a 1500 px conservando la proporción (nunca amplía las pequeñas), lo que además elimina EXIF y GPS. El navegador las reduce a la misma medida antes de enviarlas para no pasar el límite de la petición (~20 MB). Las imágenes subidas antes de este cambio se quedan como estaban.

**Lightbox:** al pulsar una imagen se abre a pantalla completa de la ventana, con contador, texto alternativo, flechas, teclado (← → Esc), deslizar en móvil y un botón de pantalla completa del navegador (tecla F).

## Pruebas
`php tests/microblog.php` (las comprobaciones de imágenes necesitan la extensión GD).
