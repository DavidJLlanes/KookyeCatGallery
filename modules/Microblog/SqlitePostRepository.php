<?php
declare(strict_types=1);

namespace Kcg\Modules\Microblog;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Almacén en SQLite (`microblog.sqlite` en la carpeta de datos). Las consultas públicas se resuelven en la base de datos
 * (una página de la portada lee solo sus hilos, no todos los posts) y las escrituras son transacciones.
 *
 * El orden de los posts con la misma hora lo da el `rowid` (orden de creación), igual que en el archivo JSON original.
 * Si junto a la base hay un `posts.json` de la versión anterior, se importa solo la primera vez (ver importLegacy()).
 */
final class SqlitePostRepository extends AbstractPostRepository
{
    /** Versión del esquema (PRAGMA user_version); cada cambio futuro añade un paso a migrate(). */
    public const SCHEMA_VERSION = 1;

    private PDO $pdo;

    public function __construct(private readonly string $dir)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('No se pudo crear la carpeta de datos del microblog.');
        try {
            $this->pdo = new PDO('sqlite:' . $this->databaseFile(), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            $this->migrate();
            @chmod($this->databaseFile(), 0640);
        } catch (PDOException $error) {
            throw new RuntimeException('No se pudo abrir la base de datos del microblog: ' . $error->getMessage(), 0, $error);
        }
        if (is_file($this->legacyFile())) $this->importLegacy();
    }

    public function databaseFile(): string
    {
        return $this->dir . '/microblog.sqlite';
    }

    public function legacyFile(): string
    {
        return $this->dir . '/posts.json';
    }

    private function migrate(): void
    {
        $version = (int) $this->pdo->query('PRAGMA user_version')->fetchColumn();
        if ($version >= self::SCHEMA_VERSION) return;
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $version = (int) $this->pdo->query('PRAGMA user_version')->fetchColumn(); // otro proceso pudo adelantarse
            if ($version < 1) {
                $this->pdo->exec('CREATE TABLE IF NOT EXISTS posts (
                    id TEXT PRIMARY KEY,
                    parent TEXT,
                    created INTEGER NOT NULL,
                    updated INTEGER NOT NULL,
                    level INTEGER NOT NULL DEFAULT 0,
                    pinned INTEGER NOT NULL DEFAULT 0,
                    text TEXT NOT NULL DEFAULT \'\',
                    quote TEXT,
                    links TEXT NOT NULL DEFAULT \'[]\',
                    images TEXT NOT NULL DEFAULT \'[]\'
                )');
                $this->pdo->exec('CREATE INDEX IF NOT EXISTS posts_roots ON posts (parent, pinned, created)');
            }
            $this->pdo->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            $this->pdo->exec('COMMIT');
        } catch (Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
    }

    /**
     * Importa el `posts.json` de la versión anterior. Dentro de una transacción inserta los posts válidos que aún no estén
     * (por id), comprueba que están todos y solo entonces aparta el archivo como `posts.migrado-AAAAMMDD-HHMMSS.json`
     * (no se borra: es la copia de seguridad). Si algo falla, el archivo queda donde estaba y no se guarda nada.
     * Un `posts.json` que no se pueda leer se deja sin tocar.
     */
    private function importLegacy(): void
    {
        $decoded = json_decode((string) @file_get_contents($this->legacyFile()), true);
        if (!is_array($decoded)) return;
        $posts = [];
        foreach (is_array($decoded['posts'] ?? null) ? $decoded['posts'] : [] as $raw) {
            $post = \microblog_normalize($raw);
            if ($post !== null) $posts[$post['id']] = $post;
        }
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            if (!is_file($this->legacyFile())) { // otro proceso ya lo importó mientras esperábamos
                $this->pdo->exec('COMMIT');
                return;
            }
            $known = array_flip($this->pdo->query('SELECT id FROM posts')->fetchAll(PDO::FETCH_COLUMN));
            foreach ($posts as $id => $post) {
                if (!isset($known[$id])) $this->upsert($post);
            }
            $stored = (int) $this->pdo->query('SELECT COUNT(*) FROM posts WHERE id IN (' . implode(',', array_map([$this->pdo, 'quote'], array_keys($posts)) ?: ["''"]) . ')')->fetchColumn();
            if ($stored !== count($posts)) throw new RuntimeException('La migración del microblog no ha copiado todos los posts.');
            $this->pdo->exec('COMMIT');
        } catch (Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
        @rename($this->legacyFile(), $this->dir . '/posts.migrado-' . date('Ymd-His') . '.json');
    }

    // -------------------------------------------------------------------------------------------------------------
    // Conversión fila <-> post
    // -------------------------------------------------------------------------------------------------------------

