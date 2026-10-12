<?php
declare(strict_types=1);

/**
 * Microblog: feed de notas cortas (texto, citas, enlaces e imágenes) estilo Tumblr / Threads.
 *
 *   Almacén       microblog_data_dir() · microblog_images_dir() · microblog_load() · microblog_mutate()
 *   Operaciones   microblog_create() · microblog_update() · microblog_delete()
 *   Consultas     microblog_roots() · microblog_replies() · microblog_find()
 *   Pintado       microblog_render_thread() · microblog_render_entry()
 *
 * Reglas del producto:
 *   - Solo el propietario (la sesión del panel) crea, edita, borra y responde. Los visitantes solo ven y dan corazón.
 *   - Un post puede combinar texto, una cita, enlaces y hasta 4 imágenes.
 *   - Tres niveles: 0 normal, 1 destacado, 2 súper destacado. Las respuestas siempre son de nivel 0.
 *   - Un post raíz puede fijarse arriba (`pinned`; solo uno a la vez) y cualquier post puede programarse:
 *     `created` es la fecha de publicación y, si está en el futuro, el post sigue oculto al público.
 *   - Una respuesta es un post con `parent` = id del post raíz (hilo plano, como en Threads).
 *   - Los corazones reutilizan hearts.php con el identificador «mb-{id}».
 *   - Las imágenes viven en /microblog/, separadas de la galería (/img/). Los datos, fuera del document root.
 */

require_once __DIR__ . '/private-storage.php';

const MICROBLOG_MAX_TEXT = 2000;
const MICROBLOG_MAX_QUOTE = 800;
const MICROBLOG_MAX_IMAGES = 4;
const MICROBLOG_MAX_LINKS = 4;
const MICROBLOG_PAGE_SIZE = 8;
const MICROBLOG_MAX_IMAGE_BYTES = 15728640; // 15 MiB
const MICROBLOG_IMAGE_MAX_SIDE = 1500;   // píxeles del lado mayor (se conserva la proporción)
const MICROBLOG_IMAGE_QUALITY = 80;      // calidad WebP
const MICROBLOG_FEED_REPLIES = 2; // respuestas visibles en la portada antes de «Ver hilo completo»

/** Niveles de destacado (clave => nombre mostrado). */
function microblog_levels(): array
{
    return [0 => 'Normal', 1 => 'Destacado', 2 => 'Súper destacado'];
}

// =============================================================================
// ALMACÉN
// =============================================================================

/**
 * Carpeta privada de los datos. Orden: variable MICROBLOG_DATA_DIR, la carpeta privada de la instalación
 * (gallery_private_directory(), junto a los corazones) y, como último recurso, data/microblog dentro del proyecto.
 */
function microblog_data_dir(): string
{
    static $dir = null;
    if ($dir !== null) return $dir;
    $candidates = [];
    $env = trim((string) getenv('MICROBLOG_DATA_DIR'));
    if ($env !== '') $candidates[] = $env;
    $candidates[] = gallery_private_directory(true) . '/microblog';
    $candidates[] = dirname(__DIR__) . '/data/microblog';
    foreach ($candidates as $candidate) {
        $candidate = rtrim($candidate, '/\\');
        if ((is_dir($candidate) || @mkdir($candidate, 0750, true)) && is_writable($candidate)) return $dir = $candidate;
    }
    return $dir = rtrim((string) end($candidates), '/\\');
}

/** Carpeta pública de las imágenes del microblog (separada de /img/ de la galería). */
function microblog_images_dir(): string
{
    $env = trim((string) getenv('MICROBLOG_IMAGES_DIR'));
    return rtrim($env !== '' ? $env : dirname(__DIR__) . '/microblog', '/\\');
}

function microblog_image_url(string $file): string
{
    return '/microblog/' . rawurlencode($file);
}

function microblog_file(): string
{
    return microblog_data_dir() . '/posts.json';
}

/** Zona horaria del sitio (variable MICROBLOG_TIMEZONE o la del servidor): fechas mostradas y hora de programación. */
function microblog_timezone(): DateTimeZone
{
    static $timezone = null;
    if ($timezone === null) {
        $name = trim((string) getenv('MICROBLOG_TIMEZONE')) ?: date_default_timezone_get();
        try { $timezone = new DateTimeZone($name); } catch (Exception) { $timezone = new DateTimeZone('UTC'); }
    }
    return $timezone;
}

/** Formatea un timestamp en la zona horaria del sitio. */
function microblog_format_time(string $format, int $timestamp): string
{
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(microblog_timezone())->format($format);
}

