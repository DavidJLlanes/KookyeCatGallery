<p align="center">
  <img src="assets/img/kookyecatgallery-photographers-cover.png" alt="KookyeCatGallery — Tu fotografía. Tu web. Plantillas y galerías para fotógrafos." width="100%">
</p>

<h1 align="center">KookyeCatGallery</h1>

<p align="center">
  <strong>Tu fotografía. Tu web.</strong><br>
  Una galería fotográfica autoalojada, personalizable y gestionada desde el navegador.
</p>

<p align="center">
  <a href="LICENSE"><img alt="Licencia PolyForm Noncommercial 1.0.0" src="https://img.shields.io/badge/LICENCIA-PolyForm_Noncommercial-d4a574?style=for-the-badge"></a>
  <a href="manifest.json"><img alt="PWA instalable" src="https://img.shields.io/badge/PWA-INSTALABLE-6366f1?style=for-the-badge&logo=pwa&logoColor=white"></a>
  <a href="#opciones-de-galería"><img alt="18 diseños de galería" src="https://img.shields.io/badge/GALERÍA-18_DISEÑOS-52796f?style=for-the-badge"></a>
</p>

<p align="center">
  <a href="#instalación-y-uso">PHP 8.1+</a> ·
  <a href="#instalación-y-uso">Instalación</a> ·
  <a href="#actualizar-una-instalación">Actualizaciones</a> ·
  <a href="docs/subida-fotografias.md">Subir fotografías</a> ·
  <a href="docs/opciones-galeria.md">Opciones de galería</a>
</p>

---

KookyeCatGallery es una web de fotografía que se instala en tu propio alojamiento. No necesita base de datos, servicios externos ni un proceso de compilación. Después de instalarla, puedes gestionar las fotografías, el diseño y el contenido desde un panel privado.

## Funciones principales

- **Gestión de fotografías desde el navegador:** biblioteca visual con búsqueda y filtro por categoría, edición rápida de datos, editor fotográfico, favoritos, borradores y orden personalizado.
- **Carga por lotes:** selecciona varias fotografías y asígnales una categoría existente o crea una nueva para todo el lote.
- **Categorías:** crea, renombra, combina, mueve o elimina categorías. En la web se pueden mostrar como enlaces para filtrar las fotografías de cada colección.
- **Edición visual del sitio:** tras iniciar sesión, activa «Editar página» para cambiar textos en su contexto directamente en la web. Las redes sociales y las páginas legales se administran en sus apartados del panel.
- **Diseño adaptable:** cabeceras, paletas, organización de bloques y diseños independientes para móvil y escritorio.
- **Tipografías locales:** 30 parejas de fuentes para títulos y texto, con ejemplos reales en el panel. Los archivos y sus licencias se distribuyen con la web.
- **18 composiciones de galería:** diez diseños estándar y ocho premium. Los controles que no corresponden a un diseño se desactivan; la misma regla se aplica al guardar en el servidor.
- **Efectos hover accesibles:** diez efectos compartidos por los diseños, también disponibles al enfocar con teclado y respetando la preferencia de movimiento reducido.
- **Identidad visual:** sube el icono de la app (también usado como favicon) y la imagen para compartir en redes. El icono se adapta automáticamente a los tamaños necesarios.
- **Mapa opcional:** representa las fotografías con coordenadas y permite abrir sus fichas. Está desactivado inicialmente.
- **Aplicación instalable (PWA):** experiencia adaptable e instalación desde navegadores compatibles.
- **Asistencia de escritura con IA opcional:** integración preparada para Gemini y OpenRouter, desactivada de inicio y sin claves en el repositorio.

## Instalación y uso

### Requisitos

- Hosting con **PHP 8.1 o posterior**.
- Extensiones PHP **GD** con JPEG, PNG y WebP, y **Fileinfo**.
- HTTPS y permisos de escritura para PHP en las carpetas de datos y cargas.
- Apache con soporte para `.htaccess`, o Nginx configurado por el proveedor para las rutas de la aplicación y el bloqueo de carpetas privadas.

