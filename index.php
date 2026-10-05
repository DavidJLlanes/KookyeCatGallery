<?php

declare(strict_types=1);

// La página contiene datos dinámicos y acciones privadas: nunca servir HTML guardado.
// Los assets (CSS/JS) conservan su versión en la URL y pueden cachearse aparte.
header('Cache-Control: private, no-store, max-age=0');

require __DIR__ . '/inc/processor.php';
require __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/site-settings.php';
require __DIR__ . '/inc/sitemap.php';
require __DIR__ . '/inc/photo-navigation.php';

$appVersion = app_version();

$siteUrl  = rtrim(getenv('GALLERY_PUBLIC_URL') ?: 'https://example.com', '/');
$siteName = site_text('text_52179dc42df7efe5');
$author   = site_text('text_30170303c506cf6c');
$siteSettings = site_settings_load();


$processor = new ImageProcessor(__DIR__);
$items     = $processor->processAll();

$items = enrich_with_sidecar($items);
$items = array_values(array_filter($items, static fn(array $item): bool => empty($item['draft'])));

// PRIORIDAD de categoría y coordenadas (de más a menos importante):
//   1) Lo que edites A MANO en el .txt:     # Categoría: / # Coordenadas:
//   2) Metadatos en el NOMBRE del archivo:  archivo(Categoría)(lat,lng).jpg
//   3) Autodetección por título/descripción (más abajo)
// EL .TXT SIEMPRE MANDA: si lo editas a mano, eso se respeta para siempre.
foreach ($items as &$item) {
    if (empty($item['category']) && !empty($item['filename_category'])) {
        $item['category'] = $item['filename_category'];
    }
    if (empty($item['latitude']) && empty($item['longitude'])
        && !empty($item['filename_lat']) && !empty($item['filename_lng'])) {
        $item['latitude']  = (float) $item['filename_lat'];
        $item['longitude'] = (float) $item['filename_lng'];
    }
}
unset($item);

// Siembra en el .txt los metadatos del NOMBRE del archivo, pero SOLO si esa
// línea aún no existe. Así nunca se pisa lo que edites a mano.
foreach ($items as $it) {
    if (empty($it['filename_category']) && (empty($it['filename_lat']) || empty($it['filename_lng']))) {
        continue;
    }
    $sidecar = $it['sidecar'] ?? '';
    if (!$sidecar || !is_file($sidecar)) continue;

    $content = file_get_contents($sidecar);
    if ($content === false) continue;

    $changed = false;
    if (!empty($it['filename_category']) && !preg_match('/^# Categoría:/m', $content)) {
        $content = rtrim($content) . "\n# Categoría: " . $it['filename_category'] . "\n";
        $changed = true;
    }
    if (!empty($it['filename_lat']) && !empty($it['filename_lng']) && !preg_match('/^# Coordenadas:/m', $content)) {
        $content = rtrim($content) . "\n# Coordenadas: " . $it['filename_lat'] . "," . $it['filename_lng'] . "\n";
        $changed = true;
    }
    if ($changed) {
        @file_put_contents($sidecar, $content);
    }
}

$items = sort_by_upload_desc($items);
if (array_filter($items, static fn(array $item): bool => $item['order'] !== null)) {
    usort($items, static function(array $a, array $b): int {
        $ao = $a['order']; $bo = $b['order'];
        if ($ao === null && $bo === null) return ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0);
        if ($ao === null) return 1;
        if ($bo === null) return -1;
        return $ao <=> $bo;
    });
}

$totalFotos = count($items);
$yearLabel  = year_range($items);
$featuredItems = array_values(array_filter($items, static fn(array $item): bool => !empty($item['featured'])));
$heroItem = $featuredItems[0] ?? ($items[0] ?? null);

// Detecta categorías automáticamente del título + descripción
$categoryKeywords = [
    'Gatos' => ['gato', 'gata', 'felino', 'cat'],
    'Gatitos' => ['gatito', 'cachorro', 'kitten'],
    'Retratos' => ['retrato', 'mirada', 'primer plano'],
    'Juego' => ['juego', 'juguete', 'correr', 'saltar'],
    'Descanso' => ['dormido', 'durmiendo', 'siesta', 'descanso'],
    'Exterior' => ['jardín', 'parque', 'exterior', 'ventana'],
    'Blanco y negro' => ['blanco y negro', 'monocromo'],
];

foreach ($items as &$item) {
    $combined = strtolower(($item['title'] ?? '') . ' ' . ($item['description'] ?? ''));

    // Si NO hay categoría manual (en el .txt o nombre), detecta automáticamente
    if (empty($item['category'])) {
        $item['category'] = 'Otras';
        foreach ($categoryKeywords as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($combined, strtolower($kw)) !== false) {
                    $item['category'] = $cat;
                    break 2;
                }
            }
        }
    }

}
unset($item);

$categories = array_unique(array_filter(array_column($items, 'category')));
sort($categories);

// Todas las fotos van al DOM; la paginación la gestiona completamente JS
$galleryItems = $heroItem ? $items : [];