function microblog_valid_id(mixed $id): bool
{
    return is_string($id) && preg_match('/^[a-z0-9]{6,20}$/', $id) === 1;
}

function microblog_new_id(): string
{
    return bin2hex(random_bytes(5));
}

/** Descarta lo que no tenga el formato esperado, para que un archivo dañado no rompa la portada. */
function microblog_normalize(mixed $raw): ?array
{
    if (!is_array($raw) || !microblog_valid_id($raw['id'] ?? null)) return null;
    $parent = $raw['parent'] ?? null;
    if ($parent !== null && !microblog_valid_id($parent)) return null;
    $quote = null;
    if (is_array($raw['quote'] ?? null) && trim((string) ($raw['quote']['text'] ?? '')) !== '') {
        $quote = [
            'text' => (string) $raw['quote']['text'],
            'source' => (string) ($raw['quote']['source'] ?? ''),
            'url' => (string) ($raw['quote']['url'] ?? ''),
        ];
    }
    $links = [];
    foreach (is_array($raw['links'] ?? null) ? $raw['links'] : [] as $link) {
        if (is_array($link) && microblog_clean_url((string) ($link['url'] ?? '')) !== '') {
            $links[] = ['url' => (string) $link['url'], 'title' => (string) ($link['title'] ?? '')];
        }
    }
    $images = [];
    foreach (is_array($raw['images'] ?? null) ? $raw['images'] : [] as $image) {
        if (is_array($image) && preg_match('/^[a-z0-9-]+\.(webp|jpg)$/', (string) ($image['file'] ?? ''))) {
            $images[] = ['file' => (string) $image['file'], 'w' => (int) ($image['w'] ?? 0), 'h' => (int) ($image['h'] ?? 0), 'alt' => (string) ($image['alt'] ?? '')];
        }
    }
    $level = (int) ($raw['level'] ?? 0);
    return [
        'id' => $raw['id'],
        'parent' => $parent,
        'created' => (int) ($raw['created'] ?? 0),
        'updated' => (int) ($raw['updated'] ?? 0),
        'level' => $parent !== null || !isset(microblog_levels()[$level]) ? 0 : $level,
        'pinned' => $parent === null && !empty($raw['pinned']),
        'text' => (string) ($raw['text'] ?? ''),
        'quote' => $quote,
        'links' => $links,
        'images' => $images,
    ];
}

/** Lee todos los posts (sin bloqueo; para lectura pública). */
function microblog_load(): array
{
    $path = microblog_file();
    if (!is_file($path)) return [];
    $decoded = json_decode((string) @file_get_contents($path), true);
    if (!is_array($decoded)) return [];
    $posts = [];
    foreach (is_array($decoded['posts'] ?? null) ? $decoded['posts'] : [] as $raw) {
        $post = microblog_normalize($raw);
        if ($post !== null) $posts[$post['id']] = $post;
    }
    return $posts;
}

/** Un post programado (con fecha futura) aún no es público. */
function microblog_is_scheduled(array $post, ?int $now = null): bool
{
    return $post['created'] > ($now ?? time());
}

/** Posts que ve el público: sin los programados ni las respuestas de un post que todavía no se ha publicado. */
function microblog_visible(array $posts, ?int $now = null): array
{
    $now ??= time();
    return array_filter($posts, static fn(array $post): bool => !microblog_is_scheduled($post, $now)
        && ($post['parent'] === null || (isset($posts[$post['parent']]) && !microblog_is_scheduled($posts[$post['parent']], $now))));
}

function microblog_load_public(): array
{
    return microblog_visible(microblog_load());
}

/**
 * Lee, modifica y guarda los posts bajo un bloqueo exclusivo. `$change` recibe la lista por referencia y
 * devuelve el resultado de la operación.
 */
