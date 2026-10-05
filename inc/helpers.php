<?php
/**
 * Helpers para leer metadatos opcionales (.txt sidecar) y ordenar la galería.
 *
 * Formato del .txt sidecar (todos los campos son opcionales):
 *
 *   Título de la foto
 *   ---
 *   Descripción larga de la foto, puede tener varias líneas.
 *
 * Si no hay archivo .txt, la foto aparece sin texto.
 */

declare(strict_types=1);
require_once __DIR__ . '/site-settings.php';

/**
 * Genera un slug URL-friendly desde un título.
 * Solo se llama cuando la IA genera el título (nunca desde fallback de nombre de archivo).
 * El slug se guarda en el .txt y nunca cambia aunque se edite el título.
 */
function generate_slug(string $title): string
{
    $map = [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o',
        'ç'=>'c','ß'=>'ss',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n',
        'À'=>'a','È'=>'e','Ì'=>'i','Ò'=>'o','Ù'=>'u',
    ];
    $slug = strtr($title, $map);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');
    return substr($slug, 0, 80);
}

function read_sidecar(string $txtPath): array
{
    $out = ['title' => '', 'description' => '', 'category' => '', 'latitude' => 0, 'longitude' => 0, 'slug' => '', 'featured' => false, 'draft' => false, 'order' => null];

    if (!is_file($txtPath)) return $out;

    $raw = file_get_contents($txtPath);
    if ($raw === false || trim($raw) === '') return $out;

    if (preg_match('/^# Categoría:\s*(.+?)$/m', $raw, $m)) {
        $out['category'] = trim($m[1]);
    }

    if (preg_match('/^# Coordenadas:\s*([\d\.\-]+)\s*,\s*([\d\.\-]+)/m', $raw, $m)) {
        $out['latitude']  = (float) $m[1];
        $out['longitude'] = (float) $m[2];
    }

    // Slug permanente: solo existe si lo generó la IA (nunca el fallback de nombre)
    if (preg_match('/^# Slug:\s*([a-z0-9-]+)$/m', $raw, $m)) {
        $out['slug'] = trim($m[1]);
    }
    $out['featured'] = (bool) preg_match('/^# Destacada:\s*1\s*$/m', $raw);
    $out['draft'] = (bool) preg_match('/^# Borrador:\s*1\s*$/m', $raw);
    if (preg_match('/^# Orden:\s*(-?\d+)\s*$/m', $raw, $m)) $out['order'] = (int) $m[1];

    // Eliminar líneas de comentario para título/descripción
    $raw = preg_replace('/^\s*#.*$/m', '', $raw) ?? $raw;
    $raw = trim($raw);

    $parts = preg_split('/^\s*---\s*$/m', $raw, 2);

    if (count($parts) === 2) {
        $out['title']       = trim($parts[0]);
        $out['description'] = trim($parts[1]);
    } else {
        $out['title'] = trim($parts[0]);
    }

    return $out;
}

function sort_by_upload_desc(array $items): array
{
    usort($items, fn($a, $b) => ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));
    return $items;
}

function year_range(array $items): string
{
    if (empty($items)) return (string) date('Y');

    $years = [];
    foreach ($items as $it) {
        $t = $it['mtime'] ?? 0;
        if ($t > 0) $years[] = (int) date('Y', $t);
    }

    if (empty($years)) return (string) date('Y');

    $min = min($years);
    $max = max($years);
    return $min === $max ? (string) $max : $min . '–' . $max;
}

function enrich_with_sidecar(array $items): array
{
    foreach ($items as &$it) {
        $sidecar = read_sidecar($it['sidecar'] ?? '');
        $it['title']       = $sidecar['title'];
        $it['description'] = $sidecar['description'];
        $it['category']    = $sidecar['category'];
        $it['latitude']    = $sidecar['latitude'];
        $it['longitude']   = $sidecar['longitude'];
        $it['slug']        = $sidecar['slug'];
        $it['featured']    = !empty($sidecar['featured']);
        $it['draft']       = !empty($sidecar['draft']);
        $it['order']       = $sidecar['order'];
    }
    unset($it);
    return $items;
}

