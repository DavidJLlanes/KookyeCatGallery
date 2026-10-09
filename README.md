<p align="center">
  <img src="assets/img/kookyecatgallery-photographers-cover.png" alt="KookyeCatGallery — Tu fotografía. Tu web. Plantillas y galerías para fotógrafos." width="100%">
</p>

<h1 align="center">KookyeCatGallery</h1>

<p align="center">
  <strong>Tu fotografía. Tu web.</strong><br>
  Una galería autoalojada para fotógrafos que quieren crear su web,<br>
  subir imágenes fácilmente y elegir cómo presentar su trabajo.<br>
  Personaliza tu portfolio y gestiona tus fotografías desde el navegador.
</p>

<p align="center">
  <a href="LICENSE"><img alt="Licencia PolyForm Noncommercial 1.0.0" src="https://img.shields.io/badge/LICENCIA-PolyForm_Noncommercial-d4a574?style=for-the-badge"></a>
  <a href="manifest.json"><img alt="PWA instalable" src="https://img.shields.io/badge/PWA-INSTALABLE-6366f1?style=for-the-badge&logo=pwa&logoColor=white"></a>
  <a href="#plantillas-y-modos-de-presentación"><img alt="10 modos de galería" src="https://img.shields.io/badge/GALERÍA-10_MODOS-52796f?style=for-the-badge"></a>
</p>

<p align="center">
  <a href="#requisitos"><img alt="PHP 8.1 o posterior" src="https://img.shields.io/badge/PHP-8.1%2B-777bb4?logo=php&logoColor=white"></a>
  <a href="#tecnología"><img alt="JavaScript" src="https://img.shields.io/badge/JavaScript-f7df1e?logo=javascript&logoColor=black"></a>
  <a href="#tecnología"><img alt="Sin compilación" src="https://img.shields.io/badge/build-sin_compilación-52796f"></a>
</p>

<h3 align="center"><a href="#instalación">📷 Crear tu web con KookyeCatGallery</a></h3>

<p align="center">
  <a href="#plantillas-y-modos-de-presentación">Plantillas y presentación</a> ·
  <a href="#instalación">Instalación</a> ·
  <a href="docs/subida-fotografias.md">Subir fotografías</a> ·
  <a href="docs/filtros-fotograficos.md">Editor y filtros</a> ·
  <a href="CONTRIBUTING.md">Contribuir</a>
</p>

---

## Características

- **Tu portfolio en tu servidor:** presenta paisajes, retratos, arquitectura, fotografía de calle o cualquier otra especialidad con tu identidad y tus textos.
- **Subida desde el navegador:** incorpora imágenes desde el panel de administración y organiza categorías, títulos, metadatos, borradores, orden y favoritos.
- **Diseño configurable:** combina cabeceras, paletas y modos de galería, con ajustes independientes para móvil y escritorio.
- **Editor integrado:** aplica filtros y ajustes fotográficos desde el navegador antes de publicar.
- **Visor de fotografías:** navegación táctil, fotografía completa y modo de pantalla completa.
- **Personalización del sitio:** edita perfil, logo, enlaces sociales, textos, proyecto y páginas legales desde el panel.
- **PWA instalable:** diseño adaptable, soporte básico sin conexión y generación de imágenes WebP.
- **Mapa opcional:** desactivado inicialmente; actívalo si quieres mostrar las ubicaciones de tus fotografías.

Guías: [textos y diseño](docs/textos-y-diseno.md) · [filtros fotográficos](docs/filtros-fotograficos.md) · [navegación entre fotos](docs/navegacion-fotografias.md) · [subida de fotografías](docs/subida-fotografias.md).

## Plantillas y modos de presentación

Desde **Textos y diseño** puedes combinar las opciones del sitio sin editar código. La cabecera, el modo de galería y el número de columnas se eligen por separado para móvil y escritorio.

### Cabeceras para dar personalidad a tu web

