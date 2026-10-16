<?php
/**
 * Cabecera de escritorio (portada tipográfica).
 *
 * Se muestra en pantallas anchas. Se oculta con `show_header_desktop` (CSS, atributo data-show-header-desktop en <body>). No es reordenable: siempre va primero.
 *
 * Variables: $heroItem, $totalFotos, $yearLabel
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
<!-- HERO tipográfico maximalista (sin foto de fondo) -->
<header class="hero hero--typography">
    <div class="hero__gradient" aria-hidden="true"></div>
    <div class="hero__glow" aria-hidden="true"></div>
    <div class="hero__noise" aria-hidden="true"></div>

    <div class="hero__ornament hero__ornament--tl" aria-hidden="true"></div>
    <div class="hero__ornament hero__ornament--br" aria-hidden="true"></div>

    <div class="hero__content">
        <img class="hero__portrait" src="<?= safe(site_media_src('profile_image')) ?>" width="120" height="120" alt="<?= site_text_html('text_30170303c506cf6c') ?>" loading="lazy">
        <?php if (!empty($siteSettings['show_logo'])): ?>
        <a href="/" class="hero__crest" aria-label="<?= site_text_html('text_83f4e59e2da59d76') ?>">
            <img src="<?= safe(site_media_src('logo_image')) ?>" width="512" height="512" alt="" loading="eager" decoding="async">
        </a>
        <?php endif; ?>
        <span class="hero__eyebrow"><?= site_inline_text_html('text_b6b50664e231e202') ?></span>
        <h1 class="hero__title">
            <?php [$nameFirstLine, $nameSecondLine] = site_name_lines(site_title()); ?>
            <span class="hero__title-line" data-reveal><?= site_name_part_html($nameFirstLine) ?></span>
            <?php if ($nameSecondLine !== ''): ?><span class="hero__title-line hero__title-line--accent" data-reveal><?= site_name_part_html($nameSecondLine) ?></span><?php endif; ?>
        </h1>
        <span class="hero__divider" aria-hidden="true"></span>
        <p class="hero__subtitle" data-reveal><?= site_inline_text_html('text_30170303c506cf6c') ?></p>
        <?php if ($heroItem): ?>
        <div class="hero__meta" data-reveal>
            <span><?= $totalFotos ?> <?= site_text_html($totalFotos === 1 ? 'text_08e81d4e64f6b4ff' : 'text_577e559428a23438') ?></span>
            <span class="hero__meta-sep">·</span>
            <span><?= safe($yearLabel) ?></span>
        </div>
        <?php else: ?>
        <p class="hero__empty-note" data-reveal><?= site_inline_text_html('template_empty_gallery_message') ?></p>
        <?php endif; ?>
    </div>

    <?php if ($heroItem): ?>
    <a href="#galeria" class="hero__scroll" aria-label="<?= site_text_html('text_ddfe959388cbea71') ?>">
        <span class="hero__scroll-line"></span>
        <span class="hero__scroll-text"><?= site_text_html('text_b2cec4172b3ff4d2') ?></span>
    </a>
    <?php endif; ?>
</header>
