# Instalación de KookyeCatGallery

## Antes de empezar

Necesitas un hosting con PHP 8.1 o posterior, GD con soporte para JPEG, PNG y WebP, Fileinfo, HTTPS y permiso para que PHP escriba archivos. La aplicación no necesita servidor de base de datos ni compilación (el microblog usa SQLite, incluido en PHP). No funciona en hosting estático.

## Dos formas de instalar

| Tu servidor | Instalador | Qué haces |
| --- | --- | --- |
| Hosting compartido (cPanel, Plesk, IONOS, Hostinger…) | `install.php` (web) | Subes los archivos y abres `install.php` en el navegador |
| VPS o servidor dedicado (Linux, SSH, `sudo`) | `instalar.sh` | Subes los archivos y ejecutas `sudo sh instalar.sh tu-dominio.com` |

Ambos usan la misma lógica (`core/Installer.php`) y hacen lo mismo: comprobar PHP, las extensiones y las carpetas explicando cómo arreglar lo que falte, crear el administrador y dejar la web lista. La diferencia es de permisos: en un hosting compartido PHP corre con tu usuario y puede escribir en lo que subes; en un VPS corre como `www-data` (o similar) y hay que asignarle sus carpetas una vez, con `sudo`, y eso lo hace `instalar.sh`.

### Instalación en un VPS o servidor dedicado

Requisitos: Linux con SSH y `sudo`, PHP 8.1+ (CLI y FPM) con `php-gd` (con WebP), `php-sqlite3` y `php-mbstring`, y tu servidor web con HTTPS ya configurado para el dominio.

```sh
# 1. Sube el contenido del paquete a la carpeta de la web
rsync -rlt ./kcg-hosting/ usuario@servidor:/tmp/kcg/        # o scp / SFTP
ssh usuario@servidor
sudo mkdir -p /var/www/tu-dominio && sudo rsync -rlt /tmp/kcg/ /var/www/tu-dominio/

# 2. Instala (una sola orden)
cd /var/www/tu-dominio
sudo sh instalar.sh tu-dominio.com
```

Qué hace `instalar.sh`, en orden:

1. Comprueba que eres administrador y que hay PHP 8.1+ en la línea de comandos.
2. Detecta el usuario con el que corre PHP (mira los procesos de PHP-FPM; si no, prueba `www-data`, `nginx`, `apache`, `http`). Se puede forzar con `--web-user USUARIO`.
3. Crea las carpetas escribibles y las asigna a ese usuario (`scripts/preparar-servidor.sh`), también la carpeta privada `config/` junto a la carpeta de la web (o la de `--privada /ruta`), y comprueba, escribiendo como ese usuario, que funcionan.
4. Comprueba extensiones y carpetas con el mismo usuario (`php scripts/install-cli.php --check`). La línea de comandos puede tener un `php.ini` distinto al de PHP-FPM, así que el panel lo vuelve a comprobar.
5. Te pide usuario y contraseña (mínimo 12 caracteres, sin mostrarla) y crea el administrador, los ajustes iniciales y la base del microblog (`scripts/install-cli.php`). La contraseña se pasa por la entrada estándar: no queda en el historial ni en la lista de procesos. Si ya había administrador, no crea otro.
6. Borra `install.php` y muestra el bloque de configuración de Nginx (rutas, bloqueo de carpetas privadas, tamaño de subida y PHP-FPM). Con Apache, el `.htaccess` incluido ya lo cubre.

Para automatizarlo (sin preguntas): `export KCG_ADMIN_PASSWORD=...` y `sudo --preserve-env=KCG_ADMIN_PASSWORD sh instalar.sh tu-dominio.com --admin nombre`.

El código queda como propiedad de `root` (PHP no puede modificarlo) y PHP solo tiene permiso de escritura sobre `img/`, `data/`, `imagenes/`, `microblog/`, `uploads/` y la carpeta privada. Es lo recomendable. Si más adelante copias archivos y el panel dice que no puede guardar algo, repite `sudo sh scripts/preparar-servidor.sh`.

## Instalación en un hosting compartido (la más sencilla)

El instalador web muestra primero la **comprobación del servidor** (✔ bien, ! aviso, ✖ impide instalar), cada punto con su explicación.

