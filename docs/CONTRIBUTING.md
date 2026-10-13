# Guía de contribución

Cómo está organizado el código, qué reglas comprueban las pruebas y cómo añadir cosas. Algunas recetas son provisionales: cambian cuando termine la fase del plan que se indica (ver [refactor/plan.md](refactor/plan.md)).

## Estructura

| Carpeta | Contenido | Convención |
| --- | --- | --- |
| `*.php` en la raíz | Puntos de entrada (una URL cada uno). Solo pueden existir los de `tests/structure-rules.json`. | minúsculas con guiones |
| `core/` | Arranque, carga automática de clases, registros (`Registry`) y ajustes (`core/Settings/`: esquema, validación, almacén y migraciones). | clases `PascalCase.php`, espacio `Kcg\Core\` |
| `core/Installation.php` | Lo propio de cada instalación: rutas privadas, dirección pública, nombres de sesión y cookies, lugar, textos y ajustes iniciales. Se alimenta de variables de entorno y de `installation.php`. | `Kcg\Core\Installation` |
| `core/Release/` | Exportación del núcleo y actualización con copia previa (`Manifest`, `Updater`). | `Kcg\Core\Release\` |
| `installation.php` | Datos de **esta** web (no son del núcleo; nunca se tocan al actualizar). Plantilla: `installation.example.php`. | PHP que devuelve un array |
| `scripts/` | `update.php` (actualizar con copia y vuelta atrás), `export-core.php` (sacar el núcleo; con `--produccion`, el paquete para subir a un hosting: sin `tests/`, `docs/`, `.github/` ni herramientas de mantenimiento), `core-files.json` y `defaults/` (archivos genéricos que sustituyen a los de esta web al exportar). | |
| `core/Settings/migrations/` | Una migración de `settings.json` por archivo. | `NNNN-nombre.php` |
| `core/Admin/` | Enrutador del panel (`Router`) y sus módulos (`Modules/<Nombre>Module.php`): acceso, subida, biblioteca, categorías, microblog y ajustes. | clases `PascalCase.php`, espacio `Kcg\Core\Admin\` |
| `modules/` | Funcionalidades completas y desactivables (microblog, mapa…). Hoy: `Microblog/` (almacén de posts: interfaz `PostRepository` con SQLite y JSON). | `Kcg\Modules\<Nombre>\` |
| `themes/<tipo>/` | Opciones de personalización, una por archivo (paletas, fuentes, parejas, cabeceras, hover…). Cada archivo devuelve un array. | minúsculas con guiones |
| `themes/*.php` | Clases que usan los registros (`Palettes`, `Fonts`). | `Kcg\Themes\`, PascalCase |
| `inc/` | Código heredado: funciones sueltas por tema. Se carga con `require`. | minúsculas con guiones |
| `inc/blocks/`, `inc/premium/` | Parciales de HTML cargados con `include` dinámico según el ajuste elegido. | idem |
| `assets/css/components/` | Estilos de la web, un archivo por componente (los trozos del antiguo `style.css`). `manifest.json` fija el orden de cascada y qué perfil de página los carga (`full`: portada, foto, microblog y panel; `legal`: páginas de texto). | minúsculas con guiones |
| `assets/css/worlds/` | Estilos de las cabeceras especiales (una hoja por cabecera + común por dispositivo + lienzo 3D). | `<móvil\|escritorio>-<id>.css` |
| `assets/js/main.js` + `assets/js/app/` | JavaScript de la web como módulos ES nativos: `main.js` es la entrada y `app/` tiene un módulo por función (galería, lightbox, mapa, página de foto…). Se cargan con import map + `modulepreload` (`inc/site-assets.php`). | minúsculas con guiones |
| `assets/css/`, `assets/js/` (resto) | Estilos y scripts sueltos del navegador. | minúsculas con guiones |
| `tests/` | Pruebas, línea base y datos de prueba. | |
| `docs/` | Documentación y plan. | |

Reglas de código:

- **Código nuevo** → clases con espacio de nombres en `core/`, `modules/` o `themes/`. Una clase por archivo, y el archivo se llama como la clase. El autocargador (`core/Autoloader.php`) las encuentra solo; no hace falta `require`.
- **Código heredado** (`inc/`) → no se reescribe de golpe: se traslada fase a fase. Si lo tocas, no añadas más funciones sueltas a un archivo que ya haga otra cosa.
- **Datos de una web concreta** (rutas del servidor, dominio, nombres, lugar, textos propios) → nunca en el código: van en `installation.php` y se leen con `Kcg\Core\Installation`. La prueba de estructura exige 0 menciones de esta web fuera de `installation.php`. Para la carpeta de ajustes usa `site_config_dir()`.
- **Sin dependencias**: ni Composer ni paso de compilación.

## Comprobar un cambio («la puerta»)

```sh
node tests/run-all.cjs --fast        # segundos: sintaxis, estructura, catálogos y PHP (el día a día)
node tests/run-all.cjs               # ~5 min: lo anterior + navegador + matriz visual rápida
node tests/run-all.cjs --no-visual   # lo mismo sin capturas (es lo que ejecuta la integración continua de GitHub)
php tests/characterization.php       # comportamiento congelado
node tests/run-all.cjs --all         # ~30 min, solo antes de publicar: todo, con las 218 capturas
```

Detalles y variables de entorno en [refactor/linea-base.md](refactor/linea-base.md). Un cambio **intencionado** actualiza la referencia (`characterization.php --update`, `visual-matrix.cjs --update`) en el mismo commit y explica el porqué.

La prueba de estructura (`php tests/structure.php`) falla si:

- hay un `.php` en la raíz que no sea un punto de entrada declarado;
- un nombre de archivo se sale de la convención;
- un archivo de `inc/` no lo carga nadie, o un CSS/JS de `assets/` no lo referencia nadie (código muerto);
- una clase está en una ruta que no le corresponde a su espacio de nombres;
- aumentan las rutas del servidor escritas a mano. Si bajan, `php tests/structure.php --update-ratchet` baja el máximo.

## Cómo añadir… (registros de `themes/`)

Cada opción de diseño es **un archivo** `themes/<tipo>/<id>.php` que devuelve un array. El identificador es el nombre del archivo (minúsculas con guiones) y el orden en el panel lo da `order`. No hay nada que registrar ni compilar: el archivo aparece solo en el panel, se valida solo y entra en la matriz visual. Después de crearlo ejecuta `php tests/catalogs.php`: comprueba que no falta nada (colores válidos, contraste, archivos de fuentes, reglas CSS…), y `php scripts/build-catalogs.php`, que actualiza [catalogos.md](catalogos.md) (la lista de todas las opciones; `tests/catalog-docs.php` falla si no la actualizas). Probado de verdad: una paleta y una pareja de tipografías nuevas aparecen con solo su archivo; un efecto hover sin reglas CSS se rechaza.

**Paleta de colores** — `themes/palettes/<id>.php` (copia `ocean.php`)
- `name`, `mode` (`light` o `dark`, coherente con el fondo) y `vars` con los 9 colores: `bg`, `bg-soft`, `bg-card`, `fg`, `fg-soft`, `fg-dim`, `accent`, `accent-soft`, `line`.
- Opcional: `extra_vars` (variables CSS extra) y `css` (reglas propias; empieza cada una por `{p}`, que se sustituye por el selector de la paleta).
- El CSS se genera y se inyecta solo para la paleta elegida. Las paletas de modo claro reciben automáticamente las reglas comunes de `themes/Palettes.php`.

**Pareja de fuentes** — `themes/pairs/<id>.php`: `name`, `heading` y `body` (ids de familias), `group` (`editorial`, `modern` o `experimental`).
**Familia nueva** — `themes/fonts/<id>.php` con `name` y `weights` (`'400 700'`), más `assets/fonts/<id>-latin.woff2`, `<id>-latin-ext.woff2` y `<id>-OFL.txt`. El `@font-face` se genera: las páginas públicas solo cargan las familias de la pareja elegida.

**Cabecera** — `themes/headers-desktop/<id>.php` o `themes/headers-mobile/<id>.php` con `name` y, si es una cabecera especial, `css` (y `js`, opcional): las rutas de sus archivos. Su hoja va en `assets/css/worlds/` (`<dispositivo>-<id>.css` más la común `<dispositivo>-common.css`; el lienzo 3D, en `canvas.css`) con reglas `body[data-header-desktop="<id>"]` dentro de su `@media`. La página solo carga los archivos de la cabecera elegida (`inc/site-assets.php`); la prueba de catálogos falla si un archivo declarado no existe, si una hoja de `worlds/` no la usa nadie o si no hay reglas para el `id`.

**Efecto hover, proporción de fotos, forma de paginación** — `themes/hover/`, `themes/grids/`, `themes/pagination-shapes/` (`name`) y sus reglas `body[data-hover="<id>"]`, `data-grid`, `data-pagination-shape` en `assets/css/`.

**Galería estándar** — `themes/galleries/<id>.php`: `name`, `devices` (`mobile`, `desktop`) y `capabilities` (qué controles admite: `columns`, `photos`, `grid`, `pagination`); la geometría va en `assets/js/gallery-layout.js`. La lista de móvil y la de escritorio salen del mismo catálogo.

**Galería premium** — ver `inc/premium-galleries.php` (pasará a `themes/` más adelante).

**Ajuste nuevo** — una entrada en `core/Settings/Schema.php`: `type` (choice, bool, int-range, int-choice, title, path, https-url…), `default`, `section` (design, content, social, texts, pages) y, según el tipo, `choices`/`min`/`max`/`options` y `error`. Con eso ya tiene valor por defecto, validación, guardado y, si lleva `body`, atributo `data-…` en el `<body>`. Falta solo su campo en `inc/site-settings-form.php` (la etiqueta sale de `label`). `php tests/settings-schema.php` comprueba que está bien declarado.

**Cambiar el formato de un ajuste ya guardado** (renombrar una opción, retirar un valor…) — una migración nueva `core/Settings/migrations/NNNN-nombre.php` (el siguiente número, sin huecos) con `description`, `when` (`always` al validar y cargar, `load` solo al cargar el archivo) y `apply(array $data): array`. Tiene que ser **idempotente** y no fallar con datos mal formados; la prueba lo comprueba. El archivo guardado lleva `settings_version`.

**Componente de estilo nuevo** — `assets/css/components/<id>.css`, una entrada en `components/manifest.json` (en su sitio del orden de cascada, con los perfiles que lo cargan: `full` siempre; `legal` solo si lo dibujan las páginas legales) y su línea en el precaché de `service-worker.js` (si falta un archivo del precaché el service worker no se instala; `tests/catalogs.php` comprueba que la lista coincide).

**Módulo de JavaScript nuevo** — `assets/js/app/<nombre>.js` con `export` de lo que usen otros y `import { … } from './x.js'` de lo que necesite (una línea por import, rutas relativas), importado desde `assets/js/main.js`; añádelo al precaché de `service-worker.js`. Los nombres de primer nivel deben ser únicos entre módulos: las pruebas montan `main.js` y sus módulos en un solo script (`tests/support/main-bundle.cjs`).

**Presupuesto de rendimiento** — `tests/performance-budget.json` fija los bytes de CSS y JS por tipo de página. Si tu cambio los sube a propósito, sube la cifra ahí; `php tests/performance-budget.php --report` muestra los valores actuales. Una opción que no se usa no debe cargar sus archivos.

**Sección nueva del panel** — un archivo `core/Admin/Modules/<Nombre>Module.php` que hereda de `AbstractModule`. El `Router` lo descubre solo. Declara `id()`, `menu()` (entradas con `id`, `href`, `label`, `short`, `order`; el `id` es también el del icono en `adminIcon()`), `actions()` (nombre de la acción POST => método protegido; toda acción exige CSRF y sesión y debe terminar la petición con redirección, `uploadPage()` o `uploadJson()`), `wantsPage()` y `page()` (la pantalla GET) y `activeMenu()` (qué entrada resaltar). `admin.php` no se toca. `php tests/admin-modules.php` comprueba que no hay choques; el recorrido `tests/admin-characterization.cjs` **exige** un paso por cada acción nueva (añádelo a `walk()` y ejecútalo con `--update`).

**Módulo nuevo**: carpeta en `modules/<Nombre>/` con sus clases PHP (`Kcg\Modules\<Nombre>\`); su CSS y su JS se cargan solo si el módulo está activo. Ejemplo: `modules/Microblog/`.

**Otro almacén de datos para el microblog** — una clase que implemente `PostRepository` (`all`, `find`, `mutate`, `publicFeed`, `publicThread`, `threads`) y la elección en `Repositories::create()`. `tests/microblog-repository.php` comprueba que una implementación nueva da exactamente lo mismo que el almacén JSON de referencia.

## Commits y ramas

- Una rama por fase (`refactor/…`) y una etiqueta `fase-N-ok` al cerrarla.
- Sin commits, push ni despliegues sin que el propietario lo pida.
- No incluyas datos personales (correo, claves, rutas privadas) en commits ni en archivos versionados.