function microblog_mutate(callable $change): mixed
{
    $dir = microblog_data_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) throw new RuntimeException('No se pudo crear la carpeta de datos del microblog.');
    $lock = @fopen($dir . '/posts.lock', 'c+');
    if ($lock === false || !flock($lock, LOCK_EX)) throw new RuntimeException('El microblog está ocupado. Inténtalo de nuevo.');
    try {
        $posts = microblog_load();
        $result = $change($posts);
        $json = json_encode(['posts' => array_values($posts)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $temporary = @tempnam($dir, '.posts-');
        if ($json === false || $temporary === false || @file_put_contents($temporary, $json) !== strlen($json)) {
            if (is_string($temporary)) @unlink($temporary);
            throw new RuntimeException('No se pudo guardar el microblog.');
        }
        @chmod($temporary, 0640);
        if (!@rename($temporary, microblog_file())) {
            @unlink($temporary);
            throw new RuntimeException('No se pudo guardar el microblog.');
        }
        return $result;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

// =============================================================================
// VALIDACIÓN
// =============================================================================

/** Devuelve la URL si es http(s) con dominio y longitud razonable; si no, cadena vacía. */
function microblog_clean_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x20\x7F"<>]/', $url)) return '';
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) return '';
    return $url;
}

/** Recorta a `$length` caracteres UTF-8 sin exigir mbstring. */
function microblog_cut(string $value, int $length): string
{
    if (function_exists('mb_substr')) return mb_substr($value, 0, $length, 'UTF-8');
    return implode('', array_slice(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, $length));
}

function microblog_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/**
 * Valida los campos de texto de un formulario (text, quote_text, quote_source, quote_url, link_url[], link_title[], level).
 * Devuelve ['text', 'quote', 'links', 'level'] ya saneado. Lanza InvalidArgumentException con un mensaje para el usuario.
 */
function microblog_validate_fields(array $input, bool $allowLevel = true): array
{
    $text = str_replace(["\r\n", "\r"], "\n", trim((string) ($input['text'] ?? '')));
    if (!preg_match('//u', $text) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) throw new InvalidArgumentException('El texto contiene caracteres no válidos.');
    if (microblog_length($text) > MICROBLOG_MAX_TEXT) throw new InvalidArgumentException('El texto no puede superar ' . MICROBLOG_MAX_TEXT . ' caracteres.');
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    $quote = null;
    $quoteText = str_replace(["\r\n", "\r"], "\n", trim((string) ($input['quote_text'] ?? '')));
    if ($quoteText !== '') {
        $source = trim((string) ($input['quote_source'] ?? ''));
        $quoteUrl = trim((string) ($input['quote_url'] ?? ''));
        if (microblog_length($quoteText) > MICROBLOG_MAX_QUOTE) throw new InvalidArgumentException('La cita no puede superar ' . MICROBLOG_MAX_QUOTE . ' caracteres.');
        if (microblog_length($source) > 120 || preg_match('/[\x00-\x1F\x7F]/', $source)) throw new InvalidArgumentException('La fuente de la cita es demasiado larga o no es válida.');
        if ($quoteUrl !== '' && microblog_clean_url($quoteUrl) === '') throw new InvalidArgumentException('El enlace de la cita debe empezar por http:// o https://.');
        $quote = ['text' => $quoteText, 'source' => $source, 'url' => $quoteUrl];
    }

    $links = [];
    $urls = is_array($input['link_url'] ?? null) ? array_values($input['link_url']) : [];
    $titles = is_array($input['link_title'] ?? null) ? array_values($input['link_title']) : [];
    foreach ($urls as $i => $url) {
        $url = trim((string) $url);
        if ($url === '') continue;
        if (microblog_clean_url($url) === '') throw new InvalidArgumentException('Los enlaces deben empezar por http:// o https://.');
        $title = trim((string) ($titles[$i] ?? ''));
        if (microblog_length($title) > 120 || preg_match('/[\x00-\x1F\x7F]/', $title)) throw new InvalidArgumentException('El título de un enlace es demasiado largo o no es válido.');
        $links[] = ['url' => $url, 'title' => $title];
    }
    if (count($links) > MICROBLOG_MAX_LINKS) throw new InvalidArgumentException('Máximo ' . MICROBLOG_MAX_LINKS . ' enlaces por post.');

    $level = 0;
    if ($allowLevel) {
        $level = filter_var($input['level'] ?? 0, FILTER_VALIDATE_INT);
        if ($level === false || !isset(microblog_levels()[$level])) throw new InvalidArgumentException('Selecciona un nivel de destacado válido.');
    }
    // Programación (<input type="datetime-local">, hora del sitio). Vacío o ya pasado = publicar ahora (null).
    $publishAt = null;
    $rawPublish = trim((string) ($input['publish_at'] ?? ''));
    if ($rawPublish !== '') {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $rawPublish)
            ? DateTimeImmutable::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $rawPublish), microblog_timezone()) : false;
        if ($date === false) throw new InvalidArgumentException('La fecha de publicación no es válida.');
        $timestamp = $date->getTimestamp();
        if ($timestamp > time() + 366 * 86400) throw new InvalidArgumentException('La publicación no puede programarse a más de un año.');
        $publishAt = $timestamp > time() + 60 ? $timestamp : null;
    }
    return ['text' => $text, 'quote' => $quote, 'links' => $links, 'level' => $level,
        'pinned' => $allowLevel && !empty($input['pinned']), 'publish_at' => $publishAt];
}

