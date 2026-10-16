<?php
/**
 * Bloque «El proyecto»: texto y, a elección, el retrato del autor o una segunda columna de texto.
 *
 * Se activa con `show_project`. Su posición la fija `section_order`. La disposición la elige `project_layout` (themes/project-layouts/):
 * «image» (retrato + texto), «aside» (segunda columna de texto, la página «project_aside») o «none» (solo texto, centrado).
 * Sin retrato o sin texto en la segunda columna, el bloque queda como solo texto, centrado.
 *
 * Variables: $siteSettings
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
<?php
$layoutId = (string) ($siteSettings['project_layout'] ?? 'image');
$layout = \Kcg\Core\Registry::for('project-layouts')->get($layoutId) ?? \Kcg\Core\Registry::for('project-layouts')->get('image');
$media = (string) ($layout['media'] ?? 'image');
// Retrato opcional del fotógrafo: el de «Imagen de perfil» en los ajustes, si existe
$portrait = null;
$profilePath = site_media_url('profile_image');
if ($media === 'image' && is_file($rootDir . $profilePath)) $portrait = ltrim($profilePath, '/');
// Segunda columna: la página «El Proyecto · segunda columna»; vacía, no hay columna.
$asideHtml = $media === 'aside' ? trim(site_page_content('project_aside')) : '';
$hasMedia = $portrait !== null || $asideHtml !== '';
?>
<!-- SECCIÓN: EL PROYECTO -->
<?php if ($siteSettings['show_project']): ?>
<section class="project<?= $hasMedia ? '' : ' project--no-photo' ?><?= $asideHtml !== '' ? ' project--aside' : '' ?>" id="proyecto" data-site-section="project">
    <div class="project__inner">
        <?php if ($portrait): ?>
        <div class="project__media" data-reveal>
            <div class="project__media-frame">
                <img src="<?= safe(site_media_src('profile_image')) ?>" alt="<?= site_text_html('text_ee497220c8c386d8') ?>" loading="lazy" decoding="async">
            </div>
        </div>
        <?php elseif ($asideHtml !== ''): ?>
        <div class="project__aside" data-reveal>
            <div class="project__body project__body--aside">
                <?= $asideHtml ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="project__text">
            <span class="project__eyebrow" data-reveal><?= site_text_html('text_c9248cc61aa75376') ?></span>
            <h2 class="project__title" data-reveal><?= site_text_html('text_6636c29ab23b6ec8') ?></h2>
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
