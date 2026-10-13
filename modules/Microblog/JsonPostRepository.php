<?php
declare(strict_types=1);

namespace Kcg\Modules\Microblog;

use RuntimeException;

/**
 * Almacén en un archivo `posts.json` (el formato original). Solo se usa si el servidor no tiene pdo_sqlite;
 * lee y escribe el archivo entero, así que no es para muchos posts.
 */
final class JsonPostRepository extends AbstractPostRepository
{
    public function __construct(private readonly string $dir) {}

    public function file(): string
    {
        return $this->dir . '/posts.json';
    }

    public function all(): array
    {
        if (!is_file($this->file())) return [];
        $decoded = json_decode((string) @file_get_contents($this->file()), true);
        if (!is_array($decoded)) return [];
        $posts = [];
        foreach (is_array($decoded['posts'] ?? null) ? $decoded['posts'] : [] as $raw) {
            $post = \microblog_normalize($raw);
            if ($post !== null) $posts[$post['id']] = $post;
        }
        return $posts;
    }

    public function mutate(callable $change): mixed
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0750, true)) throw new RuntimeException('No se pudo crear la carpeta de datos del microblog.');
        $lock = @fopen($this->dir . '/posts.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('El microblog está ocupado. Inténtalo de nuevo.');
        try {
            $posts = $this->all();
            $result = $change($posts);
            $json = json_encode(['posts' => array_values($posts)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $temporary = @tempnam($this->dir, '.posts-');
            if ($json === false || $temporary === false || @file_put_contents($temporary, $json) !== strlen($json)) {
                if (is_string($temporary)) @unlink($temporary);
                throw new RuntimeException('No se pudo guardar el microblog.');
            }
            @chmod($temporary, 0640);
            if (!@rename($temporary, $this->file())) {
                @unlink($temporary);
                throw new RuntimeException('No se pudo guardar el microblog.');
            }
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