No funciona en alojamiento exclusivamente estático. No necesita MySQL, Composer, npm ni Node.js para servir la web. Node.js y Playwright solo se usan para desarrollo y comprobaciones automatizadas.

### Instalar en un hosting compartido

1. Añade el dominio o subdominio en el panel del hosting y activa HTTPS.
2. Descarga el repositorio y copia **todo su contenido** en la raíz pública del sitio, normalmente `public_html`. Incluye los archivos ocultos, en especial `.htaccess`.
3. Comprueba que el dominio abre por HTTPS y que el alojamiento tiene PHP 8.1+, GD con WebP y Fileinfo.
4. Cuando estés preparado para completar la instalación de inmediato, abre `https://tu-dominio/install.php`. Indica la URL HTTPS del sitio, un nombre de usuario y una contraseña única de al menos 12 caracteres.
5. Confirma en el gestor de archivos que `install.php` se ha eliminado. Si sigue ahí, bórralo manualmente antes de continuar.
6. Entra en `https://tu-dominio/admin.php`, inicia sesión y personaliza la web.

El instalador prepara una carpeta privada `config/` fuera de la raíz pública cuando los permisos del hosting lo permiten. La estructura habitual queda así:

```text
cuenta/
├── public_html/   ← archivos públicos de la web
└── config/        ← ajustes y credenciales privadas
```

Si el alojamiento no permite escribir fuera de `public_html`, consulta la [guía detallada de instalación](docs/instalacion.md). Explica la ruta alternativa `var/`, la variable `GALLERY_PRIVATE_DIR`, los permisos y las diferencias entre Apache y Nginx. Que PHP funcione en Windows o Linux no garantiza que cualquier plan permita los permisos necesarios; el proveedor debe admitir los requisitos anteriores.

> **Seguridad del instalador:** `install.php` es temporal. Mientras exista y todavía no haya acceso configurado, cualquier visitante puede llegar al formulario para crear el usuario administrador. Úsalo una sola vez y comprueba que se ha borrado. Si el alojamiento no puede borrarlo automáticamente, elimínalo manualmente.

Las rutas de las fichas, como `/foto/slug`, requieren las reglas de reescritura incluidas en `.htaccess`. Si ves un 404 de LiteSpeed, verifica que el archivo se copió (algunos programas FTP ocultan los archivos que empiezan por punto) y que el proveedor permite sus reglas. En Nginx, `.htaccess` no tiene efecto: el proveedor debe configurar las rutas y denegar el acceso web a `var/`, `data/` e `img/`.

### Actualizar una instalación

No hay base de datos ni migraciones. Para actualizar, descarga la versión nueva y **copia los archivos del repositorio sobre los existentes**, aceptando sobrescribir los archivos de la aplicación. No borres primero toda la web ni reemplaces las carpetas de datos por las del repositorio.

Antes de actualizar, guarda una copia de seguridad de las fotografías y de la carpeta privada `config/` (o `var/` si tu instalación usa esa alternativa). Al copiar:

1. Sobrescribe los archivos de código y recursos con la versión nueva; incluye los archivos ocultos actualizados, como `.htaccess`.
2. Conserva `config/`, `var/`, las fotografías y los datos generados por tu instalación. No publiques esos datos en GitHub.
3. **No vuelvas a dejar `install.php` accesible tras la actualización.** Si la copia de la nueva versión lo ha vuelto a colocar en la raíz pública, elimínalo inmediatamente. No copies un archivo activo `reset-admin-access.php`; conserva solo el archivo de ejemplo con extensión `.example`.
4. Abre la web y el panel. Si el navegador conserva recursos antiguos, recarga la página o limpia la caché.

Así se actualiza la aplicación sin sobrescribir el usuario, la contraseña, los textos guardados ni la configuración privada. Los cambios hechos directamente en archivos de código se reemplazarán: guarda esas personalizaciones aparte y reaplícalas después de actualizar.

