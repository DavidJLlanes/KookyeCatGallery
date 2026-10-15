<?php
declare(strict_types=1);

namespace Kcg\Core\Release;

use RuntimeException;

/**
 * Lista de los archivos del núcleo de una versión (`core-manifest.json`): ruta => huella SHA-1.
 * Con ella una actualización sabe qué archivos añadir, cambiar o retirar, y si alguien modificó a mano un archivo del núcleo.
 */
final class Manifest
{
    public const FILE = 'core-manifest.json';

    /** Rutas (desde la raíz) que no pertenecen al núcleo: datos y archivos propios de cada instalación. */
    private const INSTALLATION = [
        'installation.php', 'install.php', 'img/', 'imagenes/', 'uploads/', 'microblog/', 'data/', 'var/', 'log/', 'config/', 'version.json', 'sitemap.xml',
        'core-manifest.json', '.git/', 'node_modules/', 'debug.log',
    ];

    /** Dentro de las carpetas de datos, lo que sí viene en el núcleo. */
    private const CORE_EXCEPTIONS = ['data/.htaccess', 'data/.gitkeep', 'img/.htaccess', 'img/.gitkeep', 'imagenes/desktop/.gitkeep', 'imagenes/mobile/.gitkeep',
        'microblog/.gitkeep', 'var/.htaccess', 'var/.gitkeep', 'uploads/.htaccess', 'uploads/.gitkeep'];

    public static function hash(string $file): string
    {
        $content = @file_get_contents($file);
        if ($content === false) throw new RuntimeException("No se puede leer $file");
        // Los saltos de línea no cuentan: una copia con CRLF es el mismo archivo.
        return sha1(str_replace("\r\n", "\n", $content));
    }

    /** ¿Es una ruta propia de la instalación (no la toca ni una actualización ni una exportación del núcleo)? */
    public static function isInstallationPath(string $path): bool
    {
        if (in_array($path, self::CORE_EXCEPTIONS, true)) return false;
        foreach (self::INSTALLATION as $prefix) {
            if ($path === $prefix || (str_ends_with($prefix, '/') && str_starts_with($path, $prefix))) return true;
        }
        return false;
    }

    /**
     * Huellas de todos los archivos del núcleo de una carpeta (sin los propios de la instalación).
     *
     * @param list<string> $exclude rutas o prefijos («carpeta/») que además se dejan fuera
     * @return array<string, string>
     */
    public static function scan(string $dir, array $exclude = []): array
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $path = substr(str_replace('\\', '/', $file->getPathname()), strlen($dir) + 1);
            if (self::isInstallationPath($path)) continue;
            foreach ($exclude as $prefix) {
                if ($path === $prefix || (str_ends_with($prefix, '/') && str_starts_with($path, $prefix))) continue 2;
            }
            $files[$path] = self::hash($file->getPathname());
        }
        ksort($files);
        return $files;
    }

    /** @return array{version: string, files: array<string, string>}|null */
    public static function read(string $dir): ?array
    {
        $path = rtrim($dir, '/\\') . '/' . self::FILE;
        if (!is_file($path)) return null;
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) && is_array($data['files'] ?? null) ? ['version' => (string) ($data['version'] ?? ''), 'files' => $data['files']] : null;
    }

    public static function write(string $dir, string $version, array $files): void
    {
        $json = json_encode(['version' => $version, 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (@file_put_contents(rtrim($dir, '/\\') . '/' . self::FILE, $json . "\n") === false) throw new RuntimeException('No se pudo escribir ' . self::FILE);
    }
}