// Genera slug para fotos con título pero sin # Slug: en el .txt
// Conserva los títulos editados manualmente.
foreach ($items as &$item) {
    if ($item['slug'] === '' && $item['title'] !== '' && !empty($item['sidecar']) && is_file($item['sidecar'])) {
        $raw = file_get_contents($item['sidecar']) ?: '';
        if (!str_contains($raw, '# auto-filename')) {
            $slug = generate_slug($item['title']);
            $item['slug'] = $slug;
            @file_put_contents($item['sidecar'], rtrim($raw) . "\n# Slug: {$slug}\n");
        }
    }
}
unset($item);

$galleryVersion = gallery_content_version(__DIR__);

// Detecta si se accede a /foto/slug directamente (para meta tags y auto-abrir lightbox)
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$fotoSlug   = null;
$fotoItem   = null;
if (preg_match('~^/foto/([a-z0-9-]+)~', $requestUri, $m)) {
    $fotoSlug = $m[1];
    foreach ($items as $it) {
        if (($it['slug'] ?? '') === $fotoSlug) {
            $fotoItem = $it;
            break;
        }
    }
}

// La ficha pública solo muestra el acceso de edición si existe una sesión privada válida.
$photoNeighbors = $fotoItem !== null ? photo_neighbors($items, (string) $fotoItem['slug']) : ['previous' => null, 'next' => null];

$isPhotoAdmin = false;
if ($fotoItem !== null && !empty($_COOKIE['kookye_gallery_admin']) && is_string($_COOKIE['kookye_gallery_admin'])) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_name('kookye_gallery_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (session_start(['read_and_close' => true])) {
        $isPhotoAdmin = !empty($_SESSION['upload_authenticated']);
    }
}
if ($fotoItem !== null) header('Vary: Cookie');
// session_start() puede modificar la cabecera de caché; reafirmarla antes de emitir HTML.
header('Cache-Control: private, no-store, max-age=0');

// Regenera sitemap.xml si hay cambios (con TODAS las fotos)
generate_sitemap(__DIR__, $siteUrl, $items);

// SEO: meta title y description (se sobreescriben si es URL de foto concreta)
$pageTitle    = site_text('text_1a0ef9e63d20942b');
$pageDesc     = str_replace('{count}', (string) $totalFotos, site_text('text_506b817f8bd1888e'));
$pageKeywords = str_replace('{count}', (string) $totalFotos, site_text('text_570bd4204e3d077b'));
$pageCanonical = $siteUrl . '/';
$pageOgImage   = $siteUrl . '/assets/img/kookye-cat-gallery-og.png';
$pageOgWidth   = 1730;
$pageOgHeight  = 909;