| Móvil · 9 diseños | Escritorio · 9 diseños |
| --- | --- |
| Perfil social | Tipográfica |
| Retrato centrado | Retrato centrado |
| Compacta | Compacta |
| Editorial | Editorial |
| Cine · tira de película | Museo · galería blanca |
| Atlas · cuaderno cartográfico | Observatorio · constelación 3D |
| Estudio · retícula suiza | Periódico · portada de autor |
| Órbita · cielo 3D | Noir · estreno de cine |
| Álbum · recortes y postales | Plano · archivo técnico |

### Diez formas de mostrar tus fotografías

Todos los modos están disponibles tanto en móvil como en escritorio.

| Modo | Presentación |
| --- | --- |
| **Masonry** | Columnas con alturas naturales que conservan las proporciones de cada fotografía. |
| **Cuadrícula** | Miniaturas uniformes con proporción cuadrada, horizontal 4:3 o vertical 3:4. |
| **Mosaico cromático** | Filas ajustadas que mantienen las proporciones originales y comparten sus bordes. |
| **Editorial asimétrica** | Filas de distinta densidad para una composición editorial. |
| **Filas por categoría** | Fotografías agrupadas en tiras por categoría. |
| **Álbum desordenado** | Composición de imágenes cuadradas de distintos tamaños, con una ligera inclinación. |
| **Sala de exposición** | Fotografías grandes y centradas, con espacio entre ellas. |
| **Hoja de contactos** | Una vista compacta para recorrer una colección de imágenes. |
| **Secuencia narrativa** | Una imagen protagonista seguida de una secuencia de fotografías. |
| **Trípticos** | Composiciones de tres imágenes: una grande junto a dos más pequeñas. |

### Galerías premium

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

### Color, proporciones y efectos

- **7 paletas:** Elegante, Noche, Luz, Cyberpunk, Japón, Bosque y Océano.
- **5 opciones de proporción:** adaptativa, masonry, cuadrada, horizontal 4:3 y vertical 3:4. La cuadrícula usa las proporciones fijas; masonry conserva las originales. El visor abre la fotografía completa.
- **Columnas:** de 1 a 4 en móvil y de 1 a 10 en escritorio, respetando la composición de cada modo.
- **10 efectos:** Suave, Acercamiento, Elevación, Revelado, Virado de color, Marco luminoso, Desplazamiento, Perspectiva, Enfoque y Destello.

Las opciones documentadas corresponden al catálogo de [diseños del sitio](inc/site-settings.php) y al motor de [composición de galerías](assets/js/gallery-layout.js).

## Tecnología

PHP 8.1+, JavaScript, HTML y CSS. No necesita framework ni proceso de compilación. La galería se ejecuta en tu propio servidor; las fotografías y la configuración pertenecen a tu instalación y no forman parte de este repositorio.

## Requisitos

- PHP 8.1 o posterior con las extensiones GD y Fileinfo.
- Apache con `.htaccess` habilitado para aplicar las reglas que protegen archivos y directorios privados.
- Node.js y Playwright solo para ejecutar todas las comprobaciones de interfaz.

## Instalación

1. Descarga este repositorio en la raíz pública de tu servidor web.
2. Asegúrate de que PHP puede escribir en `img/` para guardar fotografías y versiones procesadas.
3. Configura `GALLERY_PUBLIC_URL` con la URL pública de la galería.
4. Define `GALLERY_PRIVATE_DIR` para guardar los datos privados fuera de la raíz pública (consulta la sección siguiente).
5. Abre `/admin.php`, configura el acceso de administración y personaliza los textos. No hay una contraseña predeterminada.
6. Sube tus fotografías y revisa sus títulos, categorías y metadatos antes de publicar.

Las páginas legales son plantillas: complétalas con información correcta para tu instalación antes de hacerlas públicas.

Para una prueba local:

```sh
php -S 127.0.0.1:8000
```

Abre `http://127.0.0.1:8000`. El servidor PHP integrado es solo para desarrollo; no aplica las reglas de `.htaccess`.

## Configuración privada

Guarda los archivos de configuración **fuera de la raíz pública del sitio web**. Crea un directorio que PHP pueda leer y escribir, y configura `GALLERY_PRIVATE_DIR` para que apunte a él. Por ejemplo, si la raíz pública es `/srv/www/galeria/public`, puedes guardar los datos privados en `/srv/www/galeria/privado`.

