<?php
declare(strict_types=1);

// Pruebas del almacén del microblog (Fase 7): el almacén SQLite se comporta igual que el de archivo JSON, la migración de
// posts.json es segura, las escrituras son transacciones y una página de la portada no lee todos los posts.
// Uso: php tests/microblog-repository.php  (usa carpetas temporales; no toca los datos reales)

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'microblog-repo-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
putenv('MICROBLOG_DATA_DIR=' . $tmp . '/default');
putenv('MICROBLOG_IMAGES_DIR=' . $tmp . '/img');

require __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/site-settings.php';
require __DIR__ . '/../inc/microblog.php';

use Kcg\Modules\Microblog\JsonPostRepository;
use Kcg\Modules\Microblog\PostRepository;
use Kcg\Modules\Microblog\Repositories;
use Kcg\Modules\Microblog\SqlitePostRepository;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function newDir(string $name): string
{
    global $tmp;
    $dir = $tmp . '/' . $name;
    mkdir($dir, 0775, true);
    return $dir;
}
function post(string $id, ?string $parent, int $created, array $extra = []): array
{
    return $extra + ['id' => $id, 'parent' => $parent, 'created' => $created, 'updated' => $created, 'level' => 0, 'pinned' => false,
        'text' => "Post $id", 'quote' => null, 'links' => [], 'images' => []];
}
function writeJson(string $dir, array $posts): void
{
    file_put_contents($dir . '/posts.json', json_encode(['posts' => array_values($posts)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
function ids(array $posts): array
{
    return array_map(static fn(array $post): string => $post['id'], array_values($posts));
}

$now = 1_760_000_000;
$fixture = [
    post('aaaaaa0001', null, $now - 500),
    post('aaaaaa0002', null, $now - 500, ['level' => 1]),                                  // misma hora que el anterior: gana el último creado
    post('aaaaaa0003', null, $now - 100, ['pinned' => true, 'level' => 2, 'quote' => ['text' => 'Cita ñ', 'source' => 'Fuente', 'url' => 'https://example.com']]),
    post('aaaaaa0004', 'aaaaaa0001', $now - 400, ['links' => [['url' => 'https://example.com/x', 'title' => 'Enlace «ñ»']]]),
    post('aaaaaa0005', 'aaaaaa0001', $now - 450),                                          // anterior en el tiempo, creada después
    post('aaaaaa0006', null, $now + 3600),                                                 // programado
    post('aaaaaa0007', 'aaaaaa0006', $now + 3700),                                         // respuesta de un programado
    post('aaaaaa0008', 'aaaaaa0002', $now + 60, ['images' => [['file' => 'a-1.webp', 'w' => 10, 'h' => 20, 'alt' => 'Alt']]]), // respuesta programada de un post público
    post('aaaaaa0009', 'aaaaaa0003', $now - 50),
];

// -----------------------------------------------------------------------------------------------------------------
// 1. Equivalencia: las mismas consultas dan lo mismo en el almacén JSON y en SQLite
// -----------------------------------------------------------------------------------------------------------------
$jsonDir = newDir('json');
$sqlDir = newDir('sql');
$json = new JsonPostRepository($jsonDir);
$sqlite = new SqlitePostRepository($sqlDir);
foreach ([$json, $sqlite] as $repo) {
    $repo->mutate(static function (array &$posts) use ($fixture): void {
        foreach ($fixture as $post) $posts[$post['id']] = $post;
    });
}
check($json->all() === $sqlite->all(), 'all() difiere entre JSON y SQLite.');
check(ids($sqlite->all()) === ids($fixture), 'all() debe conservar el orden de creación.');
check($sqlite->find('aaaaaa0003') === $json->find('aaaaaa0003') && $sqlite->find('aaaaaa0003')['quote']['text'] === 'Cita ñ', 'find() difiere o pierde el texto con tilde.');
check($sqlite->find('zzzzzz9999') === null, 'find() de un id inexistente debe dar null.');
foreach ([[0, 8], [0, 2], [1, 2], [2, 2], [5, 2], [0, 100]] as [$offset, $size]) {
    check($json->publicFeed($offset, $size, $now) === $sqlite->publicFeed($offset, $size, $now), "publicFeed($offset, $size) difiere entre JSON y SQLite.");
}
$feed = $sqlite->publicFeed(0, 8, $now);
check(ids($feed['roots']) === ['aaaaaa0003', 'aaaaaa0002', 'aaaaaa0001'], 'Orden de la portada: fijado, luego el más reciente y, a igual hora, el último creado. Sale: ' . implode(',', ids($feed['roots'])));
check($feed['total'] === 3, 'El total de hilos públicos no debe contar los programados.');
check(ids($feed['replies']['aaaaaa0001']) === ['aaaaaa0005', 'aaaaaa0004'] && $feed['replies']['aaaaaa0002'] === [], 'Las respuestas van por fecha y las programadas no se ven.');
foreach (['aaaaaa0001', 'aaaaaa0004', 'aaaaaa0006', 'aaaaaa0007', 'aaaaaa0008', 'aaaaaa0009', 'zzzzzz9999'] as $id) {
    check($json->publicThread($id, $now) === $sqlite->publicThread($id, $now), "publicThread($id) difiere entre JSON y SQLite.");
}
check($sqlite->publicThread('aaaaaa0006', $now) === null && $sqlite->publicThread('aaaaaa0007', $now) === null, 'Un programado y sus respuestas no son públicos.');
check($sqlite->publicThread('aaaaaa0007', $now + 7200)['root']['id'] === 'aaaaaa0006', 'Pasada la hora, el hilo programado se publica.');
check($json->publicFeed(0, 8, $now + 7200) === $sqlite->publicFeed(0, 8, $now + 7200), 'El feed tras publicarse lo programado difiere.');
check($json->threads(60) === $sqlite->threads(60) && $json->threads(2) === $sqlite->threads(2) && count($sqlite->threads(60)) === 4, 'threads() (panel) difiere o no incluye los programados.');

// Modificaciones: editar mantiene la posición; borrar un hilo quita sus respuestas; solo un fijado.
foreach ([$json, $sqlite] as $repo) {
    $repo->mutate(static function (array &$posts): void {
        $posts['aaaaaa0001']['text'] = 'Editado';
        $posts['aaaaaa0001']['updated'] = 1_760_000_001;
        foreach ($posts as $key => $_) $posts[$key]['pinned'] = false;
        $posts['aaaaaa0002']['pinned'] = true;
        foreach (['aaaaaa0004', 'aaaaaa0005'] as $reply) unset($posts[$reply]);
        $posts['aaaaaa0010'] = post('aaaaaa0010', null, 1_760_000_002);
    });
}
check($json->all() === $sqlite->all() && ids($sqlite->all())[0] === 'aaaaaa0001' && array_slice(ids($sqlite->all()), -1)[0] === 'aaaaaa0010', 'Tras editar/borrar/crear el almacén difiere o cambia el orden.');
check($json->publicFeed(0, 8, $now) === $sqlite->publicFeed(0, 8, $now) && $sqlite->publicFeed(0, 8, $now)['roots'][0]['id'] === 'aaaaaa0002', 'El feed tras modificar difiere o no pone el fijado primero.');

// -----------------------------------------------------------------------------------------------------------------
// 2. Las escrituras son transacciones: si algo falla no se guarda nada
// -----------------------------------------------------------------------------------------------------------------
$before = $sqlite->all();
try {
    $sqlite->mutate(static function (array &$posts): void {
        $posts['aaaaaa0011'] = post('aaaaaa0011', null, 1_760_000_003);
        unset($posts['aaaaaa0001']);
        throw new InvalidArgumentException('fallo a mitad');
    });
    check(false, 'La excepción debe salir de mutate().');
} catch (InvalidArgumentException $error) {
    check($error->getMessage() === 'fallo a mitad', 'mutate() debe devolver la misma excepción.');
}
check($sqlite->all() === $before, 'Una escritura fallida no debe cambiar nada.');
check($sqlite->mutate(static fn(array &$posts): int => count($posts)) === count($before), 'mutate() devuelve el resultado de la función.');
$reopened = new SqlitePostRepository($sqlDir);
check($reopened->all() === $sqlite->all(), 'Otra conexión debe ver los mismos datos (persistencia).');

// -----------------------------------------------------------------------------------------------------------------
// 3. Migración de posts.json
// -----------------------------------------------------------------------------------------------------------------
$legacyDir = newDir('legacy');
writeJson($legacyDir, array_merge($fixture, [['id' => 'MAL', 'text' => 'sin id válido'], 'basura', post('aaaaaa0001', null, 1)])); // inválidos y duplicado
$original = file_get_contents($legacyDir . '/posts.json');
$migrated = new SqlitePostRepository($legacyDir);
check(!is_file($legacyDir . '/posts.json'), 'posts.json debe apartarse tras migrar.');
$backups = glob($legacyDir . '/posts.migrado-*.json') ?: [];
check(count($backups) === 1 && file_get_contents($backups[0]) === $original, 'Debe quedar una copia idéntica del posts.json original.');
check(ids($migrated->all()) === ids($fixture), 'La migración debe conservar los posts válidos y su orden.');
check(count($migrated->all()) === count($fixture) && $migrated->find('aaaaaa0001')['created'] === 1, 'Los inválidos se descartan y un id repetido queda como en la carga de siempre (gana el último, en la posición del primero).');
$again = new SqlitePostRepository($legacyDir);
check($again->all() === $migrated->all() && count(glob($legacyDir . '/posts.migrado-*.json')) === 1, 'Abrir de nuevo no debe duplicar ni volver a migrar.');

// Un posts.json aparecido después (restaurado a mano) se suma sin pisar lo que ya hay en la base.
writeJson($legacyDir, [post('aaaaaa0001', null, 5, ['text' => 'No debe pisar']), post('bbbbbb0001', null, $now - 10)]);
$third = new SqlitePostRepository($legacyDir);
check($third->find('aaaaaa0001')['text'] === 'Post aaaaaa0001' && $third->find('bbbbbb0001') !== null, 'Un posts.json posterior añade los posts nuevos sin pisar los existentes.');
check(!is_file($legacyDir . '/posts.json'), 'El posts.json posterior también se aparta.');

// Un posts.json ilegible no se toca ni rompe la web.
$brokenDir = newDir('broken');
file_put_contents($brokenDir . '/posts.json', '{esto no es json');
$broken = new SqlitePostRepository($brokenDir);
check($broken->all() === [] && file_get_contents($brokenDir . '/posts.json') === '{esto no es json', 'Un posts.json ilegible debe quedarse donde está.');

// Sin posts.json: base vacía.
check((new SqlitePostRepository(newDir('empty')))->publicFeed(0, 8, $now) === ['roots' => [], 'replies' => [], 'total' => 0], 'Sin datos el feed debe estar vacío.');

// La versión del esquema queda registrada.
$pdo = new PDO('sqlite:' . $sqlDir . '/microblog.sqlite');
check((int) $pdo->query('PRAGMA user_version')->fetchColumn() === SqlitePostRepository::SCHEMA_VERSION, 'user_version debe ser la versión del esquema.');
unset($pdo);

// El selector elige SQLite aquí y el JSON de reserva funciona con la misma interfaz.
check(Repositories::create(newDir('auto')) instanceof SqlitePostRepository, 'Con pdo_sqlite se debe usar SQLite.');
Repositories::use($json);
check(microblog_repository() === $json && microblog_find('aaaaaa0003')['id'] === 'aaaaaa0003' && microblog_feed(0)['total'] === $json->publicFeed(0, 100, time())['total'], 'La fachada de inc/microblog.php debe usar el almacén que se le indique.');
Repositories::use(null);

// -----------------------------------------------------------------------------------------------------------------
// 4. Rendimiento: una página de la portada no depende del tamaño total
// -----------------------------------------------------------------------------------------------------------------
$total = 20000;
$bigDir = newDir('big');
$bigJsonDir = newDir('bigjson');
$big = new SqlitePostRepository($bigDir);
$posts = [];
for ($i = 0; $i < $total; $i++) {
    $id = sprintf('p%09d', $i);
    $posts[$id] = post($id, null, $now - $i * 60, ['text' => str_repeat("Texto del post $i. ", 8)]);
    if ($i % 5 === 0) {
        $reply = sprintf('r%09d', $i);
        $posts[$reply] = post($reply, $id, $now - $i * 60 + 30);
    }
}
$started = microtime(true);
$big->mutate(static function (array &$stored) use ($posts): void { $stored = $posts; });
$writeTime = microtime(true) - $started;
$bigJson = new JsonPostRepository($bigJsonDir);
$bigJson->mutate(static function (array &$stored) use ($posts): void { $stored = $posts; });

$measure = static function (PostRepository $repo) use ($now): array {
    $started = microtime(true);
    for ($i = 0; $i < 5; $i++) $feed = $repo->publicFeed(0, 8, $now);
    return ['ms' => (microtime(true) - $started) / 5 * 1000, 'feed' => $feed];
};
$sqliteRun = $measure($big);
$jsonRun = $measure($bigJson);
check($sqliteRun['feed'] === $jsonRun['feed'] && $sqliteRun['feed']['total'] === $total, 'A gran escala el feed debe ser idéntico y contar todos los hilos.');
$deep = $big->publicFeed($total - 8, 8, $now);
check(count($deep['roots']) === 8 && $deep['roots'][7]['id'] === sprintf('p%09d', $total - 1), 'La última página debe llegar al hilo más antiguo.');
$started = microtime(true);
$big->publicThread(sprintf('r%09d', 10000), $now);
$threadMs = (microtime(true) - $started) * 1000;
printf("   %d posts: primera página SQLite %.1f ms · JSON %.1f ms · hilo %.1f ms · escribir todo %.1f s\n",
    count($posts), $sqliteRun['ms'], $jsonRun['ms'], $threadMs, $writeTime);
check($sqliteRun['ms'] < 50, sprintf('La primera página con %d posts debe tardar menos de 50 ms; tarda %.1f ms.', count($posts), $sqliteRun['ms']));
check($sqliteRun['ms'] * 5 < $jsonRun['ms'], 'La página de SQLite debe ser mucho más rápida que cargar todo el archivo.');
check($threadMs < 50, sprintf('Abrir un hilo debe tardar menos de 50 ms; tarda %.1f ms.', $threadMs));

// Limpieza.
$cleanup = static function (string $dir) use (&$cleanup): void {
    foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $entry) {
        if (in_array(basename($entry), ['.', '..'], true)) continue;
        is_dir($entry) ? $cleanup($entry) : @unlink($entry);
    }
    @rmdir($dir);
};
unset($json, $sqlite, $reopened, $migrated, $again, $third, $broken, $big, $bigJson, $pdo);
gc_collect_cycles();
$cleanup($tmp);
echo "Microblog repository tests passed.\n";
