<?php
declare(strict_types=1);

/**
 * Parte pública del microblog.
 *
 *   /microblog.php?id=ID               Hilo completo (enlace permanente)
 *   /microblog.php?id=ID&embed=1       Versión mínima para incrustar en otras webs (<iframe>)
 *   /microblog.php?feed=1&offset=N     JSON con la siguiente página del feed («Cargar más»)
 *
 * Solo lectura: crear, editar y responder se hace desde el panel (admin.php?microblog=1).
 */

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/site-settings.php';
require_once __DIR__ . '/inc/private-storage.php';
require_once __DIR__ . '/inc/microblog.php';

$siteUrl = gallery_public_url();

// Feed por AJAX.
if (isset($_GET['feed'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    $settings = site_settings_load();
    if (empty($settings['show_microblog'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'El microblog no está visible.']);
        exit;
    }
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
    $page = microblog_render_feed_page(microblog_load_public(), $offset === false ? 0 : $offset);
    echo json_encode(['ok' => true, 'html' => $page['html'], 'next' => $page['next']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$id = (string) ($_GET['id'] ?? '');
$posts = microblog_valid_id($id) ? microblog_load_public() : [];
$post = $posts[$id] ?? null;
$settings = site_settings_load();
if ($post === null || empty($settings['show_microblog'])) {
    http_response_code(404);
    header('Cache-Control: no-store');
    $post = null;
}

$embed = isset($_GET['embed']);
$version = static fn(string $file): string => substr(hash_file('sha256', __DIR__ . $file) ?: '0', 0, 12);
$rootId = $post['parent'] ?? ($post['id'] ?? '');
$root = $post !== null ? ($posts[$rootId] ?? $post) : null;
$replies = $root !== null ? microblog_replies($posts, $root['id']) : [];
$author = site_text('text_30170303c506cf6c');
$pageTitle = $post !== null ? microblog_excerpt($post, 70) . ' · ' . $author : 'Post no encontrado · ' . $author;
$pageDesc = $post !== null ? microblog_excerpt($post, 200) : '';
$canonical = $post !== null ? $siteUrl . microblog_permalink($post) : $siteUrl . '/';
$ogImage = '';
if ($post !== null && $post['images']) $ogImage = $siteUrl . microblog_image_url($post['images'][0]['file']);
elseif ($post !== null) $ogImage = $siteUrl . site_media_url('profile_image');
?>
<!DOCTYPE html>
<html lang="es-ES">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a0a0a">
    <title><?= safe($pageTitle) ?></title>
    <?php if ($post !== null): ?>
    <meta name="description" content="<?= safe($pageDesc) ?>">
    <link rel="canonical" href="<?= safe($canonical) ?>">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= safe($pageTitle) ?>">
    <meta property="og:description" content="<?= safe($pageDesc) ?>">
    <meta property="og:url" content="<?= safe($canonical) ?>">
    <meta property="og:image" content="<?= safe($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>
    <meta name="robots" content="<?= $embed || $post === null ? 'noindex, follow' : 'index, follow, max-image-preview:large' ?>">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?= safe($version('/assets/css/style.css')) ?>">
    <link rel="stylesheet" href="/assets/css/site-design.css?v=<?= safe($version('/assets/css/site-design.css')) ?>">
    <link rel="stylesheet" href="/assets/css/microblog.css?v=<?= safe($version('/assets/css/microblog.css')) ?>">
    <link rel="stylesheet" href="/assets/css/typography.css?v=<?= safe($version('/assets/css/typography.css')) ?>">
</head>
<body<?= site_design_attributes() ?> class="mb-page<?= $embed ? ' mb-page--embed' : '' ?>">
<?php if ($embed): ?>
<main class="mb-embed">
    <?php if ($post !== null): ?>
    <?= microblog_render_thread($post, [], ['actions' => false]) ?>
    <a class="mb-embed__source" href="<?= safe($canonical) ?>" target="_blank" rel="noopener"><?= safe(parse_url($siteUrl, PHP_URL_HOST) ?: '') ?> ↗</a>
    <?php else: ?>
    <p class="mb-empty">Este post ya no está disponible.</p>
    <?php endif; ?>
</main>
<script>
// Avisa a la página que incrusta del alto real para que el iframe no tenga barras.
(function () {
    var send = function () {
        var h = Math.ceil(document.documentElement.scrollHeight);
        parent.postMessage({ type: 'djl-microblog-height', id: <?= json_encode($id) ?>, height: h }, '*');
    };
    window.addEventListener('load', send);
    window.addEventListener('resize', send);
    if ('ResizeObserver' in window) new ResizeObserver(send).observe(document.body);
})();
</script>
<?php else: ?>
<header class="legal-header">
    <a href="/#microblog" class="legal-back" aria-label="Volver al microblog">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 6l-6 6 6 6"/></svg>
        <span>Volver</span>
    </a>
    <a href="/" class="legal-brand">
        <span class="legal-brand__title"><?= site_text_html('text_52179dc42df7efe5') ?></span>
        <span class="legal-brand__sub"><?= site_text_html('text_30170303c506cf6c') ?></span>
    </a>
</header>
<main class="mb-permalink">
    <?php if ($root !== null): ?>
    <?= microblog_render_thread($root, $replies) ?>
    <?php else: ?>
    <p class="mb-empty">Este post no existe o ya no está disponible.</p>
    <p class="mb-empty"><a href="/">Ir a la portada</a></p>
    <?php endif; ?>
</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= site_text_html('text_318bb1fc64a6e036') ?></p>
</footer>
<script src="/assets/js/microblog.js?v=<?= safe($version('/assets/js/microblog.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