/** Convierte $_FILES['images'] (múltiple) en una lista de archivos individuales, sin los campos vacíos. */
function microblog_collect_uploads(array $files): array
{
    $list = [];
    $field = $files['images'] ?? null;
    if (!is_array($field) || !isset($field['name'])) return $list;
    if (!is_array($field['name'])) {
        $field = array_map(static fn($v) => [$v], $field);
    }
    foreach (array_keys($field['name']) as $i) {
        $error = (int) ($field['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        $list[] = ['name' => (string) $field['name'][$i], 'tmp_name' => (string) $field['tmp_name'][$i], 'error' => $error, 'size' => (int) $field['size'][$i]];
    }
    return $list;
}

// =============================================================================
// IMÁGENES
// =============================================================================

/**
 * Procesa una imagen subida: comprueba el tipo real, corrige la orientación, reduce su lado mayor a 1500 px y la
 * guarda en /microblog/ sin metadatos (EXIF/GPS). Devuelve ['file', 'w', 'h', 'alt'].
 */
function microblog_store_image(array $upload, string $postId, string $alt = ''): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo recibir una de las imágenes. Revisa su tamaño e inténtalo otra vez.');
    $tmp = (string) $upload['tmp_name'];
    if (!is_uploaded_file($tmp) && getenv('MICROBLOG_ALLOW_LOCAL_FILES') !== '1') throw new RuntimeException('Subida no válida.');
    if (($upload['size'] ?? 0) > MICROBLOG_MAX_IMAGE_BYTES) throw new RuntimeException('Cada imagen puede pesar como máximo 15 MB.');
    if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('El servidor no tiene la extensión GD para procesar imágenes.');
    $info = @getimagesize($tmp);
    $type = is_array($info) ? $info[2] : 0;
    $source = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default => false,
    };
    if (!$source instanceof GdImage) throw new RuntimeException('Solo se admiten imágenes JPG, PNG o WebP.');

    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($source, 180, 0),
            6 => imagerotate($source, -90, 0),
            8 => imagerotate($source, 90, 0),
            default => $source,
        };
        if ($rotated instanceof GdImage) $source = $rotated;
    }
    $width = imagesx($source);
    $height = imagesy($source);
    // Solo se reduce (nunca se amplía): el lado mayor queda en 1500 px como máximo y la proporción se conserva.
    $scale = min(1.0, MICROBLOG_IMAGE_MAX_SIDE / max(1, $width, $height));
    $newWidth = (int) max(1, round($width * $scale));
    $newHeight = (int) max(1, round($height * $scale));
    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $dir = microblog_images_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new RuntimeException('No se pudo crear la carpeta de imágenes del microblog.');
    if (!function_exists('imagewebp')) throw new RuntimeException('El servidor no puede guardar imágenes WebP (falta soporte en GD).');
    $file = $postId . '-' . bin2hex(random_bytes(3)) . '.webp';
    $destination = $dir . '/' . $file;
    $ok = imagewebp($canvas, $destination, MICROBLOG_IMAGE_QUALITY);
    if (!$ok) throw new RuntimeException('No se pudo guardar una de las imágenes.');
    @chmod($destination, 0644);
    return ['file' => $file, 'w' => $newWidth, 'h' => $newHeight, 'alt' => $alt];
}

function microblog_delete_image_files(array $images): void
{
    foreach ($images as $image) {
        $file = (string) ($image['file'] ?? '');
        if (preg_match('/^[a-z0-9-]+\.(webp|jpg)$/', $file)) @unlink(microblog_images_dir() . '/' . $file);
    }
}

// =============================================================================
// OPERACIONES
// =============================================================================

/**
 * Guarda las imágenes subidas y devuelve sus datos. Si una falla, borra las ya guardadas y relanza el error.
 *
 * @param string[] $alts Textos alternativos en el mismo orden que $uploads.
 */
function microblog_store_uploads(array $uploads, string $postId, array $alts, int $room): array
{
    if (count($uploads) > $room) throw new InvalidArgumentException('Máximo ' . MICROBLOG_MAX_IMAGES . ' imágenes por post.');
    $stored = [];
    try {
        foreach ($uploads as $i => $upload) {
            $alt = trim((string) ($alts[$i] ?? ''));
            if (microblog_length($alt) > 200) throw new InvalidArgumentException('El texto alternativo de una imagen es demasiado largo.');
            $stored[] = microblog_store_image($upload, $postId, $alt);
        }
    } catch (Throwable $error) {
        microblog_delete_image_files($stored);
        throw $error;
    }
    return $stored;
}