1. Añade tu dominio o subdominio en el panel del hosting y activa HTTPS.
2. Genera el paquete de producción (`php scripts/export-core.php ../kcg-hosting --produccion`) o descarga el repositorio sin `tests/`, `docs/`, `.github/` ni `scripts/` de desarrollo, y copia **todo su contenido, incluidos los archivos ocultos**, en la raíz pública del dominio, normalmente `public_html`. Asegúrate de que también se copie `.htaccess`; algunos programas FTP ocultan los archivos que empiezan por punto.
3. Confirma que el hosting usa PHP 8.1+ y permite las reglas de `.htaccess`. Si usa Nginx, el proveedor debe habilitar el fallback de rutas a `index.php` y bloquear `var/`, `data/` e `img/`; el archivo `.htaccess` no configura Nginx.
4. Abre `https://tu-dominio/install.php`, introduce la dirección HTTPS del sitio, el usuario y una contraseña única de al menos 12 caracteres. El instalador deja la web lista para usar: guarda el acceso y la URL, crea `site-settings.json` con los ajustes iniciales, prepara `img/`, `imagenes/` y `microblog/`, crea la base del microblog (SQLite) y comprueba que PHP puede escribir en todo. La página final enumera lo que ha preparado y avisa de lo que falte (por ejemplo la extensión `pdo_sqlite` o el `.htaccess`).
5. El instalador prepara `config/` fuera de la carpeta pública cuando el hosting permite escribir en la carpeta superior de `public_html`. Guarda allí la URL pública y el hash de acceso. Al terminar intenta borrarse automáticamente.
6. **Borra `install.php`.** Se elimina solo al terminar, pero comprueba en el gestor de archivos que ya no está en `public_html`; si sigue allí, bórralo manualmente. Es mejor no tenerlo nunca publicado una vez instalada la web: si ya existe un administrador registrado el instalador no permite crear otro y se borra, pero un archivo de más es siempre una puerta de más.
7. Inicia sesión en `https://tu-dominio/admin.php`, personaliza el diseño y los textos, y sube fotografías.

Las rutas de fotografía como `/foto/slug` dependen de las reglas de `.htaccess`. Si una ficha muestra el error 404 de LiteSpeed, comprueba que ese archivo está en la raíz pública y que el hosting permite sus reglas de reescritura.

La estructura recomendada es:

```text
cuenta/
├── public_html/   ← archivos de la web
└── config/        ← ajustes y credenciales privados
```

No copies tus fotos, contraseñas ni archivos generados en el repositorio.

## Si el hosting no permite crear ../config

Crea manualmente `config` fuera de `public_html`, asigna permisos de escritura al usuario PHP y configura la variable de entorno `GALLERY_PRIVATE_DIR` con la ruta absoluta que indique el panel del hosting. Vuelve a abrir el instalador. En Linux suele parecerse a `/home/usuario/config`; en Windows usa la ruta absoluta que indique el proveedor. No inventes ni copies literalmente esos ejemplos.

Si el proveedor no permite escribir fuera del directorio público, la aplicación recurre a `var/` dentro del proyecto. Apache la bloquea mediante el `.htaccess` incluido. Con Nginx, el proveedor debe configurar expresamente que las solicitudes web a `/var/`, `/data/` e `/img/` se denieguen. Si no se puede garantizar ese bloqueo, no uses ese hosting para esta instalación.

## Permisos de escritura (todas las carpetas de una vez)

PHP solo necesita escribir en estas carpetas: `img/`, `data/`, `imagenes/desktop/`, `imagenes/mobile/`, `microblog/`, `uploads/` (imágenes de marca que subes desde el panel) y la carpeta privada `config/`. El código de la web nunca se modifica. Si el instalador informa de que PHP no puede escribir, te lista **todas** las que fallan juntas. No des permisos globales a todos los archivos:

- **En un VPS o servidor propio**, `sudo sh instalar.sh` ya lo hace por ti. Si solo necesitas arreglar permisos, ejecuta una sola vez, desde la carpeta de la web: `sudo sh scripts/preparar-servidor.sh` (o `sudo sh scripts/preparar-servidor.sh usuario-php` si PHP no corre como `www-data`). Crea las carpetas que falten, las asigna al usuario de PHP y comprueba que puede escribir en cada una.
- **En un hosting compartido**, pide al proveedor que asigne esas carpetas al usuario PHP o cámbialas desde el gestor de archivos (permisos 755 o 775).

Si después el panel dice que no puede guardar una imagen, es el mismo motivo: vuelve a ejecutar el script.

## Límites y seguridad

El instalador funciona igual en Linux y Windows en cuanto a las rutas PHP, pero no puede superar restricciones del proveedor: algunos planes no permiten escritura fuera de `public_html`, cambiar reglas del servidor o configurar variables de entorno. En ese caso sigue las instrucciones anteriores o elige otro plan.

El instalador solo debe estar publicado durante el primer ajuste. Aunque intenta borrarse al guardar, verifica siempre el borrado desde el panel del hosting. Si el acceso ya existe, el instalador no lo reemplaza; para recuperarlo utiliza el procedimiento documentado en el [README](../README.md#recuperar-o-cambiar-el-acceso-de-administración) y elimina el instalador si lo habías subido.

Define `GALLERY_PUBLIC_URL` y `GALLERY_PRIVATE_DIR` en el panel del hosting si prefieres que la configuración del servidor tenga prioridad sobre los valores guardados. La aplicación no carga archivos `.env`.

## Copias de seguridad

Copia las fotografías y la carpeta privada `config` (o `var` si se usa el fallback). El microblog es un archivo `microblog.sqlite` dentro de esa carpeta. Si migras el dominio, ejecuta el instalador solo en una instalación vacía o actualiza `public-url.txt` manualmente.

## Personalizar y actualizar

- **`installation.php`** (opcional, en la raíz pública): copia `installation.example.php` y deja solo lo que necesites: dirección, rutas privadas, lugar de la web, textos y ajustes iniciales. El instalador no lo necesita.
- **Actualizar**: `php scripts/update.php <carpeta-con-la-version-nueva>` guarda una copia de lo que cambia y puede deshacerse con `php scripts/update.php --rollback`. Ver el README.
