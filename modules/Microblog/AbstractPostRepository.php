<?php
declare(strict_types=1);

namespace Kcg\Modules\Microblog;

/** Consultas derivadas de all() en memoria: valen para cualquier almacén y son la referencia que debe igualar SQLite. */
abstract class AbstractPostRepository implements PostRepository
{
    public function find(string $id): ?array
    {
        return $this->all()[$id] ?? null;
    }

    public function publicFeed(int $offset, int $size, int $now): array
    {
        $posts = Posts::visible($this->all(), $now);
        $roots = Posts::roots($posts);
        $replies = [];
        $slice = array_slice($roots, max(0, $offset), $size);
        foreach ($slice as $root) $replies[$root['id']] = Posts::replies($posts, $root['id']);
        return ['roots' => $slice, 'replies' => $replies, 'total' => count($roots)];
    }

    public function publicThread(string $id, int $now): ?array
    {
        $posts = Posts::visible($this->all(), $now);
        $post = $posts[$id] ?? null;
        if ($post === null) return null;
        $root = $posts[$post['parent'] ?? $post['id']] ?? $post;
        return ['post' => $post, 'root' => $root, 'replies' => Posts::replies($posts, $root['id'])];
    }

    public function threads(int $limit): array
    {
        $posts = $this->all();
        $threads = [];
        foreach (array_slice(Posts::roots($posts), 0, $limit) as $root) {
            $threads[] = ['root' => $root, 'replies' => Posts::replies($posts, $root['id'])];
        }
        return $threads;
    }
}
