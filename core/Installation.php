<?php
declare(strict_types=1);

namespace Kcg\Core;

/**
 * Lo propio de cada instalación: dónde guarda sus datos privados y cuál es su dirección pública.
 *
 * El núcleo no contiene rutas ni dominios de ninguna web. Cada dato se busca en este orden:
 *   1. variable de entorno (la del hosting; también la usan las pruebas),
 *   2. archivo `installation.php` de la raíz del sitio (opcional; ver installation.example.php),
 *   3. el valor por defecto del núcleo (carpeta privada hermana de la raíz pública, `var/`, etc.).
 *
 * Claves de `installation.php` (todas opcionales):
 *   site_url        dirección pública con https, sin barra final
 *   private_dir     carpeta privada (ajustes, acceso del administrador)
 *   microblog_dir   datos del microblog (base SQLite)
 *   hearts_file     archivo de corazones
 *   ai_config_file  archivo PHP con las claves del generador de textos con IA
 *   session_name    nombre de la sesión del panel
 *   hearts_cookie   nombre de la cookie de corazones
 *   text_defaults   [id de texto => valor] que sustituyen a los textos por defecto del núcleo
 *   setting_defaults [ajuste => valor] que sustituyen a los valores iniciales de los ajustes
 *   place           lugar de la web, para las etiquetas geográficas y los datos estructurados (ver place())
 *   category_keywords  [categoría => [palabras]] para clasificar fotos sin categoría
 *   location_coords    [palabra => [lat, lng]] para situar fotos sin coordenadas, y default_coordinates [lat, lng]
 *
 * Es estático y guarda lo leído; reset() lo olvida (pruebas).
 */
final class Installation
{
    /** @var array<string, mixed>|null */
    private static ?array $file = null;