function safe(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Codifica una ruta para usarla en URL (espacios → %20, acentos, etc.)
 * preservando las barras "/". Imprescindible en srcset, donde un espacio
 * separa la URL del descriptor.
 */
function url_path(string $path): string
{
    $path = ltrim($path, '/');
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

/**
 * Versión de un asset basada en el HASH de su contenido (no en la fecha).
 * Así el navegador descarga la versión nueva en cuanto cambia el archivo,
 * aunque el FTP conserve la fecha de modificación (problema típico).
 */
function asset_ver(string $absPath): string
{
    if (!is_file($absPath)) return (string) time();
    return substr(hash_file('crc32b', $absPath) ?: (string) filemtime($absPath), 0, 8);
}

/**
 * Identificador único del despliegue, generado por GitHub Actions.
 * En desarrollo sin marcador, deriva la versión de los assets y páginas.
 */
function app_version(): string
{
    static $version = null;
    if ($version !== null) return $version;

    $releaseFile = dirname(__DIR__) . '/version.json';
    if (is_file($releaseFile)) {
        $release = json_decode((string) @file_get_contents($releaseFile), true);
        $candidate = is_array($release) ? (string) ($release['version'] ?? '') : '';
        if (preg_match('/^[a-f0-9]{7,40}$/i', $candidate)) {
            $version = strtolower($candidate);
            return $version;
        }
    }

    $root = dirname(__DIR__);
    $files = ['index.php', 'admin.php', 'service-worker.js', 'manifest.json',
        'assets/css/style.css', 'assets/css/photo-editor.css',
        'assets/js/main.js', 'assets/js/pwa.js', 'assets/js/photo-editor.js', 'assets/js/photo-editor-presets.js', 'assets/js/photo-filter-engine.js',
        'inc/site-settings.php', 'inc/site-texts.php', 'assets/css/site-design.css', 'assets/js/site-texts.js',
        'inc/photo-navigation.php', 'assets/css/photo-navigation.css'];
    $fingerprints = [];
    foreach ($files as $file) {
        $path = $root . '/' . $file;
        $fingerprints[] = is_file($path) ? (hash_file('sha256', $path) ?: '') : '';
    }
    $version = substr(hash('sha256', implode('|', $fingerprints)), 0, 16);
    return $version;
}

/** Devuelve el SVG de un icono de red social / acción para compartir. */
function social_profile_icon(string $network): string
{
    $icons = [
        'facebook' => '<path d="M13.5 22v-9h3l.5-3.5h-3.5V7.3c0-1 .3-1.8 1.8-1.8H17V2.4c-.7-.1-1.6-.2-2.7-.2-2.7 0-4.6 1.7-4.6 4.7v2.6H6.6V13h3.1v9h3.8z"/>',
        'x' => '<path d="M18.9 2H22l-6.8 7.8L23.2 22H17l-4.9-6.4L6.5 22H3.4l7.2-8.3L2.9 2h6.3l4.4 5.8L18.9 2zm-1.1 17.9h1.7L8.3 4H6.5l11.3 15.9z"/>',
        'youtube' => '<path d="M23 12s0-3.5-.4-5.2a3 3 0 0 0-2.1-2.1C18.7 4.2 12 4.2 12 4.2s-6.7 0-8.5.5a3 3 0 0 0-2.1 2.1C1 8.5 1 12 1 12s0 3.5.4 5.2a3 3 0 0 0 2.1 2.1c1.8.5 8.5.5 8.5.5s6.7 0 8.5-.5a3 3 0 0 0 2.1-2.1C23 15.5 23 12 23 12z"/><path fill="var(--bg)" d="m9.7 15.4 5.8-3.4-5.8-3.4z"/>',
        'tiktok' => '<path d="M16 2h3a6 6 0 0 0 4 4v3a9 9 0 0 1-4-1v7.5a6.5 6.5 0 1 1-5.5-6.4v3.2a3.5 3.5 0 1 0 2.5 3.3V2z"/>',
        'flickr' => '<circle cx="7" cy="12" r="4"/><circle cx="17" cy="12" r="4"/>',
        'linkedin' => '<path d="M5.5 8H2v14h3.5V8zM3.8 2A2.1 2.1 0 1 0 3.8 6.2 2.1 2.1 0 0 0 3.8 2zM22 14.1c0-4.2-2.2-6.2-5.2-6.2-2.4 0-3.5 1.3-4.1 2.2V8H9.2v14h3.5v-7c0-1.8.3-3.6 2.6-3.6s2.3 2.1 2.3 3.7V22H22v-7.9z"/>',
        'pinterest' => '<path d="M12 2a10 10 0 0 0-3.6 19.3c-.1-1.6 0-3.5.4-5.2l1.3-5.4s-.3-.7-.3-1.8c0-1.7 1-3 2.2-3 1 0 1.6.8 1.6 1.8 0 1.1-.7 2.7-1 4.2-.6 2.5 1.2 4.5 3.6 4.5 4.3 0 6.8-5.2 6.8-11.3C23 8.8 18.2 2 12 2z"/>',
        '500px' => '<text x="12" y="16" text-anchor="middle" font-size="9" font-family="Arial" font-weight="700">500px</text>',
        'bluesky' => '<path d="M5 4c2.8 2.1 5.8 6.4 7 8.7C13.2 10.4 16.2 6.1 19 4c2-1.5 5.2-2.7 5 1-.2 1.8-1 6.1-1.5 8.2-.7 2.8-3 3.5-5.1 3 3.7.6 4.6 2.7 2.6 4.8-3.8 4-5.5-1-6-2.3-.1-.3-.2-.5-.3-.7-.1.2-.2.4-.3.7-.5 1.3-2.2 6.3-6 2.3-2-2.1-1.1-4.2 2.6-4.8-2.1.5-4.4-.2-5.1-3C4.4 11.1 3.6 6.8 3.4 5 3.2 1.3 6.4 2.5 8.4 4z"/>',
        'mastodon' => '<path d="M21.6 8.2c0-4.5-3-5.8-3-5.8C17 1.7 14.3 1.4 12 1.4h-.1c-2.3 0-5 .3-6.6 1C5.3 2.4 2.4 3.7 2.4 8.2c0 1 .1 2.2.1 3.5.2 4.4 1.6 8.8 5.7 9.9 1.9.5 3.5.6 4.8.5 2.4-.1 3.7-.8 3.7-.8l-.1-2.7s-1.7.5-3.6.5c-1.9-.1-3.9-.2-4.2-2.5a4 4 0 0 1 0-.7s1.9.5 4.3.6c1.5.1 3-.1 4.5-.3 2.8-.4 5.2-2.5 5.5-4.5.5-3 .5-7.4.5-7.4z"/>'
    ];
    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . ($icons[$network] ?? '') . '</svg>';
}

function share_icon(string $net): string
{
    switch ($net) {
        case 'wa':
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>';
        case 'x':
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z"/></svg>';
        case 'tg':
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>';
        case 'fb':
            return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z"/></svg>';
        case 'share':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/></svg>';
        case 'copy':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 13.4a4 4 0 0 0 5.66 0l2.83-2.83a4 4 0 0 0-5.66-5.66l-1.41 1.42"/><path d="M13.4 10.6a4 4 0 0 0-5.66 0l-2.83 2.83a4 4 0 0 0 5.66 5.66l1.41-1.42"/></svg>';
    }
    return '';
}

/**
 * Botones de compartir la URL de una foto.
 * - Modo estático ($dynamic=false): hrefs reales (para la página /foto/slug).
 * - Modo dinámico ($dynamic=true): hrefs "#" + data-share, que el JS rellena
 *   por foto en el lightbox.
 */
function share_buttons(string $url, string $title, bool $dynamic = false): string
{
    $nets = ['wa' => 'WhatsApp', 'x' => 'X', 'tg' => 'Telegram', 'fb' => 'Facebook'];

    $hrefs = [];
    if (!$dynamic) {
        $u = rawurlencode($url);
        $t = rawurlencode($title . site_text('text_0ad82954c86eec7c'));
        $hrefs = [
            'wa' => "https://wa.me/?text={$t}%20{$u}",
            'x'  => "https://twitter.com/intent/tweet?text={$t}&url={$u}",
            'tg' => "https://t.me/share/url?url={$u}&text={$t}",
            'fb' => "https://www.facebook.com/sharer/sharer.php?u={$u}",
        ];
    }

    $html  = '<div class="share' . ($dynamic ? ' share--js' : '') . '">';
    $html .= '<span class="share__label">' . site_text_html('text_d5e9eac00ad801cf') . '</span><div class="share__btns">';
    $html .= '<button class="share__btn share__btn--native" type="button" ' . ($dynamic ? 'data-native-share-current' : 'data-native-share data-share-url="' . safe($url) . '" data-share-title="' . safe($title) . '"') . ' aria-label="Compartir con el dispositivo">' . share_icon('share') . '</button>';
    foreach ($nets as $net => $label) {
        if ($dynamic) {
            $html .= '<a class="share__btn share__btn--' . $net . '" href="#" data-share="' . $net . '" aria-label="' . site_text_html('text_a28cf8674fc26c2a') . safe($label) . '">' . share_icon($net) . '</a>';
        } else {
            $html .= '<a class="share__btn share__btn--' . $net . '" href="' . safe($hrefs[$net]) . '" target="_blank" rel="noopener" aria-label="' . site_text_html('text_a28cf8674fc26c2a') . safe($label) . '">' . share_icon($net) . '</a>';
        }
    }
    $html .= '<button class="share__btn share__btn--copy" type="button" data-copy-url="' . ($dynamic ? '' : safe($url)) . '" aria-label="' . site_text_html('text_db164690a5469f43') . '">' . share_icon('copy') . '</button>';
    $html .= '</div></div>';
    return $html;
}

/**
 * Firma ligera de las fotos y sus metadatos. Se usa para avisar a las
 * páginas abiertas cuando llega una foto nueva, sin procesar imágenes.
 */
function gallery_content_version(string $baseDir): string
{
    $settingsFingerprint = is_file(site_settings_path()) ? (hash_file('sha256', site_settings_path()) ?: '') : '';
    $imageDir = $baseDir . '/img';
    $files = is_dir($imageDir) ? scandir($imageDir) : false;
    if ($files === false) return hash('sha256', $settingsFingerprint);

    $parts = [$settingsFingerprint];
    foreach ($files as $file) {
        if (str_ends_with(strtolower($file), '.edit.jpg')) continue;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png'], true)) continue;

        $original = $imageDir . '/' . $file;
        if (!is_file($original)) continue;
        $base = pathinfo($file, PATHINFO_FILENAME);
        $parts[] = $file . ':' . (@filemtime($original) ?: 0) . ':' . (@filesize($original) ?: 0);
        foreach ([$imageDir . '/' . $base . '.edit.jpg', $imageDir . '/' . $base . '.txt'] as $related) {
            if (is_file($related)) {
                $parts[] = $related . ':' . (@filemtime($related) ?: 0) . ':' . (@filesize($related) ?: 0);
            }
        }
    }

    return hash('sha256', implode("\n", $parts));
}

/** URL pública con versión del archivo para invalidar la caché tras una reedición. */
function photo_asset_url(string $relativePath, string $baseDir): string
{
    $path = $baseDir . '/' . ltrim($relativePath, '/');
    $version = is_file($path)
        ? (string) ((int) (filemtime($path) ?: 0)) . '-' . (string) ((int) (filesize($path) ?: 0))
        : '0';
    return '/' . url_path($relativePath) . '?v=' . $version;
}

