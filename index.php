<?php

declare(strict_types=1);

// La página contiene datos dinámicos y acciones privadas: nunca servir HTML guardado.
// Los assets (CSS/JS) conservan su versión en la URL y pueden cachearse aparte.
header('Cache-Control: private, no-store, max-age=0');

require __DIR__ . '/inc/processor.php';
require __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/site-settings.php';
require __DIR__ . '/inc/sitemap.php';
require __DIR__ . '/inc/ai-text.php';
require __DIR__ . '/inc/photo-navigation.php';
require __DIR__ . '/inc/page-blocks.php';

$appVersion = app_version();

$siteUrl  = rtrim(getenv('GALLERY_PUBLIC_URL') ?: 'https://example.com', '/');
$siteName = site_text('text_52179dc42df7efe5');
$author   = site_text('text_30170303c506cf6c');
$siteSettings = site_settings_load();


$processor = new ImageProcessor(__DIR__);
$items     = $processor->processAll();

$aiConfig = ai_text_config([], $siteName, $siteUrl);
if ($aiConfig['ai_enabled']) {
    $ai = new AiTextGenerator(__DIR__, $aiConfig);
    $ai->processMissing($items, $aiConfig['ai_max_per_request']);
}

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
    'Paisajes' => ['paisaje', 'montaña', 'valle', 'horizonte'],
    'Retratos' => ['retrato', 'mirada', 'primer plano'],
    'Naturaleza' => ['naturaleza', 'bosque', 'flora', 'fauna'],
    'Arquitectura' => ['arquitectura', 'edificio', 'fachada', 'monumento'],
    'Calle' => ['calle', 'ciudad', 'urbano', 'plaza'],
    'Viajes' => ['viaje', 'destino', 'ruta', 'escapada'],
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
$pageOgImage   = $siteUrl . '/assets/img/kookyecatgallery-photographers-cover.png';
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
<html lang="es-ES"<?= $fotoItem !== null && ($_GET['viewer'] ?? '') === '1' ? ' class="viewer-pending"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
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
    <meta property="og:image:alt" content="<?= $fotoItem ? safe($fotoItem['title']) : 'KookyeCatGallery — portfolio web para fotógrafos' ?>">
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
            'description' => 'Galería fotográfica y portfolio de autor.',
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
    <noscript><style>html.viewer-pending body > * { visibility: visible !important; }</style></noscript>
    <link rel="stylesheet" href="/assets/css/interface-worlds.css?v=<?= safe(asset_ver(__DIR__ . '/assets/css/interface-worlds.css')) ?>">
    <link rel="stylesheet" href="/assets/css/gallery-layout.css?v=<?= safe(asset_ver(__DIR__ . '/assets/css/gallery-layout.css')) ?>">
    <?php if ($fotoItem === null) echo premium_gallery_head_tags(); // Estilos de la galería premium activa (ver inc/premium-galleries.php). ?>
</head>
<?php
// Datos compartidos por los bloques de la portada (inc/blocks/*.php).
$pageContext = ['author' => $author, 'categories' => $categories, 'galleryItems' => $galleryItems, 'heroItem' => $heroItem, 'rootDir' => __DIR__, 'siteSettings' => $siteSettings, 'totalFotos' => $totalFotos, 'yearLabel' => $yearLabel];
?>
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
    <a href="<?= safe(site_premium_gallery_active($siteSettings) ? '/#baraja=' . rawurlencode((string) ($fotoItem['slug'] ?? '')) : '/') ?>" class="photo-detail__back">
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

<?php
// Cabeceras fijas y bloques reordenables de la portada (ver inc/page-blocks.php).
echo page_block_render('header-desktop', $pageContext);
echo page_block_render('header-mobile', $pageContext);
echo page_blocks_render_home($pageContext, $siteSettings['section_order'] ?? null);
?>
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

<?php if ($fotoItem !== null) echo page_block_render('social', $pageContext); // En la home lo coloca page_blocks_render_home(). ?>

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
<?php if ($fotoItem === null) echo premium_gallery_script_tags(); // Comportamiento de la galería premium activa. ?>
<script type="module" src="/assets/js/interface-worlds.js?v=<?= safe(asset_ver(__DIR__ . '/assets/js/interface-worlds.js')) ?>"></script>
</body>
</html>
