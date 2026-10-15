#!/bin/sh
# Instalador para un VPS o servidor dedicado (Linux): lo deja todo listo con un solo comando.
#
#   sudo sh instalar.sh                              # pregunta lo que falte (dirección, usuario y contraseña)
#   sudo sh instalar.sh tudominio.com                # con la dirección; pregunta usuario y contraseña
#   sudo sh instalar.sh tudominio.com --admin david  # con el usuario; pregunta la contraseña
#
# Opciones:
#   --web-user USUARIO   usuario con el que corre PHP (si no se indica, se detecta; por defecto www-data)
#   --privada RUTA       carpeta privada (ajustes y acceso del administrador); por defecto «config», junto a la carpeta de la web
#   --ayuda              esta ayuda
#
# Qué hace, en orden: 1) comprueba que eres administrador y que hay PHP 8.1+; 2) detecta el usuario de PHP; 3) crea las carpetas
# con sus permisos (scripts/preparar-servidor.sh); 4) comprueba extensiones y carpetas con ese usuario; 5) crea el administrador y
# deja la web lista (scripts/install-cli.php, la misma lógica que install.php); 6) borra install.php y te enseña el bloque de
# Nginx que necesitas. Se puede repetir sin riesgo: si ya hay administrador, no crea otro.
#
# En un hosting compartido no hace falta nada de esto: sube los archivos y abre install.php en el navegador.
# La contraseña no se escribe en ningún archivo ni argumento: se pasa al instalador por la entrada estándar. Para automatizarlo,
# exporta KCG_ADMIN_PASSWORD y ejecuta: sudo --preserve-env=KCG_ADMIN_PASSWORD sh instalar.sh tudominio.com --admin david
set -eu

ROOT="$(cd "$(dirname "$0")" && pwd)"
URL=""
ADMIN=""
WEB_USER=""
PRIVATE_DIR=""

usage() { sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'; }
fail() { echo "ERROR: $*" >&2; exit 1; }
step() { echo; echo "== $*"; }

while [ $# -gt 0 ]; do
    case "$1" in
        --ayuda|-h|--help) usage; exit 0 ;;
        --admin) [ $# -ge 2 ] || fail "--admin necesita un nombre de usuario."; ADMIN="$2"; shift 2 ;;
        --web-user) [ $# -ge 2 ] || fail "--web-user necesita un usuario."; WEB_USER="$2"; shift 2 ;;
        --privada) [ $# -ge 2 ] || fail "--privada necesita una ruta."; PRIVATE_DIR="$2"; shift 2 ;;
        -*) fail "Opción desconocida: $1 (mira sh instalar.sh --ayuda)." ;;
        *) URL="$1"; shift ;;
    esac
done

# --- 1. Administrador y PHP ------------------------------------------------------------------------------------------
step "1/6 Comprobando el sistema"
[ "$(id -u)" -eq 0 ] || fail "Ejecútalo como administrador: sudo sh instalar.sh"
command -v php >/dev/null 2>&1 || fail "No hay PHP en la línea de comandos. Instálalo (Debian/Ubuntu: sudo apt install php-cli php-fpm php-gd php-sqlite3 php-mbstring) y vuelve a ejecutar."
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || fail "Hace falta PHP 8.1 o posterior (hay $(php -r 'echo PHP_VERSION;'))."
echo "  PHP $(php -r 'echo PHP_VERSION;') en $ROOT"

# --- 2. Usuario de PHP -----------------------------------------------------------------------------------------------
step "2/6 Usuario con el que corre PHP"
if [ -z "$WEB_USER" ]; then
    WEB_USER="$(ps -eo user=,comm= 2>/dev/null | awk '$1 != "root" && $2 ~ /^php-fpm|^php-cgi|^lsphp/ { print $1; exit }')"
fi
if [ -z "$WEB_USER" ]; then
    for candidate in www-data nginx apache http; do
        if id "$candidate" >/dev/null 2>&1; then WEB_USER="$candidate"; break; fi
    done
fi
[ -n "$WEB_USER" ] || fail "No encuentro el usuario de PHP. Indícalo: sudo sh instalar.sh --web-user USUARIO"
id "$WEB_USER" >/dev/null 2>&1 || fail "No existe el usuario «$WEB_USER»."
echo "  $WEB_USER"

# --- 3. Carpetas y permisos --------------------------------------------------------------------------------------------
step "3/6 Carpetas y permisos"
if [ -n "$PRIVATE_DIR" ]; then
    sh "$ROOT/scripts/preparar-servidor.sh" "$WEB_USER" "$PRIVATE_DIR"
else
    sh "$ROOT/scripts/preparar-servidor.sh" "$WEB_USER"
fi

