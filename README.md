# Kookye Cat Gallery

Galería autohospedada y adaptable para organizar y presentar fotografías. Está construida con PHP, JavaScript y CSS, sin framework ni proceso de compilación.

Este repositorio contiene únicamente el software. No incluye fotografías, datos personales, credenciales, configuraciones de producción ni el historial del sitio de origen. Añade tus propias imágenes y textos antes de publicar una instalación.

## Funciones

- Diseños de galería configurables: cuadrícula, mosaico tipo masonry y composiciones editoriales.
- Paletas, cabeceras y efectos al pasar el cursor (hover) seleccionables desde el panel.
- Gestión de fotografías, categorías, metadatos, favoritos y orden.
- Herramienta de edición fotográfica con filtros y navegación táctil.
- Editor de textos del sitio y páginas legales.
- Mapa opcional que utiliza los datos de ubicación de las fotos que subas.
- Aplicación web instalable y soporte básico sin conexión.
- Generación de miniaturas y versiones WebP para escritorio y móvil.

## Requisitos

- PHP 8.1 o posterior con las extensiones GD y Fileinfo.
- Servidor web Apache con `.htaccess` habilitado; el acceso directo a directorios privados queda bloqueado por esas reglas.
- Node.js solo es necesario para ejecutar las pruebas de JavaScript y navegador.

## Instalación

1. Descarga o clona este repositorio en la raíz pública de tu servidor web.
2. Configura PHP para permitir las subidas que necesites. El directorio `img/` debe ser escribible por PHP.
3. Crea el directorio privado de configuración fuera de la raíz pública y define `GALLERY_PRIVATE_DIR` para PHP. La aplicación también permite usar `var/` dentro del proyecto; sus reglas de servidor bloquean el acceso web.
4. Define `GALLERY_PUBLIC_URL` con la URL pública de la galería para generar enlaces y metadatos correctos.
5. Abre `/admin.php` y configura la autenticación de administración según las instrucciones del propio panel. No se proporciona una contraseña predeterminada.
6. Sube tus fotografías, revisa sus metadatos y personaliza los textos. Las páginas legales incluidas son plantillas: complétalas con información válida para tu instalación antes de hacerla pública.

Para una instalación de prueba local con PHP:

```sh
php -S 127.0.0.1:8000
```

Luego abre `http://127.0.0.1:8000`. La subida requiere que PHP pueda escribir en `img/`.

## Privacidad y configuración

No guardes contraseñas, claves API, datos de acceso, fotografías privadas ni archivos de configuración en el control de versiones. Usa variables de entorno o un directorio privado, y sirve el sitio mediante HTTPS. Los títulos y descripciones se editan localmente desde la administración; esta versión no transmite nombres de archivo a servicios externos.

Las imágenes pueden incluir EXIF, coordenadas GPS u otros metadatos. Comprueba y elimina los datos que no quieras publicar antes de subirlas. El mapa está desactivado inicialmente. La aplicación genera un sitemap a partir del contenido disponible en la instalación.

## Desarrollo y comprobaciones

La integración continua valida PHP, revisa la sintaxis de JavaScript y ejecuta las comprobaciones principales con GitHub Actions. Para ejecutar las comprobaciones sin navegador:

```sh
node tests/photo-presets.cjs
php tests/site-settings.php
php tests/photo-navigation.php
```

Las comprobaciones de interfaz requieren Playwright y Chromium, como se indica en `.github/workflows/validate.yml`.

## Licencia

El software se distribuye bajo la **Licencia Kookye Cat Gallery de Uso No Comercial**, incluida en `LICENSE`. Permite reutilizar, modificar y redistribuir el código sin fines económicos o comerciales. No concede derechos sobre imágenes, marcas ni datos que añadas a tu instalación.
