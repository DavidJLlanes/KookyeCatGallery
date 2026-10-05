# Habilitar la subida de fotografías en el servidor PHP

El panel acepta imágenes de hasta 15 MiB y envía dos peticiones independientes (original y copia editada). Nginx y PHP deben permitir un poco más que ese máximo.

## Nginx

En el bloque HTTPS (`server { ... }`) del sitio `tu-dominio.example` añade:

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
