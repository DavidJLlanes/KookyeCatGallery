<?php
declare(strict_types=1);

namespace Kcg\Modules\Microblog;

/**
 * Almacén de los posts del microblog. Un post es el array normalizado de microblog_normalize()
 * (id, parent, created, updated, level, pinned, text, quote, links, images).
 *
 * Hay dos implementaciones con el mismo comportamiento (lo comprueba tests/microblog-repository.php):
 * SqlitePostRepository (la normal) y JsonPostRepository (reserva si el servidor no tiene pdo_sqlite).
 */
interface PostRepository
{
    /** Todos los posts, también los programados, por orden de creación (id => post). Para el panel y las pruebas. */
    public function all(): array;

    public function find(string $id): ?array;

    /**
     * Lee, modifica y guarda los posts como una operación indivisible. `$change` recibe la lista por referencia
     * (id => post) y devuelve el resultado, que se devuelve aquí. Si lanza una excepción no se guarda nada.
     */
    public function mutate(callable $change): mixed;

    /**
     * Una página de la portada: los hilos públicos desde `$offset`, con todas sus respuestas públicas.
     *
     * @return array{roots: list<array>, replies: array<string, list<array>>, total: int} `total` = hilos públicos en conjunto
     */
    public function publicFeed(int $offset, int $size, int $now): array;

    /**
     * Un hilo público a partir del id de cualquiera de sus posts (la raíz o una respuesta); null si no existe o no es público.
     *
     * @return array{post: array, root: array, replies: list<array>}|null
     */
    public function publicThread(string $id, int $now): ?array;

    /**
     * Hilos para el panel (incluidos los programados), el fijado primero y luego del más reciente al más antiguo.
     *
     * @return list<array{root: array, replies: list<array>}>
     */
    public function threads(int $limit): array;
}
