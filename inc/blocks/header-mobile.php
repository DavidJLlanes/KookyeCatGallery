<?php
/**
 * Cabecera de móvil (perfil).
 *
 * Se muestra en pantallas estrechas. Se oculta con `show_header_mobile` (CSS, atributo data-show-header-mobile en <body>). No es reordenable: va justo después de la cabecera de escritorio.
 *
 * Variables: $categories, $totalFotos
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
<!-- Cabecera de perfil móvil -->
<section class="mobile-profile" aria-label="<?= site_text_html('text_1d997c72b89b5e1f') ?>"
    data-template-reel-label="<?= site_text_html('template_reel_label') ?>"
    data-template-atlas-label="<?= site_text_html('template_atlas_label') ?>"
    data-template-orbit-label="<?= site_text_html('template_orbit_label') ?>"
    data-template-scrapbook-label="<?= site_text_html('template_scrapbook_label') ?>">
    <div class="mobile-profile__masthead">
        <a class="mobile-profile__brand" href="/" aria-label="<?= site_text_html('text_84f085942c2a2aa1') ?>"><?= site_text_html('text_52179dc42df7efe5') ?></a>
        <span class="mobile-profile__edition"><?= site_inline_text_html('text_8aa8595771f39463') ?></span>
    </div>
    <div class="mobile-profile__identity">
        <div class="mobile-profile__portraits">
            <a class="mobile-profile__avatar-link" href="/" aria-label="<?= site_text_html('text_84f085942c2a2aa1') ?>"><img class="mobile-profile__avatar" src="<?= safe(site_media_url('profile_image')) ?>" width="88" height="88" alt="<?= site_text_html('text_30170303c506cf6c') ?>"></a>
            <span class="mobile-profile__crest-wrap"><img src="<?= safe(site_media_url('logo_image')) ?>" width="500" height="640" alt="<?= site_text_html('text_125ff372f2933b34') ?>"></span>
        </div>
        <div class="mobile-profile__stats" aria-label="<?= site_text_html('text_7f9488ff977b7f67') ?>">
            <div><strong><?= $totalFotos ?></strong><span><?= site_text_html('text_577e559428a23438') ?></span></div>
            <div><strong><?= count($categories) ?></strong><span><?= site_text_html('text_bb6db5ced87f3dfa') ?></span></div>
            <div><strong><?= site_inline_text_html('text_b5c772cf5b14f84e') ?></strong><span><?= site_inline_text_html('text_ff1de97302308752') ?></span></div>
        </div>
    </div>
    <div class="mobile-profile__bio">
        <strong class="mobile-profile__name"><?= site_inline_text_html('text_4577bab0d9627af1') ?> <span class="mobile-profile__verified" role="img" aria-label="<?= site_text_html('text_6ba397681d3ee9c1') ?>" title="<?= site_text_html('text_30170303c506cf6c') ?>">✓</span></strong>
        <p><?= site_inline_text_html('text_2e2b80d871481edc') ?></p>
        <p><?= site_inline_text_html('text_54b7645201863e32') ?></p>
        <?php if (!empty($siteSettings['profile_website_url'])): ?>
            <a class="mobile-profile__website" href="<?= safe((string) $siteSettings['profile_website_url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="Visitar <?= site_text_html('text_7e6debbd1dab03b1') ?>, se abre en una pestaña nueva"><?= site_inline_text_html('text_7e6debbd1dab03b1') ?></a>
            <?php endif; ?>
    </div>
</section>
