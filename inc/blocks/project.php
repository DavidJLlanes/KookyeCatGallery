<?php
/**
 * Bloque «El proyecto»: texto y retrato del autor.
 *
 * Se activa con `show_project`. Su posición la fija `section_order`. El retrato es opcional: se toma del primer archivo existente en la raíz.
 *
 * Variables: $siteSettings
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
<?php
// Retrato opcional del fotógrafo: sube uno de estos por FTP a la raíz si lo deseas
$portrait = null;
foreach (['retrato.webp', 'david.webp', 'david.jpg', 'retrato.jpg'] as $p) {
    if (is_file($rootDir . '/' . $p)) { $portrait = $p; break; }
}
?>
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