/**
 * Crea un post o una respuesta (`$parent` = id del post raíz).
 *
 * @param array $input   Campos del formulario (ver microblog_validate_fields()).
 * @param array $uploads Archivos de microblog_collect_uploads().
 */
function microblog_create(array $input, array $uploads, ?string $parent = null): array
{
    $fields = microblog_validate_fields($input, $parent === null);
    $id = microblog_new_id();
    $images = microblog_store_uploads($uploads, $id, is_array($input['image_alt'] ?? null) ? array_values($input['image_alt']) : [], MICROBLOG_MAX_IMAGES);
    if ($fields['text'] === '' && !$fields['quote'] && !$fields['links'] && !$images) {
        throw new InvalidArgumentException('Escribe algo o añade una cita, un enlace o una imagen.');
    }
    $now = time();
    $post = ['id' => $id, 'parent' => $parent, 'created' => $fields['publish_at'] ?? $now, 'updated' => $now, 'level' => $fields['level'], 'pinned' => $fields['pinned'],
        'text' => $fields['text'], 'quote' => $fields['quote'], 'links' => $fields['links'], 'images' => $images];
    try {
        microblog_mutate(static function (array &$posts) use ($post, $parent): void {
            if ($parent !== null && (!isset($posts[$parent]) || $posts[$parent]['parent'] !== null)) {
                throw new InvalidArgumentException('El post al que respondes ya no existe.');
            }
            if ($post['pinned']) foreach ($posts as $key => $other) $posts[$key]['pinned'] = false; // solo un post fijado
            $posts[$post['id']] = $post;
        });
    } catch (Throwable $error) {
        microblog_delete_image_files($images);
        throw $error;
    }
    return $post;
}

/**
 * Edita un post: campos de texto, nivel, imágenes a quitar (`$removeFiles`) y nuevas imágenes.
 */
function microblog_update(string $id, array $input, array $uploads, array $removeFiles = []): array
{
    $current = microblog_load()[$id] ?? null;
    if ($current === null) throw new InvalidArgumentException('El post no existe.');
    $fields = microblog_validate_fields($input, $current['parent'] === null);
    $kept = array_values(array_filter($current['images'], static fn(array $image): bool => !in_array($image['file'], $removeFiles, true)));
    $removed = array_values(array_filter($current['images'], static fn(array $image): bool => in_array($image['file'], $removeFiles, true)));
    $added = microblog_store_uploads($uploads, $id, is_array($input['image_alt'] ?? null) ? array_values($input['image_alt']) : [], MICROBLOG_MAX_IMAGES - count($kept));
    $images = array_merge($kept, $added);
    if ($fields['text'] === '' && !$fields['quote'] && !$fields['links'] && !$images) {
        microblog_delete_image_files($added);
        throw new InvalidArgumentException('El post no puede quedar vacío.');
    }
    try {
        $post = microblog_mutate(static function (array &$posts) use ($id, $fields, $images, $input): array {
            if (!isset($posts[$id])) throw new InvalidArgumentException('El post no existe.');
            $changes = ['updated' => time(), 'level' => $fields['level'], 'pinned' => $fields['pinned'], 'text' => $fields['text'],
                'quote' => $fields['quote'], 'links' => $fields['links'], 'images' => $images];
            // La fecha solo se cambia mientras el post sigue programado (el formulario solo la ofrece entonces).
            if (array_key_exists('publish_at', $input) && microblog_is_scheduled($posts[$id])) $changes['created'] = $fields['publish_at'] ?? time();
            if ($changes['pinned']) foreach ($posts as $key => $other) $posts[$key]['pinned'] = false;
            $posts[$id] = array_merge($posts[$id], $changes);
            return $posts[$id];
        });
    } catch (Throwable $error) {
        microblog_delete_image_files($added);
        throw $error;
    }
    microblog_delete_image_files($removed);
    return $post;
}

/** Borra un post, sus respuestas y sus imágenes. Devuelve cuántos posts se borraron. */
function microblog_delete(string $id): int
{
    $images = [];
    $count = microblog_mutate(static function (array &$posts) use ($id, &$images): int {
        if (!isset($posts[$id])) return 0;
        $removed = 0;
        foreach ($posts as $key => $post) {
            if ($key === $id || $post['parent'] === $id) {
                array_push($images, ...$post['images']);
                unset($posts[$key]);
                $removed++;
            }
        }
        return $removed;
    });
    microblog_delete_image_files($images);
    return $count;
}

