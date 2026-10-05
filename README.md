<p align="center">
  <img src="assets/img/kookye-cat-gallery-og.png" alt="Kookye Cat Gallery, galería fotográfica de gatos" width="100%">
</p>

<h1 align="center">Kookye Cat Gallery</h1>

<p align="center">
  Galería autohospedada para organizar y presentar fotografías de gatos.
  Adaptable a móvil y escritorio, con administración y edición desde el navegador.
</p>

La portada también se usa como imagen Open Graph y Twitter Card al compartir la página principal; cada ficha de fotografía conserva su propia vista previa.

<p align="center">
  <a href="https://github.com/DavidJLlanes/KookyeCatGallery/actions/workflows/validate.yml"><img alt="Validación automática" src="https://github.com/DavidJLlanes/KookyeCatGallery/actions/workflows/validate.yml/badge.svg?branch=main"></a>
  <a href="LICENSE"><img alt="PolyForm Noncommercial License 1.0.0" src="https://img.shields.io/badge/licencia-PolyForm%20Noncommercial%201.0.0-D49B38?style=flat-square"></a>
  <a href="#requisitos"><img alt="PHP 8.1 o posterior" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white"></a>
  <a href="#caracter%C3%ADsticas"><img alt="Aplicación web instalable" src="https://img.shields.io/badge/PWA-instalable-6C4AB6?style=flat-square"></a>
</p>

<p align="center">
  <a href="#caracter%C3%ADsticas">Características</a> ·
  <a href="#tecnolog%C3%ADa">Tecnología</a> ·
  <a href="#instalaci%C3%B3n">Instalación</a> ·
  <a href="#configuraci%C3%B3n-privada">Configuración privada</a> ·
  <a href="CONTRIBUTING.md">Contribuir</a> ·
  <a href="https://github.com/DavidJLlanes/KookyeCatGallery/commits/main">Cambios</a> ·
  <a href="https://github.com/DavidJLlanes/KookyeCatGallery/issues">Incidencias</a>
</p>

---

## Características

- Diseños de galería configurables: cuadrícula, mosaico masonry y composiciones editoriales.
- Paletas, cabeceras móviles y de escritorio, columnas y efectos al pasar el cursor.
- Administración de fotografías, categorías, metadatos, borradores, orden y favoritos.
- Editor fotográfico integrado con filtros variados y navegación táctil.
- Edición de textos del sitio y páginas legales desde el panel.
- Mapa opcional, desactivado inicialmente para evitar publicar ubicaciones por accidente.
- Aplicación web instalable, soporte básico sin conexión y generación de imágenes WebP.

Guías: [textos y diseño](docs/textos-y-diseno.md) · [filtros fotográficos](docs/filtros-fotograficos.md) · [navegación entre fotos](docs/navegacion-fotografias.md) · [subida de fotografías](docs/subida-fotografias.md).

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

## Desarrollo y comprobaciones

GitHub Actions valida PHP y JavaScript y comprueba filtros, diseño adaptable, cuadrículas, administración, navegación, favoritos y editor fotográfico.

Comprobaciones básicas locales:

```sh
node tests/photo-presets.cjs
php tests/site-settings.php
php tests/photo-navigation.php
```

Para las comprobaciones de interfaz se necesitan Playwright y Chromium. Consulta el flujo de [validación automática](.github/workflows/validate.yml).

## Contribuir y seguridad

Lee la guía para [contribuir](CONTRIBUTING.md) y las instrucciones de [seguridad](SECURITY.md). Puedes abrir una [incidencia](https://github.com/DavidJLlanes/KookyeCatGallery/issues) para informar de errores o proponer mejoras.

## Licencia

El proyecto se distribuye bajo la **[PolyForm Noncommercial License 1.0.0](https://polyformproject.org/licenses/noncommercial/1.0.0)**. El texto oficial completo está en [LICENSE](LICENSE). Esta licencia oficial para software establece los usos no comerciales permitidos; consulta sus términos completos antes de reutilizar o redistribuir el proyecto.