    /** Valor de una clave del archivo `installation.php`, o `$default`. */
    public static function value(string $key, mixed $default = null): mixed
    {
        return self::file()[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    private static function file(): array
    {
        if (self::$file !== null) return self::$file;
        $path = self::env('KCG_INSTALLATION_FILE') ?: self::root() . '/installation.php';
        $loaded = is_file($path) ? (static fn(string $file): mixed => require $file)($path) : [];
        return self::$file = is_array($loaded) ? $loaded : [];
    }

    public static function root(): string
    {
        return dirname(__DIR__);
    }

    public static function reset(): void
    {
        self::$file = null;
    }

    /**
     * Carpetas (desde la raíz) donde PHP necesita escribir. Las crea el instalador y las deja con sus permisos
     * `scripts/preparar-servidor.sh`; un test comprueba que ese script y esta lista dicen lo mismo.
     */
    public const WRITABLE_DIRECTORIES = ['img', 'data', 'imagenes/desktop', 'imagenes/mobile', 'microblog', 'uploads', 'uploads/icons'];

    /**
     * Comprueba todas las carpetas escribibles de una vez (intentando crear las que falten) y devuelve las que fallan:
     * carpeta relativa => qué le pasa. Vacío = todo en orden. Así el instalador puede dar una única lista y un único comando.
     *
     * @return array<string, string>
     */
    public static function unwritableDirectories(?string $root = null): array
    {
        $root = self::clean($root ?? self::root());
        $problems = [];
        foreach (self::WRITABLE_DIRECTORIES as $relative) {
            $directory = $root . '/' . $relative;
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                $problems[$relative] = 'no existe y PHP no puede crearla';
            } elseif (!is_writable($directory)) {
                $problems[$relative] = 'PHP no tiene permiso de escritura';
            }
        }
        return $problems;
    }

    private static function env(string ...$names): string
    {
        foreach ($names as $name) {
            $value = trim((string) getenv($name));
            if ($value !== '') return $value;
        }
        return '';
    }

    private static function clean(string $path): string
    {
        return rtrim($path, '/\\');
    }

    /**
     * Carpeta privada de la instalación (ajustes, credenciales). Orden: SITE_CONFIG_DIR o GALLERY_PRIVATE_DIR,
     * `private_dir`, la carpeta `config` hermana de la raíz pública (hosting compartido) y, como último recurso, `var/`.
     * Con `$create` intenta crearla.
     */
    public static function privateDir(bool $create = false): string
    {
        $configured = self::env('SITE_CONFIG_DIR', 'GALLERY_PRIVATE_DIR') ?: (string) self::value('private_dir', '');
        if ($configured !== '') {
            $path = self::clean($configured);
            if ($create && !is_dir($path)) @mkdir($path, 0750, true);
            return $path;
        }
        $outside = dirname(self::root()) . DIRECTORY_SEPARATOR . 'config';
        if (is_dir($outside)) return $outside;
        if (is_writable(dirname($outside)) && (!$create || (@mkdir($outside, 0750) && is_dir($outside)))) return $outside;
        $fallback = self::root() . DIRECTORY_SEPARATOR . 'var';
        if ($create && !is_dir($fallback)) @mkdir($fallback, 0750, true);
        return $fallback;
    }

    /** Dirección pública del sitio, sin barra final. */
    public static function siteUrl(): string
    {
        $configured = self::env('GALLERY_PUBLIC_URL') ?: (string) self::value('site_url', '');
        if ($configured !== '') return rtrim($configured, '/');
        $urlFile = self::privateDir() . DIRECTORY_SEPARATOR . 'public-url.txt';
        $stored = is_file($urlFile) ? trim((string) @file_get_contents($urlFile)) : '';
        return $stored !== '' ? rtrim($stored, '/') : 'https://example.com';
    }

    /** Carpetas candidatas para los datos del microblog, por orden de preferencia. */
    public static function microblogDirs(): array
    {
        $candidates = [];
        if (self::env('MICROBLOG_DATA_DIR') !== '') $candidates[] = self::env('MICROBLOG_DATA_DIR');
        elseif ((string) self::value('microblog_dir', '') !== '') $candidates[] = (string) self::value('microblog_dir');
        $candidates[] = self::privateDir() . '/microblog';
        $candidates[] = self::root() . '/data/microblog';
        return $candidates;
    }

    public static function heartsFile(): string
    {
        $configured = (string) self::value('hearts_file', '');
        return $configured !== '' ? $configured : self::privateDir() . '/hearts.json';
    }

    /** Archivo PHP con las claves de IA (devuelve un array), o '' si la instalación no tiene uno. */
    public static function aiConfigFile(): string
    {
        return (string) self::value('ai_config_file', '');
    }

    public static function sessionName(): string
    {
        return (string) self::value('session_name', 'kookye_gallery_admin');
    }

    public static function heartsCookie(): string
    {
        return (string) self::value('hearts_cookie', 'kookye_gallery_hearts');
    }

    /** Textos por defecto propios de la instalación (id => valor). */
    public static function textDefaults(): array
    {
        $defaults = self::value('text_defaults', []);
        return is_array($defaults) ? $defaults : [];
    }

    /** Valores iniciales de ajustes propios de la instalación (ajuste => valor). */
    public static function settingDefaults(): array
    {
        $defaults = self::value('setting_defaults', []);
        return is_array($defaults) ? $defaults : [];
    }

    /**
     * Lugar de la web: name, locality, region, country, geo_region, latitude, longitude y gallery_description.
     * Vacío si la instalación no declara ninguno (entonces no se publican etiquetas geográficas).
     */
    public static function place(): array
    {
        $place = self::value('place', []);
        return is_array($place) ? $place : [];
    }

    /** Palabras clave por categoría para clasificar fotos sin categoría. */
    public static function categoryKeywords(): array
    {
        $keywords = self::value('category_keywords', null);
        if (is_array($keywords) && $keywords) return $keywords;
        return [
            'Paisajes' => ['paisaje', 'montaña', 'valle', 'horizonte'],
            'Retratos' => ['retrato', 'mirada', 'primer plano'],
            'Naturaleza' => ['naturaleza', 'bosque', 'flora', 'fauna'],
            'Arquitectura' => ['arquitectura', 'edificio', 'fachada', 'monumento'],
            'Calle' => ['calle', 'ciudad', 'urbano', 'plaza'],
            'Viajes' => ['viaje', 'destino', 'ruta', 'escapada'],
            'Blanco y negro' => ['blanco y negro', 'monocromo'],
        ];
    }

    /** Coordenadas por palabra clave (vacío si la instalación no las declara). */
    public static function locationCoords(): array
    {
        $coords = self::value('location_coords', []);
        return is_array($coords) ? $coords : [];
    }

    /** Coordenadas de una foto sin ubicación [lat, lng], o null si no se asignan automáticamente. */
    public static function defaultCoordinates(): ?array
    {
        $coords = self::value('default_coordinates', null);
        return is_array($coords) && count($coords) === 2 ? [(float) $coords[0], (float) $coords[1]] : null;
    }
}