### Copias de seguridad y cambio de dominio

Guarda una copia de `config/` (o `var/`, según tu instalación), de las fotografías y de cualquier archivo propio que hayas añadido. No hay base de datos que exportar. Antes de restaurar, conserva la estructura y los permisos de escritura para PHP. Si cambias de dominio, actualiza la URL pública guardada (`public-url.txt`) o establece `GALLERY_PUBLIC_URL` en la configuración del hosting. Consulta [instalación](docs/instalacion.md) para los detalles.

## Administración y personalización

El panel está en `/admin.php`. Sus áreas separan **Contenido**, **Diseño**, **Textos**, **Redes sociales** y **Publicación**, para que cada tipo de ajuste tenga un lugar claro.

- **Contenido:** sube una foto, edita una existente, utiliza la biblioteca visual o carga un lote.
- **Categorías:** administra colecciones por separado y asigna las fotos desde el editor o la carga por lotes.
- **Diseño:** elige cabeceras, galería móvil y de escritorio, paleta, efectos, estructura de la página e identidad gráfica.
- **Textos:** cambia el nombre del sitio, la identidad del autor, textos de presentación, crédito y páginas de texto.
- **Redes sociales:** elige los perfiles, enlaces y nombres visibles; configura también los textos de esa sección.
- **Publicación:** ajusta opciones para mostrar y compartir la web.

Al iniciar sesión, el botón redondo **Editar página** activa la edición contextual de los textos disponibles. Pulsa un texto editable, realiza el cambio y guárdalo. Esta edición no reemplaza las herramientas del panel para fotografías, redes sociales o páginas legales.

El icono de la app debe ser cuadrado; se recomienda PNG, JPG o WebP de **512 × 512 px como mínimo**. Se generan las variantes para favicon, Apple Touch y PWA. Para compartir en redes se recomienda una imagen horizontal de **1200 × 630 px**. Los límites de archivo y los formatos aceptados aparecen junto a los controles de carga del panel.

### Opciones de galería

Móvil y escritorio tienen selectores independientes. Hay diez diseños estándar:

| Diseño | Composición |
| --- | --- |
| **Masonry** | Columnas que conservan las proporciones originales. |
| **Cuadrícula** | Miniaturas uniformes; admite proporciones cuadrada, 4:3 o 3:4. |
| **Mosaico cromático** | Filas de imágenes con sus proporciones originales y bordes contiguos. |
| **Editorial asimétrica** | Filas con distinta densidad de columnas. |
| **Filas por categoría** | Colecciones en tiras horizontales; muestra todas las fotos de cada fila. |
| **Álbum desordenado** | Composición de tarjetas cuadradas con tamaños y giros variados. |
| **Sala de exposición** | Fotografías grandes, centradas y espaciadas. |
| **Hoja de contactos** | Presentación compacta; permite ajustar la proporción. |
| **Secuencia narrativa** | Fotografía protagonista seguida de una secuencia. |
| **Trípticos** | Grupos de tres con composición propia. |

Los diez efectos hover son Suave, Acercamiento, Elevación, Revelado, Virado de color, Marco luminoso, Desplazamiento, Perspectiva, Enfoque y Destello.

Las ocho galerías premium sustituyen la estándar y su interfaz se ajusta al tipo de navegación:

| Galería | Presentación |
| --- | --- |
| **Baraja** | Cartas fotográficas a pantalla completa con navegación gestual. |
| **Cover Flow** | Foto central frontal y fotos vecinas giradas en perspectiva, con reflejo. |
| **Burbujas** | Fotos circulares flotantes, distribuidas por la pantalla. |
| **Cuadrados** | Composición flotante como Burbujas con tarjetas cuadradas. |
| **Tambor** | Carrusel cilíndrico 3D con las imágenes curvadas hacia los lados. |
| **Cilindro** | Varias filas de fotos dispuestas alrededor de un cilindro. |
| **Polaroids** | La foto activa destaca entre tarjetas inclinadas sobre un fondo oscuro. |
| **Tinder** | Una tarjeta cada vez, con gestos para pasar y marcar favoritos. |

