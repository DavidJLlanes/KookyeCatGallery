<?php
declare(strict_types=1);

namespace Kcg\Core;

use DomainException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lógica de instalación, común a todas las formas de instalar: el instalador web (`install.php`, para hosting compartido) y el de
 * línea de comandos (`scripts/install-cli.php`, que usa `instalar.sh` en un VPS o servidor dedicado). Ninguno duplica nada de esto:
 * comprobar el servidor, validar los datos, preparar la carpeta privada y dejar listos ajustes, carpetas y microblog.
 *
 *   $installer = new Installer($root);
 *   $installer->checks();                      // qué cumple el servidor: ok / warn / fail, con su explicación
 *   $installer->install($url, $user, $pass);   // deja la web lista; devuelve [lo preparado, avisos]
 *
 * Errores: InvalidArgumentException = datos mal escritos (422), RuntimeException = el servidor no lo permite (500),
 * DomainException = ya hay un administrador (409). Cada mensaje dice qué hacer.
 */
final class Installer
{
    public const PASSWORD_MIN = 12;
    public const PASSWORD_MAX = 1024;

    public function __construct(private readonly string $root)
    {
    }

    /** ¿Ya hay un administrador registrado? Entonces no se puede registrar otro. */
    public function hasAdministrator(): bool
    {
        return is_file(Installation::privateDir() . DIRECTORY_SEPARATOR . 'upload-auth.php');
    }

    /**
     * Comprobaciones del servidor, en el orden en que se muestran. `fail` impide instalar; `warn` no, pero conviene saberlo.
     *
     * @return list<array{status: string, label: string, hint: string}>
     */
    public function checks(): array
    {
        $checks = [];
        $add = static function (string $status, string $label, string $hint = '') use (&$checks): void {
            $checks[] = ['status' => $status, 'label' => $label, 'hint' => $hint];
        };

        PHP_VERSION_ID >= 80100
            ? $add('ok', 'PHP ' . PHP_VERSION)
            : $add('fail', 'PHP ' . PHP_VERSION, 'Hace falta PHP 8.1 o posterior. Cambia la versión de PHP desde el panel del hosting.');
        (extension_loaded('gd') && function_exists('imagewebp'))
            ? $add('ok', 'GD con soporte para JPEG, PNG y WebP')
            : $add('fail', 'GD con soporte para WebP', 'Pide al hosting que active GD con JPEG, PNG y WebP (en un VPS: instala php-gd).');
        (extension_loaded('fileinfo') && class_exists('finfo'))
            ? $add('ok', 'Fileinfo')
            : $add('fail', 'Fileinfo', 'Pide al hosting que active la extensión Fileinfo (en un VPS: php-fileinfo, suele venir incluida).');
        extension_loaded('pdo_sqlite')
            ? $add('ok', 'SQLite (pdo_sqlite)')
            : $add('warn', 'SQLite (pdo_sqlite)', 'Sin ella la web funciona, pero el microblog usa un archivo JSON, más lento con muchos posts. Pide la extensión pdo_sqlite (en un VPS: php-sqlite3).');
        extension_loaded('exif')
            ? $add('ok', 'EXIF')
            : $add('warn', 'EXIF', 'Sin ella no se corrige la orientación de las fotos de móvil ni se leen su fecha y su GPS. Pide la extensión exif (en un VPS: php-exif o php-mbstring según la distribución).');

        $private = Installation::privateDir(true);
        $outside = !str_starts_with(str_replace('\\', '/', $private) . '/', str_replace('\\', '/', $this->root) . '/');
        if (!is_dir($private) || !is_writable($private)) {
            $add('fail', 'Carpeta privada ' . $private, (is_dir($private) ? 'PHP no tiene permiso de escritura.' : 'No existe y PHP no puede crearla.') . ' ' . $this->fixHint());
        } elseif ($outside) {
            $add('ok', 'Carpeta privada fuera de la web: ' . $private);
        } else {
            $add('warn', 'Carpeta privada dentro de la web: ' . $private, 'El hosting no deja escribir fuera de la carpeta pública, así que se usa var/. Apache la bloquea con el .htaccess incluido; con Nginx, bloquea las direcciones /var/, /data/ e /img/.');
        }

        $problems = Installation::unwritableDirectories($this->root);
        if ($problems === []) {
            $add('ok', 'Carpetas de fotos y datos con permiso de escritura (' . implode(', ', array_map(static fn(string $d): string => $d . '/', Installation::WRITABLE_DIRECTORIES)) . ')');
        } else {
            foreach ($problems as $relative => $reason) $add('fail', $relative . '/', ucfirst($reason) . '.');
            $add('fail', 'Cómo arreglarlo', $this->fixHint());
        }
        return $checks;
    }