if ($fotoItem !== null) {
    $pageTitle    = safe($fotoItem['title']) . site_text('text_0ad82954c86eec7c');
    $pageDesc     = $fotoItem['description'] ?: $fotoItem['title'] . site_text('text_5f894de21770ce4f') . $author;
    $pageCanonical = $siteUrl . '/foto/' . $fotoItem['slug'];
    $pageOgImage   = $siteUrl . photo_asset_url($fotoItem['desktop'], __DIR__);
    $pageOgWidth   = (int) ($fotoItem['desktop_w'] ?? 1200);
    $pageOgHeight  = (int) ($fotoItem['desktop_h'] ?? 630);
}
?>
<!DOCTYPE html>
<html lang="es-ES">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= site_text_html('text_52179dc42df7efe5') ?>">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="app-version" content="<?= safe($appVersion) ?>">
    <meta name="gallery-version" content="<?= safe($galleryVersion) ?>">
    <link rel="manifest" href="/manifest.json?v=<?= safe($appVersion) ?>">

    <title><?= safe($pageTitle) ?></title>
    <meta name="description" content="<?= safe($pageDesc) ?>">
    <meta name="keywords" content="<?= safe($pageKeywords) ?>">
    <meta name="author" content="<?= safe($author) ?>">
    <link rel="canonical" href="<?= safe($pageCanonical) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">

    <!-- Open Graph -->
    <meta property="og:type" content="<?= $fotoItem ? 'article' : 'website' ?>">
    <meta property="og:locale" content="es_ES">
    <meta property="og:site_name" content="<?= safe($siteName) ?>">
    <meta property="og:title" content="<?= safe($pageTitle) ?>">
    <meta property="og:description" content="<?= safe($pageDesc) ?>">
    <meta property="og:url" content="<?= safe($pageCanonical) ?>">
    <?php if ($pageOgImage): ?>
    <meta property="og:image" content="<?= safe($pageOgImage) ?>">
    <meta property="og:image:width" content="<?= $pageOgWidth ?>">
    <meta property="og:image:height" content="<?= $pageOgHeight ?>">
    <meta property="og:image:alt" content="<?= $fotoItem ? safe($fotoItem['title']) : 'Kookye Cat Gallery — galería de fotografías de gatos' ?>">
    <?php endif; ?>

    <!-- Twitter / X Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= safe($pageTitle) ?>">
    <meta name="twitter:description" content="<?= safe($pageDesc) ?>">
    <?php if ($pageOgImage): ?>
    <meta name="twitter:image" content="<?= safe($pageOgImage) ?>">
    <?php endif; ?>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.svg">
    <link rel="icon" type="image/webp" sizes="192x192" href="/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap">

    <link rel="stylesheet" href="/assets/css/style.css?v=<?= safe($appVersion) ?>">

    <!-- Leaflet: Mapa interactivo -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>

    <!-- Schema.org JSON-LD: ImageGallery + Person (+ ImageObject si es página de foto) -->
    <?php
    $schemaGraph = [
        [
            '@type' => 'WebSite',
            '@id'   => $siteUrl . '/#website',
            'url'   => $siteUrl . '/',
            'name'  => $siteName,
            'description' => $pageDesc,
            'inLanguage' => 'es-ES',
            'publisher' => ['@id' => $siteUrl . '/#person'],
        ],
        [
            '@type' => 'Person',
            '@id'   => $siteUrl . '/#person',
            'name'  => $author,
            'url'   => $siteUrl . '/',
            'jobTitle' => 'Fotógrafo',
            'image' => $heroItem ? $siteUrl . photo_asset_url($heroItem['desktop'], __DIR__) : null,
            'sameAs' => [],
        ],
    ];

    // SOLO en la home: la galería completa con todas sus imágenes.
    // En una página de foto NO se listan las demás fotos (cada URL trata de una sola).
    if ($fotoItem === null) {
        $schemaGraph[] = [
            '@type' => 'ImageGallery',
            '@id'   => $siteUrl . '/#gallery',
            'name'  => $siteName,
            'description' => 'Galería de gatos, retratos y momentos cotidianos.',
            'url'   => $siteUrl . '/',
            'inLanguage' => 'es-ES',
            'isPartOf' => ['@id' => $siteUrl . '/#website'],
            'creator' => ['@id' => $siteUrl . '/#person'],
            'numberOfItems' => $totalFotos,
            'associatedMedia' => array_map(function($item) use ($siteUrl, $author) {
                return [
                    '@type' => 'ImageObject',
                    'contentUrl' => $siteUrl . photo_asset_url($item['desktop'], __DIR__),
                    'thumbnailUrl' => $siteUrl . photo_asset_url($item['mobile'], __DIR__),
                    'name' => $item['title'] ?: site_text('text_400a7f33d431611c'),
                    'description' => $item['description'] ?: site_text('text_c7ea52a379d46ca5') . $author,
                    'creator' => ['@id' => $siteUrl . '/#person'],
                    'copyrightHolder' => ['@id' => $siteUrl . '/#person'],
                    'datePublished' => isset($item['mtime']) ? date('c', $item['mtime']) : null,
                ];
            }, array_slice($galleryItems, 0, 50)),
        ];
    }

    // Si es una página de foto concreta, añade su ImageObject como entidad principal + breadcrumb
    if ($fotoItem !== null) {
        $schemaGraph[] = [
            '@type' => 'ImageObject',
            '@id'   => $pageCanonical . '#photo',
            'url'   => $pageCanonical,
            'contentUrl'  => $siteUrl . photo_asset_url($fotoItem['desktop'], __DIR__),
            'thumbnailUrl' => $siteUrl . photo_asset_url($fotoItem['mobile'], __DIR__),
            'name'        => $fotoItem['title'],
            'caption'     => $fotoItem['title'],
            'description' => $fotoItem['description'] ?: site_text('text_c7ea52a379d46ca5') . $author,
            'creator'        => ['@id' => $siteUrl . '/#person'],
            'copyrightHolder' => ['@id' => $siteUrl . '/#person'],
            'creditText'     => $author,
            'datePublished'  => isset($fotoItem['mtime']) ? date('c', $fotoItem['mtime']) : null,
            'width'  => !empty($fotoItem['desktop_w']) ? (int) $fotoItem['desktop_w'] : null,
            'height' => !empty($fotoItem['desktop_h']) ? (int) $fotoItem['desktop_h'] : null,
            'isPartOf' => ['@id' => $siteUrl . '/#gallery'],
        ];
        $schemaGraph[] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => $siteUrl . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $fotoItem['title'], 'item' => $pageCanonical],
            ],
        ];
    }
    ?>
    <script type="application/ld+json">
    <?= json_encode(['@context' => 'https://schema.org', '@graph' => $schemaGraph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
    <link rel="stylesheet" href="/assets/css/site-design.css?v=<?= safe(asset_ver(__DIR__ . '/assets/css/site-design.css')) ?>">
    <link rel="stylesheet" href="/assets/css/photo-navigation.css?v=<?= safe(asset_ver(__DIR__ . '/assets/css/photo-navigation.css')) ?>">
    <link rel="stylesheet" href="/assets/css/interface-worlds.css?v=<?= safe(asset_ver(__DIR__ . '/assets/css/interface-worlds.css')) ?>">
    <link rel="stylesheet" href="/assets/css/gallery-layout.css?v=<?= safe(asset_ver(__DIR__ . '/assets/css/gallery-layout.css')) ?>">
</head>
<body<?= site_design_attributes() ?> class="<?= $heroItem ? 'has-photos' : 'no-photos' ?>"<?= $fotoSlug ? ' data-open-slug="' . safe($fotoSlug) . '"' : '' ?>>

<?php if ($fotoItem === null): ?>
<!-- PRELOADER cinematográfico (solo en la home) -->
<div class="preloader" id="preloader" aria-hidden="true">
    <div class="preloader__inner">
        <img class="preloader__crest" src="<?= safe(site_media_url('logo_image')) ?>" width="500" height="640" alt="">
        <span class="preloader__title"><?= site_text_html('text_2c6b326536186079') ?></span>
        <span class="preloader__bar"><span class="preloader__bar-fill"></span></span>
    </div>
</div>
<?php endif; ?>

<!-- Barra de progreso de scroll -->
<div class="scroll-progress" id="scrollProgress" aria-hidden="true"></div>

<!-- Cursor decorativo (escritorio) -->
<div class="cursor-dot" aria-hidden="true"></div>
<div class="cursor-ring" aria-hidden="true"><span class="cursor-ring__label"><?= site_text_html('text_20ab386d12d982bd') ?></span></div>

<a class="upload-fab" href="/admin.php" aria-label="<?= site_text_html('text_929d5afb3be64e4a') ?>" title="<?= site_text_html('text_929d5afb3be64e4a') ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
    <span class="upload-fab__label"><?= site_text_html('text_c55b46a0c60668c4') ?></span>
</a>

<?php if ($fotoItem !== null): ?>
<!-- DETALLE DE FOTO (permalink /foto/slug): contenido único renderizado por PHP para SEO -->
<section class="photo-detail" aria-label="<?= safe($fotoItem['title']) ?>"
    data-photo-previous="<?= $photoNeighbors['previous'] ? '/foto/' . safe($photoNeighbors['previous']['slug']) : '' ?>"
    data-photo-next="<?= $photoNeighbors['next'] ? '/foto/' . safe($photoNeighbors['next']['slug']) : '' ?>"
    data-photo-previous-image="<?= $photoNeighbors['previous'] ? safe(photo_asset_url($photoNeighbors['previous']['desktop'], __DIR__)) : '' ?>"
    data-photo-next-image="<?= $photoNeighbors['next'] ? safe(photo_asset_url($photoNeighbors['next']['desktop'], __DIR__)) : '' ?>">
    <a href="/" class="photo-detail__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
        <?= site_text_html('text_f99ccb8765ac6c3a') ?>
    </a>
    <div class="photo-detail__inner">
        <div class="photo-detail__viewer">
        <nav class="photo-detail__navigation" aria-label="<?= site_text_html('photo_navigation') ?>">
            <?php foreach (['previous' => 'text_6e24ef52e0e9edbd', 'next' => 'text_8bed218e5d1a26ca'] as $direction => $labelKey): ?>
            <?php if ($photoNeighbors[$direction]): ?>
            <a class="photo-detail__nav photo-detail__nav--<?= $direction ?>" href="/foto/<?= safe($photoNeighbors[$direction]['slug']) ?>" rel="<?= $direction === 'previous' ? 'prev' : 'next' ?>" aria-label="<?= site_text_html($labelKey) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="<?= $direction === 'previous' ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6' ?>"/></svg>
            </a>
            <?php else: ?>
            <span class="photo-detail__nav photo-detail__nav--<?= $direction ?> is-disabled" role="link" aria-disabled="true" aria-label="<?= site_text_html($labelKey) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="<?= $direction === 'previous' ? 'M15 6l-6 6 6 6' : 'M9 6l6 6-6 6' ?>"/></svg>
            </span>
            <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <button class="photo-detail__btn" type="button" data-zoom-slug="<?= safe($fotoItem['slug']) ?>" aria-label="<?= site_text_html('text_e7d9334b34fa5f57') ?>">
            <img class="photo-detail__img"
                 src="<?= safe(photo_asset_url($fotoItem['desktop'], __DIR__)) ?>"
                 alt="<?= safe($fotoItem['title']) ?><?= site_text_html('text_5f894de21770ce4f') ?><?= safe($author) ?>"
                 <?php if (!empty($fotoItem['desktop_w']) && !empty($fotoItem['desktop_h'])): ?>width="<?= (int) $fotoItem['desktop_w'] ?>" height="<?= (int) $fotoItem['desktop_h'] ?>"<?php endif; ?>
                 loading="eager" decoding="async">
            <span class="photo-detail__view-full" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3H3v5M16 3h5v5M3 16v5h5M21 16v5h-5"/></svg><?= site_text_html('text_5ebed0b0766b91d4') ?></span>
        </button>
        </div>
        <div class="photo-detail__info">
            <h1 class="photo-detail__title"><?= safe($fotoItem['title']) ?></h1>
            <?php if (!empty($fotoItem['description'])): ?>
            <p class="photo-detail__desc"><?= safe($fotoItem['description']) ?></p>
            <?php endif; ?>
            <div class="photo-detail__meta">
                <?php if (!empty($fotoItem['category'])): ?>
                <span class="photo-detail__chip"><?= safe($fotoItem['category']) ?></span>
                <?php endif; ?>
                <?php if (!empty($fotoItem['latitude']) && !empty($fotoItem['longitude'])): ?>
                <a class="photo-detail__coords" href="https://www.google.com/maps?q=<?= (float) $fotoItem['latitude'] ?>,<?= (float) $fotoItem['longitude'] ?>" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <?= safe(number_format((float) $fotoItem['latitude'], 4, '.', '')) ?>, <?= safe(number_format((float) $fotoItem['longitude'], 4, '.', '')) ?>
                </a>
                <?php endif; ?>
            </div>
            <div class="photo-detail__actions">
                <button class="photo-detail__zoom" type="button" data-zoom-slug="<?= safe($fotoItem['slug']) ?>"><?= site_text_html('text_d020a22b29eaad92') ?></button>
                <?php if ($isPhotoAdmin): ?>
                <a class="photo-detail__edit" href="/admin.php?edit=<?= safe(rawurlencode($fotoItem['original'])) ?>"><?= site_text_html('text_2f620d60184d9043') ?></a>
                <?php endif; ?>
            </div>
            <div class="photo-app-actions">
                <button class="photo-app-action" type="button" data-favorite-photo="<?= safe($fotoItem['slug']) ?>" aria-pressed="false">♡ Favorita</button>
                <button class="photo-app-action photo-heart" type="button" data-heart-photo="<?= safe($fotoItem['slug']) ?>" aria-label="Dar un corazón" aria-pressed="false">♡ <span data-heart-count>0</span></button>
            </div>
            <?= share_buttons($pageCanonical, $fotoItem['title']) ?>
        </div>
    </div>
</section>

<?php
// FOTOS RELACIONADAS: otras de la misma categoría (con URL propia)
$related = [];
foreach ($items as $ri) {
    if (empty($ri['slug']) || $ri['slug'] === $fotoItem['slug']) continue;
    if (($ri['category'] ?? '') !== ($fotoItem['category'] ?? '')) continue;
    $related[] = $ri;
    if (count($related) >= 6) break;
}
?>
<?php if (!empty($related)): ?>
<section class="related" aria-label="<?= site_text_html('text_c9a6bc6eb8882183') ?>">
    <div class="related__head">
        <span class="related__eyebrow"><?= site_text_html('text_cfed0e3af4509ead') ?></span>
        <h2 class="related__title"><?= site_text_html('text_90dd4cd34311daa0') ?> <em><?= safe($fotoItem['category']) ?></em></h2>
    </div>
    <div class="related__grid">
        <?php foreach ($related as $ri): ?>
        <a class="related__item" href="/foto/<?= safe($ri['slug']) ?>">
            <span class="related__media">
                <img src="<?= safe(photo_asset_url($ri['mobile'], __DIR__)) ?>" alt="<?= safe($ri['title']) ?><?= site_text_html('text_70241acd5e85d5cc') ?>" loading="lazy" decoding="async">
            </span>
            <span class="related__cap"><?= safe($ri['title']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php else: ?>

<!-- HERO tipográfico maximalista (sin foto de fondo) -->
<header class="hero hero--typography">
    <div class="hero__gradient" aria-hidden="true"></div>
    <div class="hero__glow" aria-hidden="true"></div>
    <div class="hero__noise" aria-hidden="true"></div>

    <div class="hero__ornament hero__ornament--tl" aria-hidden="true"></div>
    <div class="hero__ornament hero__ornament--br" aria-hidden="true"></div>

    <div class="hero__content">
        <img class="hero__portrait" src="<?= safe(site_media_url('profile_image')) ?>" width="120" height="120" alt="<?= site_text_html('text_30170303c506cf6c') ?>" loading="lazy">
        <a href="/" class="hero__crest" aria-label="<?= site_text_html('text_83f4e59e2da59d76') ?>">
            <img src="<?= safe(site_media_url('logo_image')) ?>" width="500" height="640" alt="" loading="eager" decoding="async">
        </a>
        <span class="hero__eyebrow"><?= site_text_html('text_b6b50664e231e202') ?></span>
        <h1 class="hero__title">
            <span class="hero__title-line" data-reveal><?= site_text_html('text_cbbba360eb60dd04') ?></span>
            <span class="hero__title-line hero__title-line--accent" data-reveal><?= site_text_html('text_2c1ac3057629f681') ?></span>
        </h1>
        <span class="hero__divider" aria-hidden="true"></span>
        <p class="hero__subtitle" data-reveal><?= site_text_html('text_30170303c506cf6c') ?></p>
        <?php if ($heroItem): ?>
        <div class="hero__meta" data-reveal>
            <span><?= $totalFotos ?> <?= site_text_html($totalFotos === 1 ? 'text_08e81d4e64f6b4ff' : 'text_577e559428a23438') ?></span>
            <span class="hero__meta-sep">·</span>
            <span><?= safe($yearLabel) ?></span>
        </div>
        <?php else: ?>
        <p class="hero__empty-note" data-reveal><?= site_text_html('text_eefccddb211b22ea') ?> <code><?= site_text_html('text_7fe0e410821a1ee4') ?></code> <?= site_text_html('text_cc0ecea94d78a73f') ?></p>
        <?php endif; ?>
    </div>

    <?php if ($heroItem): ?>
    <a href="#galeria" class="hero__scroll" aria-label="<?= site_text_html('text_ddfe959388cbea71') ?>">
        <span class="hero__scroll-line"></span>
        <span class="hero__scroll-text"><?= site_text_html('text_b2cec4172b3ff4d2') ?></span>
    </a>
    <?php endif; ?>
</header>

<!-- Cabecera de perfil móvil -->
<section class="mobile-profile" aria-label="<?= site_text_html('text_1d997c72b89b5e1f') ?>">
    <div class="mobile-profile__masthead">
        <a class="mobile-profile__brand" href="/" aria-label="<?= site_text_html('text_84f085942c2a2aa1') ?>"><?= site_text_html('text_52179dc42df7efe5') ?></a>
        <span class="mobile-profile__edition"><?= site_text_html('text_8aa8595771f39463') ?></span>
    </div>
    <div class="mobile-profile__identity">
        <div class="mobile-profile__portraits">
            <a class="mobile-profile__avatar-link" href="/" aria-label="<?= site_text_html('text_84f085942c2a2aa1') ?>"><img class="mobile-profile__avatar" src="<?= safe(site_media_url('profile_image')) ?>" width="88" height="88" alt="<?= site_text_html('text_30170303c506cf6c') ?>"></a>
            <span class="mobile-profile__crest-wrap"><img src="<?= safe(site_media_url('logo_image')) ?>" width="500" height="640" alt="<?= site_text_html('text_125ff372f2933b34') ?>"></span>
        </div>
        <div class="mobile-profile__stats" aria-label="<?= site_text_html('text_7f9488ff977b7f67') ?>">
            <div><strong><?= $totalFotos ?></strong><span><?= site_text_html('text_577e559428a23438') ?></span></div>
            <div><strong><?= count($categories) ?></strong><span><?= site_text_html('text_bb6db5ced87f3dfa') ?></span></div>
            <div><strong><?= site_text_html('text_b5c772cf5b14f84e') ?></strong><span><?= site_text_html('text_ff1de97302308752') ?></span></div>
        </div>
    </div>
    <div class="mobile-profile__bio">
        <strong class="mobile-profile__name"><?= site_text_html('text_4577bab0d9627af1') ?> <span class="mobile-profile__verified" role="img" aria-label="<?= site_text_html('text_6ba397681d3ee9c1') ?>" title="<?= site_text_html('text_30170303c506cf6c') ?>">✓</span></strong>
        <p><?= site_text_html('text_2e2b80d871481edc') ?></p>
        <p><?= site_text_html('text_54b7645201863e32') ?></p>
        <a class="mobile-profile__website" href="https://example.com" target="_blank" rel="noopener noreferrer" aria-label="<?= site_text_html('text_e5d843682b4f2596') ?>"><?= site_text_html('text_7e6debbd1dab03b1') ?></a>
    </div>
</section>

<?php
// Retrato opcional del fotógrafo: sube uno de estos por FTP a la raíz si lo deseas
$portrait = null;
$profilePath = site_media_url('profile_image');
if (is_file(__DIR__ . $profilePath)) $portrait = ltrim($profilePath, '/');
?>
<main id="galeria" class="gallery-section">

    <?php if ($siteSettings['show_categories'] && $heroItem && !empty($categories)): ?>
    <!-- SELECTOR DE CATEGORÍAS -->
    <div class="categories-filter" id="categoriesFilter" data-site-section="categories" data-reveal>
        <div class="categories-filter__inner">
            <button class="categories-filter__chip is-active" data-category=""><?= site_text_html('text_aff4d19d6ee43b20') ?></button>
            <?php foreach ($categories as $cat): ?>
            <button class="categories-filter__chip" data-category="<?= safe($cat) ?>"><?= safe($cat) ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php endif; ?>

    <?php if ($heroItem): ?>
    <div class="gallery-intro" data-site-section="intro">
        <h2 class="gallery-intro__title" data-reveal><?= site_text_html('text_7d367bc92702d135') ?></h2>
        <p class="gallery-intro__text" data-reveal>
            <?= site_text_html('text_c15e876671f50d92') ?> <strong><?= site_text_html('text_b5c772cf5b14f84e') ?></strong><?= site_text_html('text_a9157ae01488ec67') ?>
        </p>
    </div>

    <!-- GALERÍA MASONRY -->
    <section class="masonry" id="masonry">
        <?php
        // Numeración cronológica (Opción B: la foto MÁS ANTIGUA es la nº 1).
        // $galleryItems está ordenado de más nueva a más antigua, así que invertimos.
        $totalPhotos = count($galleryItems);
        $catCounts = [];                       // total de fotos por categoría
        foreach ($galleryItems as $gi) {
            $c = $gi['category'] ?? '';
            $catCounts[$c] = ($catCounts[$c] ?? 0) + 1;
        }
        $catRunning = $catCounts;              // contador descendente por categoría
        ?>
        <?php foreach ($galleryItems as $i => $item): ?>
            <?php
            /**
             * ESTRUCTURA DE CADA FOTO
             *
             * Para añadir título y descripción a una foto, crea un archivo .txt con el
             * mismo nombre que la foto en la carpeta /img/ (formato: Título / --- / Descripción).
             * Si no creas el .txt, la foto aparecerá sin texto y todo funciona igual.
             */
            $itemCat   = $item['category'] ?? '';
            $numGlobal = $totalPhotos - $i;            // global: más antigua = 1
            $numCat    = $catRunning[$itemCat]--;      // dentro de su categoría: más antigua = 1
            $catTotal  = $catCounts[$itemCat];
            $hasTitle  = $item['title'] !== '';
            $aspect    = max(0.01, (float) ($item['aspect'] ?? 1.5));
            ?>
            <article
                class="card<?= $hasTitle ? ' card--has-title' : '' ?>"
                style="--aspect: <?= number_format($aspect, 4, '.', '') ?>"
                data-reveal
                data-index="<?= $i ?>"
                data-title="<?= safe($item['title']) ?>"
                data-description="<?= safe($item['description']) ?>"
                data-full="<?= safe(photo_asset_url($item['desktop'], __DIR__)) ?>"
                data-category="<?= safe($item['category'] ?? '') ?>"
                data-lat="<?= $item['latitude'] ?? 0 ?>"
                data-lng="<?= $item['longitude'] ?? 0 ?>"
                data-slug="<?= safe($item['slug'] ?? '') ?>"
                data-num-global="<?= $numGlobal ?>"
                data-num-cat="<?= $numCat ?>"
                data-cat-total="<?= $catTotal ?>"
            >
                <?php if (!empty($item['slug'])): ?>
                <a class="card__btn" href="/foto/<?= safe($item['slug']) ?>" aria-label="<?= site_text_html('text_199d79e67ea29a83') ?><?= safe($item['title'] ?: site_text('text_08e81d4e64f6b4ff')) ?>">
                <?php else: ?>
                <button class="card__btn" type="button" aria-label="<?= site_text_html('text_7be0de9ca7203c46') ?><?= safe($item['title'] ?: site_text('text_08e81d4e64f6b4ff')) ?>">
                <?php endif; ?>
                    <picture class="card__picture">
                        <source media="(max-width: 768px)" srcset="<?= safe(photo_asset_url($item['mobile'], __DIR__)) ?>" type="image/webp">
                        <img
                            class="card__img"
                            loading="lazy"
                            decoding="async"
                            src="<?= safe(photo_asset_url($item['desktop'], __DIR__)) ?>"
                            alt="<?= safe($item['title'] ?: site_text('text_c7ea52a379d46ca5') . $author) ?><?= site_text_html('text_70241acd5e85d5cc') ?>"
                            title="<?= safe($item['title'] ?: site_text('text_52179dc42df7efe5')) ?>"
                        >
                    </picture>

                    <?php if (!empty($item['slug'])): ?>
                    <span class="card__heart-count" data-heart-display="<?= safe($item['slug']) ?>" aria-label="Corazones">♡ <span data-heart-count>0</span></span>
                    <?php endif; ?>
                    <?php if ($hasTitle): ?>
                    <div class="card__title-base" aria-hidden="true">
                        <h3 class="card__title"><?= safe($item['title']) ?></h3>
                    </div>
                    <?php endif; ?>
                <?php if (!empty($item['slug'])): ?></a><?php else: ?></button><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="gallery-app-tools" aria-label="Herramientas de galería">
        <button type="button" class="gallery-app-tool" id="favoritesToggle" aria-pressed="false">♡ Favoritas</button>
        <button type="button" class="gallery-app-tool" id="slideshowStart">▶ Presentación</button>
    </div>

    <!-- Paginación gestionada por JS (aparece cuando hay más de 20 fotos) -->
    <nav class="pagination" id="jsPagination" aria-label="<?= site_text_html('text_5935e29992445803') ?>" hidden></nav>

    <?php endif; ?>

</main>

<?php if ($siteSettings['show_map']): ?>
<section class="map-section" id="mapa" data-site-section="map" aria-labelledby="mapHeading">
    <div class="map-section__head">
        <span class="map-section__eyebrow"><?= site_text_html('text_d50e1dd2fb1b8143') ?></span>
        <h2 class="map-section__title" id="mapHeading"><?= site_text_html('text_525b8adbcb4f9654') ?></h2>
        <label class="map-filter" for="mapCategory">
            <span><?= site_text_html('text_b22780340ae5569f') ?></span>
            <select id="mapCategory" aria-label="<?= site_text_html('text_68b4717007382b83') ?>">
                <option value=""><?= site_text_html('text_425a839def0b84fd') ?></option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= safe($cat) ?>"><?= safe($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="map-filter__chevron" aria-hidden="true">⌄</span>
        </label>
    </div>
    <div class="map-container" id="mapContainer" data-reveal><div id="map" class="map"></div></div>
</section>
<?php endif; ?>

<!-- SECCIÓN: EL PROYECTO -->
<?php if ($siteSettings['show_project']): ?>
<section class="project<?= $portrait ? '' : ' project--no-photo' ?>" id="proyecto" data-site-section="project">
    <div class="project__inner">
        <?php if ($portrait): ?>
        <div class="project__media" data-reveal>
            <div class="project__media-frame">
                <img src="/<?= safe($portrait) ?>" alt="<?= site_text_html('text_ee497220c8c386d8') ?>" loading="lazy" decoding="async">
            </div>
        </div>
        <?php endif; ?>

        <div class="project__text">
            <span class="project__eyebrow" data-reveal><?= site_text_html('text_c9248cc61aa75376') ?></span>
            <h2 class="project__title" data-reveal><?= site_text_html('text_6636c29ab23b6ec8') ?><br><?= site_text_html('text_3d9d76375e115725') ?></h2>
            <div class="project__body" data-reveal>
                <?= site_page_content('project') ?>
            </div>
            <a href="#galeria" class="project__link" data-reveal>
                <span><?= site_text_html('text_92d07ee88e566b66') ?></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>
<?php endif; // fin: home vs página de foto ?>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?= site_text_html('text_f0430f9bb09ea2c6') ?>">
    <button class="lightbox__fullscreen" type="button" aria-label="<?= site_text_html('text_69dd3ff6fd21ed0e') ?>" title="<?= site_text_html('text_aae4e10e8be39bd5') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M8 3H3v5M16 3h5v5M3 16v5h5M21 16v5h-5"/></svg>
        <span><?= site_text_html('text_5ebed0b0766b91d4') ?></span>
    </button>
    <button class="lightbox__close" type="button" aria-label="<?= site_text_html('text_2b26671eb88810be') ?>" title="<?= site_text_html('text_2b26671eb88810be') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M6 6l12 12M6 18L18 6"/></svg>
    </button>
    <div class="lightbox__stage">
        <button class="lightbox__nav lightbox__nav--prev" type="button" aria-label="<?= site_text_html('text_6e24ef52e0e9edbd') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 6l-6 6 6 6"/></svg>
        </button>
        <img class="lightbox__img" src="" alt="">
        <button class="lightbox__nav lightbox__nav--next" type="button" aria-label="<?= site_text_html('text_8bed218e5d1a26ca') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 6l6 6-6 6"/></svg>
        </button>
    </div>
    <div class="lightbox__caption">
        <span class="lightbox__counter"></span>
        <h3 class="lightbox__title"></h3>
        <p class="lightbox__description"></p>
        <a class="lightbox__coords" href="#" target="_blank" rel="noopener" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/>
                <circle cx="12" cy="10" r="2.5"/>
            </svg>
            <span class="lightbox__coords-text"></span>
        </a>
        <div class="photo-app-actions">
            <button class="photo-app-action" type="button" data-favorite-current aria-pressed="false">♡ Favorita</button>
            <button class="photo-app-action photo-heart" type="button" data-heart-current aria-label="Dar un corazón" aria-pressed="false">♡ <span data-heart-count>0</span></button>
        </div>
        <?= share_buttons('', '', true) ?>
    </div>
</div>

<!-- Botón volver arriba -->
<button class="top-btn" type="button" aria-label="<?= site_text_html('text_e06e2e6400797b86') ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>

<!-- CONTACTO -->
<?php if ($siteSettings['show_social']): ?>
<section class="contact" id="contacto" data-site-section="social">
    <div class="contact__glow" aria-hidden="true"></div>
    <div class="contact__inner">
        <span class="contact__eyebrow"><?= site_text_html('text_bdd8f61b94c52379') ?></span>
        <h2 class="contact__title"><?= site_text_html('text_49a006d539216e16') ?><br><?= site_text_html('text_f366ccf006a44b3f') ?></h2>
        <p class="contact__text"><?= site_text_html('text_ab3ceef210237f9d') ?></p>
        <div class="contact__links">
            <a class="contact__link" href="<?= safe((string) $siteSettings['instagram_url']) ?>" target="_blank" rel="noopener">
                <span class="contact__icon">
                    <span class="contact__logo contact__logo--ig" aria-hidden="true"></span>
                </span>
                <span class="contact__net"><?= site_text_html('text_bad57ef7837c8e6b') ?></span>
                <span class="contact__handle"><?= site_text_html('text_3b20084d3d94b4ee') ?></span>
            </a>
            <a class="contact__link" href="<?= safe((string) $siteSettings['threads_url']) ?>" target="_blank" rel="noopener">
                <span class="contact__icon">
                    <span class="contact__logo contact__logo--threads" aria-hidden="true"></span>
                </span>
                <span class="contact__net"><?= site_text_html('text_3e42e385075b9b56') ?></span>
                <span class="contact__handle"><?= site_text_html('social_threads_user') ?></span>
            </a>
            <?php
            $socialNames = ['facebook'=>'Facebook','x'=>'X','youtube'=>'YouTube','tiktok'=>'TikTok','flickr'=>'Flickr','linkedin'=>'LinkedIn','pinterest'=>'Pinterest','500px'=>'500px','bluesky'=>'Bluesky','mastodon'=>'Mastodon'];
            foreach (($siteSettings['social_links'] ?? []) as $social):
                $network = (string) ($social['network'] ?? '');
                if (!isset($socialNames[$network]) || empty($social['url'])) continue;
            ?>
            <a class="contact__link" href="<?= safe((string) $social['url']) ?>" target="_blank" rel="noopener">
                <span class="contact__icon"><?= social_profile_icon($network) ?></span>
                <span class="contact__net"><?= safe($socialNames[$network]) ?></span>
                <span class="contact__handle"><?= safe((string) (($social['handle'] ?? '') ?: $socialNames[$network])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= site_text_html('text_318bb1fc64a6e036') ?></p>
    <nav class="site-footer__nav">
        <a href="/aviso-legal.php"><?= site_text_html('text_7664bd753da07ad5') ?></a>
        <a href="/politica-privacidad.php"><?= site_text_html('text_52233e2c4d6b2e9a') ?></a>
        <a href="/politica-cookies.php"><?= site_text_html('text_141395eb35564fe5') ?></a>
        <a href="/admin.php"><?= site_text_html('text_1a1b3e6ecc51b478') ?></a>
        <button class="pwa-install-button" id="installAppButton" type="button" hidden><?= site_text_html('text_f1e02d469cf54d61') ?></button>
    </nav>
</footer>

<?= site_client_texts() ?>
<script src="/assets/js/pwa.js?v=<?= safe($appVersion) ?>" defer></script>
<script src="/assets/js/gallery-layout.js?v=<?= safe(asset_ver(__DIR__ . '/assets/js/gallery-layout.js')) ?>" defer></script>
<script src="/assets/js/main.js?v=<?= safe($appVersion) ?>" defer></script>
<script type="module" src="/assets/js/interface-worlds.js?v=<?= safe(asset_ver(__DIR__ . '/assets/js/interface-worlds.js')) ?>"></script>
</body>
</html>