Las opciones incompatibles se ocultan o desactivan con una explicación y el servidor también impide que se guarden para diseños que no las usan. Baraja, Cover Flow, Tambor, Cilindro, Polaroids y Tinder controlan su propia composición y navegación. Burbujas y Cuadrados sí utilizan «Fotos visibles a la vez» como límite de página. En los diseños compatibles, las columnas, la cantidad de fotos y la paginación se aplican según corresponda. La selección «Todas» desactiva la paginación cuando ya no se utiliza.

Consulta la [matriz completa de compatibilidad](docs/opciones-galeria.md) y la guía de [galerías premium](docs/galerias-premium.md) antes de elegir combinaciones.

## Acceso y seguridad

La instalación admite un único usuario administrador. El acceso se almacena como hash de contraseña dentro de la carpeta privada.

Si pierdes la contraseña, el repositorio incluye `reset-admin-access.php.example`. Para restablecer el acceso, copia ese ejemplo como `reset-admin-access.php`, súbelo cuando vayas a utilizarlo y abre su URL por HTTPS. El archivo muestra la advertencia antes del formulario e intenta borrarse al guardar. **Comprueba que desapareció; si continúa en el servidor, elimínalo inmediatamente desde el alojamiento.** No dejes el archivo activo online para usarlo más tarde: cualquiera que acceda a su dirección podría cambiar el único usuario y contraseña.

No guardes en este repositorio contraseñas, claves de API, fotografías privadas ni archivos de configuración de una instalación. Revisa los metadatos EXIF de las fotos antes de publicarlas; pueden incluir coordenadas GPS.

## IA opcional

La generación de títulos y descripciones con Gemini u OpenRouter viene desactivada. El repositorio público no contiene claves API. Para activarla, configura las variables necesarias en el entorno del servidor y consulta [la guía de IA](docs/ia-textos.md). No introduzcas claves en archivos que vayas a publicar en GitHub.

## Guías

- [Instalación y configuración del hosting](docs/instalacion.md)
- [Opciones compatibles de las galerías](docs/opciones-galeria.md)
- [Tipografías y licencias](docs/tipografias.md)
- [Diseños de galería premium](docs/galerias-premium.md)
- [Textos y diseño](docs/textos-y-diseno.md)
- [Subir fotografías y límites del servidor](docs/subida-fotografias.md)
- [Filtros fotográficos](docs/filtros-fotograficos.md)
- [Navegación entre fotografías](docs/navegacion-fotografias.md)
- [Estructura de bloques de la portada](docs/estructura-de-bloques.md)
- [Configuración de IA](docs/ia-textos.md)
- [Contribuir al proyecto](CONTRIBUTING.md)
- [Política de seguridad](SECURITY.md)

## Desarrollo

La aplicación está construida con PHP, JavaScript, HTML y CSS. Para servir el sitio no se necesita compilar nada. Para desarrollo local, con PHP instalado, puedes iniciar el servidor integrado:

```sh
php -S 127.0.0.1:8000
```

Abre `http://127.0.0.1:8000`. Este servidor es solo para desarrollo: no aplica las reglas de `.htaccess` ni reproduce las protecciones de un hosting configurado para producción.

Las comprobaciones automatizadas y sus dependencias se describen en el flujo de [GitHub Actions](.github/workflows/validate.yml). No es necesario instalar Playwright para usar o publicar la web.

## Licencia

Este proyecto se distribuye bajo **[PolyForm Noncommercial License 1.0.0](https://polyformproject.org/licenses/noncommercial/1.0.0)**. Consulta el texto completo en [LICENSE](LICENSE) para conocer los usos permitidos y sus condiciones.