# Todo lo que sigue se ejecuta con el usuario de PHP, para que lo que cree lo pueda leer y escribir luego la web.
as_web() { su -s /bin/sh "$WEB_USER" -c "cd '$ROOT' && $1"; }
PRIVATE_ENV=""
[ -z "$PRIVATE_DIR" ] || PRIVATE_ENV="GALLERY_PRIVATE_DIR='$PRIVATE_DIR' "

# --- 4. Comprobación -------------------------------------------------------------------------------------------------
step "4/6 Comprobando extensiones y carpetas (con PHP de línea de comandos)"
as_web "${PRIVATE_ENV}php scripts/install-cli.php --check" || fail "El servidor no cumple lo necesario (arriba se explica qué falta). Arréglalo y vuelve a ejecutar."
echo "  Nota: PHP-FPM, el que sirve la web, puede tener extensiones distintas a las de la línea de comandos; el panel lo volverá a comprobar."

# --- 5. Administrador y web lista --------------------------------------------------------------------------------------
step "5/6 Creando el administrador"
if [ -z "$URL" ]; then
    [ -t 0 ] || fail "Falta la dirección. Ejemplo: sudo sh instalar.sh tudominio.com"
    printf '  Dirección de la web (por ejemplo tudominio.com): '
    read -r URL
fi
case "$URL" in https://*) ;; http://*) fail "La dirección debe ser HTTPS." ;; *) URL="https://$URL" ;; esac
URL="${URL%/}"
echo "$URL" | grep -Eq '^https://[A-Za-z0-9.-]+(:[0-9]+)?$' || fail "Dirección no válida: $URL (usa solo el dominio, sin ruta)."

if [ -z "$ADMIN" ]; then
    if [ -t 0 ]; then
        printf '  Usuario administrador [admin]: '
        read -r ADMIN
    fi
    ADMIN="${ADMIN:-admin}"
fi
echo "$ADMIN" | grep -Eq '^[A-Za-z0-9._-]{3,64}$' || fail "Usuario no válido: 3 a 64 caracteres (letras, números, punto, guion o guion bajo)."

PASSWORD="${KCG_ADMIN_PASSWORD:-}"
if [ -z "$PASSWORD" ]; then
    [ -t 0 ] || fail "Falta la contraseña. Ejecútalo en una terminal o exporta KCG_ADMIN_PASSWORD (ver --ayuda)."
    stty -echo
    trap 'stty echo' EXIT INT TERM
    printf '  Contraseña (mínimo 12 caracteres, no se ve al escribir): '
    read -r PASSWORD
    echo
    printf '  Repítela: '
    read -r REPEAT
    echo
    stty echo
    trap - EXIT INT TERM
    [ "$PASSWORD" = "$REPEAT" ] || fail "Las contraseñas no coinciden."
fi
[ "${#PASSWORD}" -ge 12 ] || fail "La contraseña debe tener al menos 12 caracteres."

STATUS=0
printf '%s' "$PASSWORD" | as_web "${PRIVATE_ENV}php scripts/install-cli.php --url='$URL' --username='$ADMIN'" || STATUS=$?
PASSWORD=""
REPEAT=""
if [ "$STATUS" -eq 3 ]; then
    echo "  Esta web ya estaba instalada: se conserva su administrador."
elif [ "$STATUS" -ne 0 ]; then
    fail "La instalación no se completó (arriba se explica por qué). Corrige y vuelve a ejecutar: no se ha creado ningún administrador."
fi

# --- 6. Limpieza y servidor web --------------------------------------------------------------------------------------
step "6/6 Terminando"
if [ -f "$ROOT/install.php" ]; then
    rm -f "$ROOT/install.php"
    echo "  install.php eliminado (ya no hace falta)."
fi

SOCKET="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n 1 || true)"
[ -n "$SOCKET" ] || SOCKET="/run/php/php8.3-fpm.sock"
DOMAIN="${URL#https://}"
cat <<EOF

Si usas Nginx, este bloque (dentro del server de $DOMAIN, con tu certificado HTTPS) deja la web como debe estar:

    root $ROOT;
    index index.php;
    client_max_body_size 20m;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ ^/(core|inc|modules|scripts|var|data|img)/ { return 404; }
    location = /core-manifest.json { return 404; }
    location ^~ /uploads/ { location ~ \.php\$ { return 404; } }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$SOCKET;
    }

Después: sudo nginx -t && sudo systemctl reload nginx
Con Apache no hace falta nada más: el .htaccess incluido ya lo protege.

Listo. Entra en $URL/admin.php con el usuario «$ADMIN».
Para actualizar la web en el futuro: sudo php scripts/update.php /ruta/a/la/version-nueva  (mira docs/instalacion.md).
EOF
