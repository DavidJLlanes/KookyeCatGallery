<?php
declare(strict_types=1);
require_once __DIR__ . '/inc/site-settings.php';
$pageTitle = site_text('text_c4a491ae2cd05080');
$pageH1 = site_text('text_e4124618327d933f');
?>
<!DOCTYPE html>
<html lang="es-ES">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0a0a0a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= site_text_html('text_52179dc42df7efe5') ?>">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.json">
    <meta name="robots" content="noindex, follow">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
    <link rel="stylesheet" href="/assets/css/site-design.css?v=<?= filemtime(__DIR__ . '/assets/css/site-design.css') ?>">
    <link rel="stylesheet" href="/assets/css/typography.css?v=<?= filemtime(__DIR__ . '/assets/css/typography.css') ?>">
</head>
<body<?= site_design_attributes() ?> class="legal-page">

<header class="legal-header">
    <a href="/" class="legal-back" aria-label="<?= site_text_html('text_3906c387259fafc4') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 6l-6 6 6 6"/></svg>
        <span><?= site_text_html('text_ab26ae7bca362195') ?></span>
    </a>
    <a href="/" class="legal-brand">
        <span class="legal-brand__title"><?= site_text_html('text_52179dc42df7efe5') ?></span>
        <span class="legal-brand__sub"><?= site_inline_text_html('text_30170303c506cf6c') ?></span>
    </a>
</header>

<main class="legal-main">
    <h1 class="legal-h1"><?= htmlspecialchars($pageH1) ?></h1>

    <section class="legal-content">
        <?= site_page_content('cookies_policy') ?>
    </section>
</main>

<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= site_text_html('text_57bdd4e7e9940c57') ?></p>
    <nav class="site-footer__nav">
        <a href="/aviso-legal.php"><?= site_text_html('text_7664bd753da07ad5') ?></a>
        <a href="/politica-privacidad.php"><?= site_text_html('text_52233e2c4d6b2e9a') ?></a>
        <a href="/politica-cookies.php"><?= site_text_html('text_141395eb35564fe5') ?></a>
        <button class="pwa-install-button" id="installAppButton" type="button" hidden><?= site_text_html('text_f1e02d469cf54d61') ?></button>
    </nav>
</footer>

<?= site_client_texts() ?>
<script src="/assets/js/pwa.js?v=<?= substr(hash_file('sha256', __DIR__ . '/assets/js/pwa.js'), 0, 12) ?>" defer></script>
</body>
</html>
