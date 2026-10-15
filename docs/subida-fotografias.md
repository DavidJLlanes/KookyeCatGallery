# Habilitar la subida de fotografías en el VPS

El panel acepta imágenes de hasta 15 MiB y envía dos peticiones independientes (original y copia editada). Nginx y PHP deben permitir un poco más que ese máximo.

## Nginx

En el bloque HTTPS (`server { ... }`) del sitio `davidjimenezllanes.es` añade:

```nginx
client_max_body_size 20m;
```

No lo pongas dentro de un bloque `location`. Después comprueba y recarga:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## PHP-FPM

En el `php.ini` de la versión de PHP-FPM que usa el sitio, configura:

```ini
upload_max_filesize = 16M
post_max_size = 20M
```

Edita el archivo FPM, no solo el de PHP CLI. Puedes localizar el servicio instalado con:

```bash
systemctl list-units --type=service 'php*-fpm.service'
```

Después reinicia el servicio que corresponda, sustituyendo `8.x` por la versión instalada:

```bash
sudo systemctl restart php8.x-fpm
```

Si PHP sigue rechazando la subida, revisa también que `upload_tmp_dir` tenga espacio y permisos de escritura para PHP-FPM.

Con estos límites, al seleccionar una foto el panel debería mostrar la vista previa y, al completar la subida, volver a administración con “Foto subida correctamente”.

## Cámara en el móvil

Los campos de fotografías (subir foto, carga por lotes, imágenes del microblog y foto de perfil) muestran **Usar cámara** en dispositivos táctiles (Android e iPhone). Un campo auxiliar con `capture` abre la cámara y la foto pasa al campo normal como si se hubiera elegido allí; el campo de archivos nunca queda con `capture` puesto, así que «Elegir archivo» sigue abriendo la galería. Se activa con `data-camera` en el `<input type="file">` (`assets/js/camera-input.js`; `data-camera="user"` para la cámara frontal). Prueba: `node tests/camera-input.cjs`.

## Resolución con la que se publican

Las fotos de la galería y de la ficha se publican en **WebP al 80 %** con el lado mayor en **1800 px** como máximo (versión móvil: 800 px de ancho y 1200 de alto como máximo); las imágenes del microblog, igual: WebP al 80 % y 1800 px. Nunca se amplían. Se generan **al publicar** o editar la foto, no en la primera visita, y las descripciones con IA se piden después de responder, de modo que ni quien sube la foto ni quien visita la web esperan. Si cambia la receta, las fotos ya publicadas se rehacen poco a poco (4 por llamada). Prueba: `php tests/image-processor.php`.

**Rehacerlas desde el panel.** En **Ajustes → Publicación → Imágenes de la galería** el botón **Rehacer imágenes** rehace las versiones WebP pendientes (de 20 en 20; si quedan más, el panel lo dice y se pulsa otra vez). Es lo mismo que `php scripts/regenerate-images.php`, que solo se puede ejecutar por línea de comandos (por la web da 404 a propósito, igual que el resto de `scripts/`).

## Fotos muy grandes

La web publica siempre 1800 px aunque subas más, pero el original se guarda tal cual en `img/`. El servidor admite originales de hasta 15 MB: una foto mayor (por ejemplo, de 48 megapíxeles) se **reduce en el propio dispositivo** antes de enviarla, a 4096 px de lado mayor y en JPEG, tanto en «Subir foto» como en la carga por lotes (`assets/js/image-fit.js`). La copia reducida pierde los metadatos EXIF (fecha y GPS): en «Subir foto» el GPS se lee antes de reducirla, pero en la carga por lotes no se conserva.