// =============================================================================
// CONSULTAS
// =============================================================================

function microblog_find(string $id): ?array
{
    return microblog_load()[$id] ?? null;
}

/** Posts raíz: el fijado primero y después del más reciente al más antiguo. */
function microblog_roots(array $posts): array
{
    // Se invierte antes de ordenar: usort es estable, así que con la misma hora gana el último creado.
    $roots = array_reverse(array_values(array_filter($posts, static fn(array $post): bool => $post['parent'] === null)));
    usort($roots, static fn(array $a, array $b): int => ($b['pinned'] <=> $a['pinned']) ?: ($b['created'] <=> $a['created']));
    return $roots;
}

/** Respuestas de un post raíz, en orden cronológico (el hilo se lee de arriba abajo). */
function microblog_replies(array $posts, string $rootId): array
{
    $replies = array_values(array_filter($posts, static fn(array $post): bool => $post['parent'] === $rootId));
    usort($replies, static fn(array $a, array $b): int => $a['created'] <=> $b['created']);
    return $replies;
}

/** Entradas para sitemap.xml: los posts raíz públicos, cada uno con su hilo. */
function microblog_sitemap_entries(string $siteUrl): array
{
    $posts = microblog_load_public();
    $entries = [];
    foreach (microblog_roots($posts) as $root) {
        $last = max(array_merge([$root['updated'], $root['created']], array_column(microblog_replies($posts, $root['id']), 'created')));
        $entries[] = ['loc' => $siteUrl . microblog_permalink($root), 'lastmod' => microblog_format_time('c', $last)];
    }
    return $entries;
}

/** URL permanente de un post (las respuestas llevan al hilo de su post raíz). */
function microblog_permalink(array $post, string $siteUrl = ''): string
{
    $root = $post['parent'] ?? $post['id'];
    return $siteUrl . '/microblog.php?id=' . $root . ($post['parent'] !== null ? '#mb-' . $post['id'] : '');
}

function microblog_embed_url(array $post, string $siteUrl = ''): string
{
    return $siteUrl . '/microblog.php?id=' . $post['id'] . '&embed=1';
}

// =============================================================================
// PINTADO
// =============================================================================

function microblog_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Fecha en español, p. ej. «12 oct 2026». */
function microblog_date(int $timestamp): string
{
    $months = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return (int) microblog_format_time('j', $timestamp) . ' ' . $months[(int) microblog_format_time('n', $timestamp) - 1] . ' ' . microblog_format_time('Y', $timestamp);
}

/**
 * Texto plano → HTML: párrafos, saltos de línea y enlaces.
 *
 * Enlaces: `[texto](https://…)` muestra «texto» como enlace; una URL suelta se convierte sola en enlace.
 * Todo lo demás se escapa, así que no se puede colar HTML.
 */
