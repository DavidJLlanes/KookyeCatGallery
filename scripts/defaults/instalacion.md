# Instalación de KookyeCatGallery

## Antes de empezar

Necesitas un hosting con PHP 8.1 o posterior, GD con soporte para JPEG, PNG y WebP, Fileinfo, HTTPS y permiso para que PHP escriba archivos. La aplicación no necesita servidor de base de datos ni compilación (el microblog usa SQLite, incluido en PHP). No funciona en hosting estático.

## Instalación sencilla

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

Si el formulario informa de que PHP no puede escribir, no des permisos globales a todos los archivos. Pide al proveedor que asigne la carpeta al usuario PHP correcto o que te indique cómo configurarla.

## Límites y seguridad

El instalador funciona igual en Linux y Windows en cuanto a las rutas PHP, pero no puede superar restricciones del proveedor: algunos planes no permiten escritura fuera de `public_html`, cambiar reglas del servidor o configurar variables de entorno. En ese caso sigue las instrucciones anteriores o elige otro plan.

El instalador solo debe estar publicado durante el primer ajuste. Aunque intenta borrarse al guardar, verifica siempre el borrado desde el panel del hosting. Si el acceso ya existe, el instalador no lo reemplaza; para recuperarlo utiliza el procedimiento documentado en el [README](../README.md#recuperar-o-cambiar-el-acceso-de-administración) y elimina el instalador si lo habías subido.

Define `GALLERY_PUBLIC_URL` y `GALLERY_PRIVATE_DIR` en el panel del hosting si prefieres que la configuración del servidor tenga prioridad sobre los valores guardados. La aplicación no carga archivos `.env`.

## Copias de seguridad

Copia las fotografías y la carpeta privada `config` (o `var` si se usa el fallback). El microblog es un archivo `microblog.sqlite` dentro de esa carpeta. Si migras el dominio, ejecuta el instalador solo en una instalación vacía o actualiza `public-url.txt` manualmente.

## Personalizar y actualizar

- **`installation.php`** (opcional, en la raíz pública): copia `installation.example.php` y deja solo lo que necesites: dirección, rutas privadas, lugar de la web, textos y ajustes iniciales. El instalador no lo necesita.
- **Actualizar**: `php scripts/update.php <carpeta-con-la-version-nueva>` guarda una copia de lo que cambia y puede deshacerse con `php scripts/update.php --rollback`. Ver el README.
