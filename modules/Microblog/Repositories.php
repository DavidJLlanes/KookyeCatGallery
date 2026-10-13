<?php
declare(strict_types=1);

namespace Kcg\Modules\Microblog;

use Throwable;

/** Elige y guarda el almacén del microblog: SQLite si el servidor tiene pdo_sqlite y, si no, el archivo JSON. */
final class Repositories
{
    private static ?PostRepository $current = null;

    /** El almacén de la carpeta de datos del sitio (microblog_data_dir()). */
    public static function current(): PostRepository
    {
        return self::$current ??= self::create(\microblog_data_dir());
    }

    public static function create(string $dir): PostRepository
    {
        if (extension_loaded('pdo_sqlite') && in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            try {
                return new SqlitePostRepository($dir);
            } catch (Throwable $error) {
                // Con un posts.json pendiente de importar un fallo no debe pasar en silencio al archivo: se vería un microblog vacío o desfasado.
                if (is_file($dir . '/posts.json') || is_file($dir . '/microblog.sqlite')) throw $error;
            }
        }
        return new JsonPostRepository($dir);
    }

    /** Fuerza otro almacén (pruebas) o, con null, vuelve a elegirlo en el siguiente uso. */
    public static function use(?PostRepository $repository): void
    {
        self::$current = $repository;
    }
}
