#!/bin/sh
# Deja listas, de una sola vez, todas las carpetas donde la web necesita escribir: las crea si faltan, las asigna al usuario con el
# que corre PHP y comprueba, con ese mismo usuario, que puede escribir en cada una. Se puede repetir sin riesgo.
#
#   sudo sh scripts/preparar-servidor.sh                  # PHP corre como www-data (Debian, Ubuntu)
#   sudo sh scripts/preparar-servidor.sh usuario-php      # otro usuario (nginx, apache, el de tu hosting…)
#   sudo sh scripts/preparar-servidor.sh www-data /ruta   # además, carpeta privada en otra ruta (GALLERY_PRIVATE_DIR)
#
# No toca el código de la web ni borra nada. La carpeta privada (ajustes y acceso del administrador) es «config», junto a la
# carpeta de la web, salvo que se indique otra ruta. Esta lista debe coincidir con Installation::WRITABLE_DIRECTORIES (hay un test).
set -eu

WRITABLE_DIRECTORIES="img data imagenes/desktop imagenes/mobile microblog uploads uploads/icons"

WEB_USER="${1:-www-data}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PRIVATE_DIR="${2:-$(dirname "$ROOT")/config}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Ejecútalo como administrador: sudo sh scripts/preparar-servidor.sh" >&2
    exit 1
fi
if ! id "$WEB_USER" >/dev/null 2>&1; then
    echo "No existe el usuario «$WEB_USER». Indica el usuario con el que corre PHP: sudo sh scripts/preparar-servidor.sh usuario-php" >&2
    exit 1
fi
WEB_GROUP="$(id -gn "$WEB_USER")"

echo "Web: $ROOT"
echo "Usuario PHP: $WEB_USER:$WEB_GROUP"
echo "Carpeta privada: $PRIVATE_DIR"
echo

for relative in $WRITABLE_DIRECTORIES; do
    directory="$ROOT/$relative"
    mkdir -p "$directory"
    chown -R "$WEB_USER:$WEB_GROUP" "$directory"
    find "$directory" -type d -exec chmod 775 {} +
    find "$directory" -type f -exec chmod 664 {} +
done

# La web mantiene sitemap.xml al día y añade su línea a robots.txt: esos dos archivos (no la carpeta) los escribe PHP.
[ -e "$ROOT/sitemap.xml" ] || : > "$ROOT/sitemap.xml"
for file in sitemap.xml robots.txt; do
    if [ -f "$ROOT/$file" ]; then
        chown "$WEB_USER:$WEB_GROUP" "$ROOT/$file"
        chmod 664 "$ROOT/$file"
    fi
done

mkdir -p "$PRIVATE_DIR"
chown -R "$WEB_USER:$WEB_GROUP" "$PRIVATE_DIR"
chmod 750 "$PRIVATE_DIR"

failed=0
check() {
    if su -s /bin/sh "$WEB_USER" -c "touch '$1/.prueba-escritura' && rm -f '$1/.prueba-escritura'" 2>/dev/null; then
        echo "  OK     $2"
    else
        echo "  FALLA  $2"
        failed=1
    fi
}
echo "Comprobación (escribiendo como $WEB_USER):"
for relative in $WRITABLE_DIRECTORIES; do check "$ROOT/$relative" "$relative/"; done
check "$PRIVATE_DIR" "carpeta privada ($PRIVATE_DIR)"

echo
if [ "$failed" -eq 0 ]; then
    echo "Todo listo: recarga install.php (o el panel) y continúa."
else
    echo "Alguna carpeta sigue sin permitir escribir. Causas habituales:" >&2
    echo "  - open_basedir en PHP-FPM no incluye esa ruta (añádela en el pool: php_admin_value[open_basedir])." >&2
    echo "  - SELinux o AppArmor lo impiden (revisa «getenforce» o el perfil de PHP-FPM)." >&2
    echo "  - PHP corre con otro usuario: vuelve a ejecutar indicándolo (sudo sh scripts/preparar-servidor.sh usuario-php)." >&2
    exit 1
fi