    private function hydrate(array $row): array
    {
        return [
            'id' => $row['id'],
            'parent' => $row['parent'],
            'created' => (int) $row['created'],
            'updated' => (int) $row['updated'],
            'level' => (int) $row['level'],
            'pinned' => (bool) $row['pinned'],
            'text' => (string) $row['text'],
            'quote' => $row['quote'] === null ? null : json_decode((string) $row['quote'], true),
            'links' => json_decode((string) $row['links'], true) ?: [],
            'images' => json_decode((string) $row['images'], true) ?: [],
        ];
    }

    private function upsert(array $post): void
    {
        $json = static fn(mixed $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->pdo->prepare('INSERT INTO posts (id, parent, created, updated, level, pinned, text, quote, links, images)
            VALUES (:id, :parent, :created, :updated, :level, :pinned, :text, :quote, :links, :images)
            ON CONFLICT(id) DO UPDATE SET parent = excluded.parent, created = excluded.created, updated = excluded.updated, level = excluded.level,
                pinned = excluded.pinned, text = excluded.text, quote = excluded.quote, links = excluded.links, images = excluded.images')
            ->execute([
                'id' => $post['id'], 'parent' => $post['parent'], 'created' => $post['created'], 'updated' => $post['updated'],
                'level' => $post['level'], 'pinned' => $post['pinned'] ? 1 : 0, 'text' => $post['text'],
                'quote' => $post['quote'] === null ? null : $json($post['quote']), 'links' => $json($post['links']), 'images' => $json($post['images']),
            ]);
    }

    /** @return list<array> */
    private function select(string $where, array $params, string $order, ?int $limit = null, int $offset = 0): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM posts' . ($where !== '' ? ' WHERE ' . $where : '') . ' ORDER BY ' . $order
            . ($limit !== null ? ' LIMIT ' . $limit . ' OFFSET ' . $offset : ''));
        $statement->execute($params);
        return array_map([$this, 'hydrate'], $statement->fetchAll());
    }

    // -------------------------------------------------------------------------------------------------------------
    // PostRepository
    // -------------------------------------------------------------------------------------------------------------

    public function all(): array
    {
        $posts = [];
        foreach ($this->select('', [], 'rowid') as $post) $posts[$post['id']] = $post;
        return $posts;
    }

    public function find(string $id): ?array
    {
        return $this->select('id = ?', [$id], 'rowid', 1)[0] ?? null;
    }

    public function mutate(callable $change): mixed
    {
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $before = $this->all();
            $posts = $before;
            $result = $change($posts);
            foreach (array_diff_key($before, $posts) as $id => $_) $this->pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
            foreach ($posts as $id => $post) {
                if (($before[$id] ?? null) !== $post) $this->upsert($post);
            }
            $this->pdo->exec('COMMIT');
            return $result;
        } catch (PDOException $error) {
            $this->pdo->exec('ROLLBACK');
            throw new RuntimeException('No se pudo guardar el microblog.', 0, $error);
        } catch (Throwable $error) {
            $this->pdo->exec('ROLLBACK');
            throw $error;
        }
    }

    public function publicFeed(int $offset, int $size, int $now): array
    {
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM posts WHERE parent IS NULL AND created <= ?');
        $count->execute([$now]);
        $roots = $this->select('parent IS NULL AND created <= ?', [$now], 'pinned DESC, created DESC, rowid DESC', max(0, $size), max(0, $offset));
        $replies = [];
        if ($roots) {
            $ids = array_column($roots, 'id');
            foreach ($ids as $id) $replies[$id] = [];
            $marks = implode(',', array_fill(0, count($ids), '?'));
            foreach ($this->select("parent IN ($marks) AND created <= ?", [...$ids, $now], 'created, rowid') as $reply) $replies[$reply['parent']][] = $reply;
        }
        return ['roots' => $roots, 'replies' => $replies, 'total' => (int) $count->fetchColumn()];
    }

    public function publicThread(string $id, int $now): ?array
    {
        $post = $this->select('id = ? AND created <= ?', [$id, $now], 'rowid', 1)[0] ?? null;
        if ($post === null) return null;
        $root = $post;
        if ($post['parent'] !== null) {
            $root = $this->select('id = ? AND parent IS NULL AND created <= ?', [$post['parent'], $now], 'rowid', 1)[0] ?? null;
            if ($root === null) return null; // respuesta de un post todavía programado o que ya no existe
        }
        return ['post' => $post, 'root' => $root, 'replies' => $this->select('parent = ? AND created <= ?', [$root['id'], $now], 'created, rowid')];
    }

    public function threads(int $limit): array
    {
        $threads = [];
        foreach ($this->select('parent IS NULL', [], 'pinned DESC, created DESC, rowid DESC', max(0, $limit)) as $root) {
            $threads[] = ['root' => $root, 'replies' => $this->select('parent = ?', [$root['id']], 'created, rowid')];
        }
        return $threads;
    }
}
