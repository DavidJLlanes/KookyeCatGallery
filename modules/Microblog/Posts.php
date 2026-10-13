<?php
declare(strict_types=1);

namespace Kcg\Modules\Microblog;

/**
 * Reglas de lista de posts que no dependen de dónde se guarden: qué es público y en qué orden se muestra.
 * Las usa el almacén JSON (en memoria) y las comprueba la prueba de equivalencia con SQLite, que las traduce a SQL.
 */
final class Posts
{
    /** Un post programado (con fecha futura) aún no es público. */
    public static function isScheduled(array $post, int $now): bool
    {
        return $post['created'] > $now;
    }

    /** Posts que ve el público: sin los programados ni las respuestas de un post que todavía no se ha publicado. */
    public static function visible(array $posts, int $now): array
    {
        return array_filter($posts, static fn(array $post): bool => !self::isScheduled($post, $now)
            && ($post['parent'] === null || (isset($posts[$post['parent']]) && !self::isScheduled($posts[$post['parent']], $now))));
    }

    /** Posts raíz: el fijado primero y después del más reciente al más antiguo (con la misma hora gana el último creado). */
    public static function roots(array $posts): array
    {
        // Se invierte antes de ordenar: usort es estable.
        $roots = array_reverse(array_values(array_filter($posts, static fn(array $post): bool => $post['parent'] === null)));
        usort($roots, static fn(array $a, array $b): int => ($b['pinned'] <=> $a['pinned']) ?: ($b['created'] <=> $a['created']));
        return $roots;
    }

    /** Respuestas de un post raíz, en orden cronológico (el hilo se lee de arriba abajo). */
    public static function replies(array $posts, string $rootId): array
    {
        $replies = array_values(array_filter($posts, static fn(array $post): bool => $post['parent'] === $rootId));
        usort($replies, static fn(array $a, array $b): int => $a['created'] <=> $b['created']);
        return $replies;
    }
}
