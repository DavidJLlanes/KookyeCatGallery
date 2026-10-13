<?php
declare(strict_types=1);

namespace Kcg\Core;

/**
 * Carga automática de clases por espacio de nombres, sin Composer.
 *
 * Convención (PSR-4 con una raíz en minúsculas por componente):
 *   Kcg\Core\Foo              → core/Foo.php
 *   Kcg\Modules\Microblog\Bar → modules/Microblog/Bar.php
 *   Kcg\Themes\Palettes\Baz   → themes/Palettes/Baz.php
 *
 * Una clase por archivo; el archivo se llama como la clase (PascalCase.php) y su carpeta como el espacio de nombres.
 * Las funciones sueltas heredadas siguen en inc/ y se cargan con require; esta carga es para el código nuevo.
 */
final class Autoloader
{
    /** @var array<string, string> prefijo de espacio de nombres (con «\» final) → carpeta */
    private static array $roots = [];
    private static bool $registered = false;

    /** Añade una raíz de clases y, la primera vez, registra el cargador. */
    public static function addRoot(string $prefix, string $directory): void
    {
        self::$roots[trim($prefix, '\\') . '\\'] = rtrim($directory, '/\\');
        if (!self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    /** Ruta de archivo que le corresponde a una clase, o null si ninguna raíz la cubre. */
    public static function pathFor(string $class): ?string
    {
        $class = ltrim($class, '\\');
        foreach (self::$roots as $prefix => $directory) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;
            $relative = substr($class, strlen($prefix));
            return $directory . '/' . str_replace('\\', '/', $relative) . '.php';
        }
        return null;
    }

    public static function load(string $class): void
    {
        $path = self::pathFor($class);
        if ($path !== null && is_file($path)) require $path;
    }

    /** @return array<string, string> raíces registradas (para pruebas y diagnóstico) */
    public static function roots(): array
    {
        return self::$roots;
    }
}