    /** ¿Alguna comprobación impide instalar? */
    public function blocked(?array $checks = null): bool
    {
        foreach ($checks ?? $this->checks() as $check) {
            if ($check['status'] === 'fail') return true;
        }
        return false;
    }

    /**
     * Cómo dar permisos según dónde se ejecute. Si el dueño de la carpeta de la web no es quien ejecuta PHP, es un servidor
     * propio (VPS o dedicado) y se arregla con el script; si es el mismo, es un hosting compartido y se hace desde su panel.
     */
    public function fixHint(): string
    {
        $script = 'Servidor propio (VPS o dedicado): cd ' . $this->root . ' && sudo sh scripts/preparar-servidor.sh (o sudo sh instalar.sh si aún no has instalado), y recarga esta página. Si PHP no corre como www-data, añade el usuario al final.';
        $shared = 'Hosting compartido: desde el gestor de archivos o por FTP, pon permisos 755 (o 775) a esas carpetas, o pide al proveedor que las asigne al usuario de PHP.';
        if (!function_exists('posix_geteuid')) return $shared . ' ' . $script;
        $owner = @fileowner($this->root);
        return $owner !== false && $owner !== posix_geteuid() ? $script . ' ' . $shared : $shared . ' ' . $script;
    }

    /**
     * Valida y normaliza los datos del administrador. Devuelve la dirección pública sin barra final.
     *
     * @throws InvalidArgumentException con el mensaje para quien instala
     */
    public function validate(string $url, string $username, string $password): string
    {
        $url = rtrim(trim($url), '/');
        $parts = filter_var($url, FILTER_VALIDATE_URL) ? parse_url($url) : false;
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new InvalidArgumentException('Escribe una URL HTTPS válida, sin ruta ni parámetros.');
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/D', trim($username))) {
            throw new InvalidArgumentException('El usuario debe tener entre 3 y 64 caracteres: letras, números, punto, guion o guion bajo.');
        }
        if (strlen($password) < self::PASSWORD_MIN || strlen($password) > self::PASSWORD_MAX) {
            throw new InvalidArgumentException('La contraseña debe tener entre ' . self::PASSWORD_MIN . ' y ' . self::PASSWORD_MAX . ' caracteres.');
        }
        return $url;
    }

    /**
     * Deja la web lista: carpeta privada, acceso del administrador, dirección pública, ajustes iniciales y base del microblog.
     *
     * @return array{0: list<string>, 1: list<string>} [lo que se ha preparado, avisos que conviene leer]
     * @throws InvalidArgumentException|RuntimeException|DomainException
     */
    public function install(string $url, string $username, string $password): array
    {
        $url = $this->validate($url, $username, $password);
        $username = trim($username);
        if ($this->hasAdministrator()) {
            throw new DomainException('Esta web ya tiene un administrador registrado, así que no se puede registrar otro. Para restablecer la contraseña, sigue la guía de recuperación.');
        }
        $checks = $this->checks();
        if ($this->blocked($checks)) {
            $lines = [];
            foreach ($checks as $check) {
                if ($check['status'] === 'fail') $lines[] = $check['label'] . ($check['hint'] !== '' ? ': ' . $check['hint'] : '');
            }
            throw new RuntimeException("El servidor no cumple lo necesario. No se ha cambiado nada:\n- " . implode("\n- ", $lines));
        }

        $private = Installation::privateDir(true);
        $separator = DIRECTORY_SEPARATOR;
        // Defensa adicional si el proveedor apunta la carpeta privada dentro del document root.
        if (!is_file($private . $separator . '.htaccess')) @file_put_contents($private . $separator . '.htaccess', 'Require all denied' . PHP_EOL);
        if (!is_file($private . $separator . 'index.php')) @file_put_contents($private . $separator . 'index.php', '<?php' . PHP_EOL . 'http_response_code(404);' . PHP_EOL . 'exit;' . PHP_EOL);
        if (!is_file($private . $separator . 'web.config')) @file_put_contents($private . $separator . 'web.config', '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
        // uploads/ es pública (imágenes de marca): que no pueda ejecutar nada aunque alguien consiga dejar un archivo ahí.
        $uploadsRules = $this->root . '/uploads/.htaccess';
        if (!is_file($uploadsRules)) @file_put_contents($uploadsRules, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)$\">\nRequire all denied\n</FilesMatch>\n");

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') throw new RuntimeException('No se pudo generar el hash de la contraseña.');
        $auth = "<?php\nreturn " . var_export(['username' => $username, 'password_hash' => $hash], true) . ";\n";
        $authPath = $private . $separator . 'upload-auth.php';
        $urlPath = $private . $separator . 'public-url.txt';
        $temporaryUrl = tempnam($private, '.install-url-');
        if ($temporaryUrl === false) throw new RuntimeException('No se pudieron preparar los archivos privados. Comprueba permisos y espacio.');
        // El acceso se crea con «x» (falla si ya existe): dos instalaciones a la vez no pueden registrar dos administradores.
        $handle = @fopen($authPath, 'x');
        if ($handle === false) {
            @unlink($temporaryUrl);
            throw new DomainException('Esta web ya tiene un administrador registrado, así que no se puede registrar otro.');
        }
        $written = fwrite($handle, $auth);
        fclose($handle);
        if ($written !== strlen($auth) || !@chmod($authPath, 0640)) {
            @unlink($authPath);
            @unlink($temporaryUrl);
            throw new RuntimeException('No se pudo guardar el acceso. Comprueba permisos y espacio.');
        }
        if (file_put_contents($temporaryUrl, $url . "\n", LOCK_EX) === false || !@chmod($temporaryUrl, 0640) || !@rename($temporaryUrl, $urlPath)) {
            @unlink($authPath);
            @unlink($temporaryUrl);
            throw new RuntimeException('No se pudo guardar la dirección pública. Comprueba permisos y espacio.');
        }

        // Dejar la web lista: ajustes iniciales y base del microblog (así cualquier problema de permisos sale ahora y no más tarde).
        $prepared = ['Acceso del administrador «' . $username . '» y dirección pública guardados en la carpeta privada.'];
        $warnings = [];
        try {
            putenv('GALLERY_PUBLIC_URL=' . $url);
            require_once $this->root . '/inc/helpers.php';
            require_once $this->root . '/inc/site-settings.php';
            require_once $this->root . '/inc/microblog.php';
            $settingsPath = Settings\Store::path();
            if (!is_file($settingsPath)) Settings\Store::save(Settings\Schema::defaults());
            $prepared[] = 'Ajustes iniciales guardados (' . basename($settingsPath) . ').';
            $repository = \microblog_repository();
            $prepared[] = $repository instanceof \Kcg\Modules\Microblog\SqlitePostRepository
                ? 'Base del microblog creada (' . basename($repository->databaseFile()) . ').'
                : 'Microblog preparado con archivo JSON (el servidor no tiene pdo_sqlite; la web funciona igual, pero con muchos posts irá más lenta).';
        } catch (\Throwable $error) {
            $warnings[] = 'El acceso se guardó, pero no se pudieron preparar los ajustes o el microblog: ' . $error->getMessage();
        }
        foreach ($checks as $check) {
            if ($check['status'] === 'warn') $warnings[] = $check['label'] . ': ' . $check['hint'];
        }
        $prepared[] = 'Carpetas listas y con permiso de escritura: ' . implode(', ', array_map(static fn(string $d): string => $d . '/', ['img', 'data', 'imagenes/desktop', 'imagenes/mobile', 'microblog', 'uploads'])) . '.';
        if (!is_file($this->root . '/.htaccess')) $warnings[] = 'No hay .htaccess en la raíz: si el hosting es Apache, cópialo (las fichas /foto/… lo necesitan); con Nginx, configura el fallback a index.php.';
        return [$prepared, $warnings];
    }
}