En ese directorio la aplicación crea `site-settings.json`, `upload-auth.php` y `hearts.json`. No subas estos archivos a GitHub ni los coloques en una carpeta que el servidor pueda servir públicamente.

Como alternativa, la aplicación puede usar `var/` dentro del proyecto. El `.htaccess` incluido bloquea el acceso web directo a esa carpeta; aun así, se recomienda configurar un directorio privado fuera de la raíz pública.

No guardes credenciales, claves API, datos de acceso ni fotos privadas en el repositorio. Las imágenes pueden contener coordenadas GPS u otros datos EXIF: revísalas antes de subirlas. El mapa está desactivado inicialmente.

## Recuperar o cambiar el acceso de administración

El panel admite **un solo usuario**. El repositorio incluye `reset-admin-access.php.example`, una herramienta que puedes subir temporalmente para crear el primer acceso o reemplazar el usuario y la contraseña si los pierdes. La herramienta modifica el mismo archivo privado que usa el panel y se borra automáticamente tras una operación correcta. Una clave temporal solo se puede usar una vez.

1. Desde tu ordenador, genera una clave aleatoria nueva de 64 caracteres hexadecimales: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.
2. Haz una copia local de `reset-admin-access.php.example`, llámala `reset-admin-access.php` y sustituye `REPLACE_WITH_A_NEW_RANDOM_64_CHARACTER_SECRET` por la clave que acabas de generar. No guardes ni subas esa copia modificada a GitHub.
3. Sube `reset-admin-access.php` a la raíz pública de la web usando el panel de archivos o SFTP. Accede por HTTPS a `https://tu-dominio/reset-admin-access.php`.
4. Introduce la clave temporal, el nombre de usuario nuevo y una contraseña única de al menos 12 caracteres. Al guardar, el servidor reemplaza la única cuenta de administración y elimina el archivo temporal.
5. Confirma desde el gestor de archivos que `reset-admin-access.php` ya no está en la raíz pública. Si el borrado automático no pudo completarse, elimínalo manualmente antes de salir.

La clave queda bloqueada para usos posteriores en la carpeta privada de configuración. Para otra recuperación, genera una clave nueva, prepara otra copia local de la plantilla y vuelve a subirla temporalmente. No reutilices una clave ni dejes la herramienta en el servidor: mientras exista, cualquiera puede abrir su formulario, aunque solo quien conozca la clave aleatoria podrá cambiar el acceso.

## Textos con IA

La generación opcional de títulos y descripciones está disponible con Gemini u OpenRouter. Viene desactivada y sin claves. Consulta [configuración de IA](docs/ia-textos.md) para activarla mediante variables de entorno fuera del repositorio.

## Desarrollo y comprobaciones

GitHub Actions valida PHP y JavaScript y comprueba filtros, diseño adaptable, cuadrículas, administración, navegación, favoritos y editor fotográfico.

Comprobaciones básicas locales:

```sh
node tests/photo-presets.cjs
php tests/site-settings.php
php tests/photo-navigation.php
```

Con Playwright y Chromium: `node tests/premium-deck.cjs` (galería premium Estilo Baraja), `node tests/admin-layout.cjs` (panel), `node tests/gallery-layout.cjs` (galerías estándar) y el resto de `tests/*.cjs`.

Para las comprobaciones de interfaz se necesitan Playwright y Chromium. Consulta el flujo de [validación automática](.github/workflows/validate.yml).

## Contribuir y seguridad

Lee la guía para [contribuir](CONTRIBUTING.md) y las instrucciones de [seguridad](SECURITY.md).

## Licencia

El proyecto se distribuye bajo la **[PolyForm Noncommercial License 1.0.0](https://polyformproject.org/licenses/noncommercial/1.0.0)**. El texto oficial completo está en [LICENSE](LICENSE). Esta licencia oficial para software establece los usos no comerciales permitidos; consulta sus términos completos antes de reutilizar o redistribuir el proyecto.
