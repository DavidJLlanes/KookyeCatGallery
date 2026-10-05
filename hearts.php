<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$privateDir = getenv('GALLERY_PRIVATE_DIR') ?: __DIR__ . '/var';
define('HEARTS_FILE', rtrim($privateDir, '/\\') . '/hearts.json');
const HEARTS_COOKIE = 'kookye_gallery_hearts';

function hearts_reply(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hearts_valid_slug(string $slug): bool
{
    return $slug !== '' && strlen($slug) <= 150
        && preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) === 1;
}

function hearts_slug(string $slug): string
{
    $slug = trim($slug);
    if (!hearts_valid_slug($slug)) hearts_reply(422, ['ok' => false, 'error' => 'Fotografía no válida.']);
    return $slug;
}

function hearts_visitor(bool $create): ?string
{
    $cookie = $_COOKIE[HEARTS_COOKIE] ?? null;
    if (is_string($cookie) && preg_match('/^[a-f0-9]{32}$/', $cookie)) {
        return hash('sha256', $cookie);
    }
    if (!$create) return null;

    $cookie = bin2hex(random_bytes(16));
    setcookie(HEARTS_COOKIE, $cookie, [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    return hash('sha256', $cookie);
}

function hearts_lock(int $mode)
{
    $handle = @fopen(HEARTS_FILE . '.lock', 'c+');
    if ($handle === false) hearts_reply(503, ['ok' => false, 'error' => 'Contador no disponible.']);
    if (!flock($handle, $mode)) {
        fclose($handle);
        hearts_reply(503, ['ok' => false, 'error' => 'Contador ocupado.']);
    }
    return $handle;
}

function hearts_read(): array
{
    if (!is_file(HEARTS_FILE)) return ['counts' => [], 'voters' => []];
    $raw = @file_get_contents(HEARTS_FILE);
    if ($raw === false) hearts_reply(503, ['ok' => false, 'error' => 'No se pudo leer el contador.']);
    $decoded = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($decoded)) hearts_reply(503, ['ok' => false, 'error' => 'El contador necesita reparación.']);

    // El formato antiguo era directamente {slug: número}. Conservamos esas cifras.
    $rawCounts = isset($decoded['counts']) && is_array($decoded['counts'])
        ? $decoded['counts'] : $decoded;
    $counts = [];
    foreach ($rawCounts as $slug => $count) {
        if (is_string($slug) && hearts_valid_slug($slug) && is_numeric($count)) {
            $counts[$slug] = max(0, (int) $count);
        }
    }

    $voters = [];
    foreach (($decoded['voters'] ?? []) as $slug => $entries) {
        if (!is_string($slug) || !hearts_valid_slug($slug) || !is_array($entries)) continue;
        foreach ($entries as $visitor => $value) {
            if (is_string($visitor) && preg_match('/^[a-f0-9]{64}$/', $visitor) && $value) {
                $voters[$slug][$visitor] = 1;
            }
        }
    }
    return ['counts' => $counts, 'voters' => $voters];
}

function hearts_save(array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) hearts_reply(503, ['ok' => false, 'error' => 'No se pudo guardar el contador.']);
    $temporary = @tempnam(dirname(HEARTS_FILE), '.counts-');
    if ($temporary === false) hearts_reply(503, ['ok' => false, 'error' => 'No se pudo guardar el contador.']);
    $written = @file_put_contents($temporary, $json);
    @chmod($temporary, 0640);
    if ($written !== strlen($json) || !@rename($temporary, HEARTS_FILE)) {
        @unlink($temporary);
        hearts_reply(503, ['ok' => false, 'error' => 'No se pudo guardar el contador.']);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    $slugs = array_values(array_unique(array_filter(explode(',', (string) ($_GET['slugs'] ?? '')))));
    if (count($slugs) > 100) hearts_reply(422, ['ok' => false, 'error' => 'Demasiadas fotografías.']);
    foreach ($slugs as $slug) hearts_slug($slug);
    $lock = hearts_lock(LOCK_SH);
    $data = hearts_read();
    flock($lock, LOCK_UN);
    fclose($lock);
    $visitor = hearts_visitor(false);
    $counts = [];
    $liked = [];
    foreach ($slugs as $slug) {
        $counts[$slug] = $data['counts'][$slug] ?? 0;
        $liked[$slug] = $visitor !== null && isset($data['voters'][$slug][$visitor]);
    }
    hearts_reply(200, ['ok' => true, 'counts' => $counts, 'liked' => $liked]);
}

if ($method !== 'POST') hearts_reply(405, ['ok' => false, 'error' => 'Método no permitido.']);

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !==
    parse_url('https://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    hearts_reply(403, ['ok' => false, 'error' => 'Origen no permitido.']);
}
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    hearts_reply(403, ['ok' => false, 'error' => 'Origen no permitido.']);
}

$slug = hearts_slug((string) ($_POST['slug'] ?? ''));
$action = (string) ($_POST['action'] ?? 'like');
if ($action !== 'like' && $action !== 'unlike') {
    hearts_reply(422, ['ok' => false, 'error' => 'Acción no válida.']);
}
$visitor = hearts_visitor($action === 'like');
$lock = hearts_lock(LOCK_EX);
$data = hearts_read();
$alreadyLiked = $visitor !== null && isset($data['voters'][$slug][$visitor]);
$changed = false;
if ($action === 'like' && !$alreadyLiked && $visitor !== null) {
    $data['voters'][$slug][$visitor] = 1;
    $data['counts'][$slug] = min(PHP_INT_MAX, ($data['counts'][$slug] ?? 0) + 1);
    $changed = true;
} elseif ($action === 'unlike' && $alreadyLiked) {
    unset($data['voters'][$slug][$visitor]);
    if (!$data['voters'][$slug]) unset($data['voters'][$slug]);
    $remaining = max(0, ($data['counts'][$slug] ?? 0) - 1);
    if ($remaining === 0) unset($data['counts'][$slug]);
    else $data['counts'][$slug] = $remaining;
    $changed = true;
}
if ($changed) hearts_save($data);
flock($lock, LOCK_UN);
fclose($lock);
hearts_reply(200, [
    'ok' => true,
    'slug' => $slug,
    'count' => $data['counts'][$slug] ?? 0,
    'liked' => $action === 'like' ? true : false,
]);