function microblog_format_text(string $text): string
{
    $autolink = static function (string $html): string {
        return preg_replace_callback('~https?://[^\s<]+~u', static function (array $m): string {
            $url = $m[0];
            $tail = '';
            // La puntuación final (y los paréntesis sin pareja) no forman parte del enlace.
            while ($url !== '') {
                if (preg_match('/(&quot;|&#039;|&amp;)$/', $url, $q)) $cut = strlen($q[1]);
                elseif (preg_match('/[.,;:!?\]]$/', $url)) $cut = 1;
                elseif (str_ends_with($url, ')') && substr_count($url, ')') > substr_count($url, '(')) $cut = 1;
                else break;
                $tail = substr($url, -$cut) . $tail;
                $url = substr($url, 0, -$cut);
            }
            $plain = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            if (microblog_clean_url($plain) === '') return $m[0];
            $label = preg_replace('~^https?://(www\.)?~i', '', $plain) ?? $plain;
            if (microblog_length($label) > 42) $label = microblog_cut($label, 41) . '…';
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . microblog_e($label) . '</a>' . $tail;
        }, $html) ?? $html;
    };

    $paragraphs = [];
    foreach (preg_split("/\n{2,}/", trim($text)) ?: [] as $paragraph) {
        if (trim($paragraph) === '') continue;
        $html = microblog_e($paragraph);
        // Primero los enlaces con texto [texto](url); el resto del párrafo se autoenlaza.
        $parts = preg_split('/(\[[^\]\n]{1,200}\]\(https?:\/\/[^\s)]+\))/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $out = '';
        foreach ($parts as $part) {
            if (preg_match('/^\[([^\]\n]{1,200})\]\((https?:\/\/[^\s)]+)\)$/u', $part, $m) && microblog_clean_url(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')) !== '') {
                $out .= '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer nofollow">' . $m[1] . '</a>';
            } else {
                $out .= $autolink($part);
            }
        }
        $paragraphs[] = '<p>' . nl2br($out, false) . '</p>';
    }
    return implode('', $paragraphs);
}

function microblog_host(string $url): string
{
    return preg_replace('~^www\.~i', '', (string) parse_url($url, PHP_URL_HOST)) ?? '';
}

function microblog_icon(string $name): string
{
    $paths = [
        'reply' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
        'share' => '<path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M12 15V3M8 7l4-4 4 4"/>',
        'embed' => '<path d="M8 8l-5 4 5 4M16 8l5 4-5 4M14 5l-4 14"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'star' => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'pin' => '<path d="M12 17v5M8 3h8l-1 6 3 4H6l3-4z"/>',
        'spark' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M18 6l-2.5 2.5M8.5 15.5L6 18"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Cuerpo de un post (cita, texto, enlaces, imágenes) sin la cabecera ni las acciones.
 */
function microblog_render_body(array $post): string
{
    $html = '';
    if ($post['text'] !== '') $html .= '<div class="mb-text">' . microblog_format_text($post['text']) . '</div>';
    if ($post['quote'] !== null) {
        $quote = $post['quote'];
        $source = $quote['source'] !== '' ? microblog_e($quote['source']) : ($quote['url'] !== '' ? microblog_e(microblog_host($quote['url'])) : '');
        if ($source !== '' && microblog_clean_url($quote['url']) !== '') {
            $source = '<a href="' . microblog_e($quote['url']) . '" target="_blank" rel="noopener noreferrer nofollow">' . $source . '</a>';
        }
        $html .= '<blockquote class="mb-quote"><span class="mb-quote__mark" aria-hidden="true">“</span>'
            . microblog_format_text($quote['text'])
            . ($source !== '' ? '<footer>— <cite>' . $source . '</cite></footer>' : '') . '</blockquote>';
    }
    if ($post['images']) {
        $count = count($post['images']);
        $html .= '<div class="mb-images mb-images--' . $count . '">';
        foreach ($post['images'] as $image) {
            $size = $image['w'] > 0 && $image['h'] > 0 ? ' width="' . $image['w'] . '" height="' . $image['h'] . '"' : '';
            $url = microblog_image_url($image['file']);
            $html .= '<a class="mb-image" href="' . microblog_e($url) . '" data-mb-lightbox><img src="' . microblog_e($url) . '" alt="' . microblog_e($image['alt']) . '"' . $size . ' loading="lazy" decoding="async"></a>';
        }
        $html .= '</div>';
    }
    foreach ($post['links'] as $link) {
        $host = microblog_host($link['url']);
        $title = $link['title'] !== '' ? $link['title'] : $host;
        $html .= '<a class="mb-link" href="' . microblog_e($link['url']) . '" target="_blank" rel="noopener noreferrer nofollow">'
            . '<span class="mb-link__icon">' . microblog_icon('link') . '</span>'
            . '<span class="mb-link__text"><strong>' . microblog_e($title) . '</strong><small>' . microblog_e($host) . '</small></span></a>';
    }
    return $html;
}

/**
 * Una entrada (post o respuesta) del hilo: avatar, cabecera, cuerpo y acciones.
 *
 * Opciones: 'actions' (bool, por defecto true), 'last' (bool: sin línea de hilo debajo).
 */
function microblog_render_entry(array $post, array $options = []): string
{
    $actions = $options['actions'] ?? true;
    $isReply = $post['parent'] !== null;
    $name = site_text('text_30170303c506cf6c');
    $handle = site_text('text_4577bab0d9627af1');
    $permalink = microblog_permalink($post);
    $label = null;
    if (!$isReply && $post['level'] === 1) $label = ['star', 'Destacado'];
    if (!$isReply && $post['level'] === 2) $label = ['spark', 'Súper destacado'];

    $html = '<div class="mb-entry' . ($isReply ? ' mb-entry--reply' : '') . (!empty($options['last']) ? ' is-last' : '') . '" id="mb-' . $post['id'] . '">'
        . '<div class="mb-rail"><img class="mb-avatar" src="' . microblog_e(site_media_url('profile_image')) . '" alt="" width="44" height="44" loading="lazy"></div>'
        . '<div class="mb-main"><header class="mb-head"><strong class="mb-name">' . microblog_e($name) . '</strong>'
        . '<span class="mb-handle">' . microblog_e($handle) . '</span>'
        . '<a class="mb-date" href="' . microblog_e($permalink) . '"><time datetime="' . microblog_format_time('c', $post['created']) . '">' . microblog_e(microblog_date($post['created'])) . '</time></a>'
        . ($label || $post['pinned'] ? '<span class="mb-flags">'
            . ($post['pinned'] ? '<span class="mb-pin">' . microblog_icon('pin') . 'Fijado</span>' : '')
            . ($label ? '<span class="mb-badge mb-badge--' . $post['level'] . '">' . microblog_icon($label[0]) . microblog_e($label[1]) . '</span>' : '') . '</span>' : '')
        . '</header>'
        . '<div class="mb-body">' . microblog_render_body($post) . '</div>';

    if ($actions) {
        $replyCount = (int) ($options['replyCount'] ?? 0);
        $html .= '<footer class="mb-actions">'
            . '<button class="mb-action mb-heart" type="button" data-mb-heart="mb-' . $post['id'] . '" aria-pressed="false" aria-label="Dar un corazón">♡ <span data-mb-heart-count>0</span></button>'
            . (!$isReply ? '<a class="mb-action" href="' . microblog_e($permalink) . '" aria-label="Ver el hilo">' . microblog_icon('reply') . '<span>' . $replyCount . '</span></a>' : '')
            . '<button class="mb-action" type="button" data-mb-share data-url="' . microblog_e($permalink) . '" aria-label="Compartir">' . microblog_icon('share') . '</button>'
            . '<button class="mb-action" type="button" data-mb-embed data-id="' . $post['id'] . '" aria-label="Incrustar">' . microblog_icon('embed') . '</button>'
            . '</footer>';
    }
    return $html . '</div></div>';
}

/**
 * Un hilo completo: el post raíz y sus respuestas, en una sola tarjeta.
 *
 * Opciones: 'replyLimit' (int|null: máximo de respuestas visibles; null = todas), 'actions' (bool).
 */
function microblog_render_thread(array $root, array $replies, array $options = []): string
{
    $limit = $options['replyLimit'] ?? null;
    $shown = $limit === null ? $replies : array_slice($replies, 0, $limit);
    $hidden = count($replies) - count($shown);
    $entryOptions = ['actions' => $options['actions'] ?? true];
    $html = '<article class="mb-thread mb-thread--l' . $root['level'] . '" data-mb-thread="' . $root['id'] . '">'
        . ($root['level'] === 2 ? '<span class="mb-thread__shine" aria-hidden="true"></span>' : '')
        . microblog_render_entry($root, $entryOptions + ['replyCount' => count($replies), 'last' => !$shown && $hidden === 0]);
    foreach ($shown as $i => $reply) {
        $html .= microblog_render_entry($reply, $entryOptions + ['last' => $hidden === 0 && $i === count($shown) - 1]);
    }
    if ($hidden > 0) {
        $html .= '<a class="mb-more-replies" href="' . microblog_e(microblog_permalink($root)) . '">Ver hilo completo · ' . $hidden . ' ' . ($hidden === 1 ? 'respuesta más' : 'respuestas más') . '</a>';
    }
    return $html . '</article>';
}

/** HTML de una página de la portada: los hilos desde `$offset` y el siguiente desplazamiento (o null). */
function microblog_render_feed_page(array $posts, int $offset, int $size = MICROBLOG_PAGE_SIZE): array
{
    $roots = microblog_roots($posts);
    $slice = array_slice($roots, max(0, $offset), $size);
    $html = '';
    foreach ($slice as $root) {
        $html .= microblog_render_thread($root, microblog_replies($posts, $root['id']), ['replyLimit' => MICROBLOG_FEED_REPLIES]);
    }
    $next = $offset + $size < count($roots) ? $offset + $size : null;
    return ['html' => $html, 'next' => $next, 'total' => count($roots)];
}

/** Resumen en una línea para el SEO y el panel. */
function microblog_excerpt(array $post, int $length = 140): string
{
    $source = $post['text'] !== '' ? $post['text'] : ($post['quote']['text'] ?? ($post['links'][0]['title'] ?? ($post['links'][0]['url'] ?? ($post['images'] ? 'Imagen' : ''))));
    $source = trim((string) preg_replace('/\s+/u', ' ', $source));
    return microblog_length($source) > $length ? rtrim(microblog_cut($source, $length - 1)) . '…' : $source;
}
