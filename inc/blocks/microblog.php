<?php
/**
 * Bloque «Microblog»: feed de notas estilo Tumblr / Threads.
 *
 * Se activa con `show_microblog`. Su posición la fija `section_order`. No se pinta si todavía no hay posts.
 * Muestra los primeros hilos y un botón «Cargar más» (assets/js/microblog.js) que pide el resto a
 * /microblog.php?feed=1&offset=N.
 *
 * Variables: $siteSettings
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
<!-- MICROBLOG -->
<?php if ($siteSettings['show_microblog']):
    $microblogPage = microblog_feed(0);
    if ($microblogPage['html'] !== ''): ?>
<section class="mb-section" id="microblog" data-site-section="microblog" aria-labelledby="microblog-title">
    <div class="mb-section__inner">
        <header class="mb-section__head">
            <span class="mb-section__eyebrow"><?= site_inline_text_html('text_6d1f0b7e2a4c9853') ?></span>
            <h2 class="mb-section__title" id="microblog-title"><?= site_inline_text_html('text_b3e82f5a7c10d946') ?></h2>
        </header>
        <div class="mb-feed" data-mb-feed data-mb-next="<?= $microblogPage['next'] === null ? '' : (int) $microblogPage['next'] ?>">
            <?= $microblogPage['html'] ?>
        </div>
        <div class="mb-loadmore"<?= $microblogPage['next'] === null ? ' hidden' : '' ?>><button class="mb-loadmore__btn" type="button" data-mb-loadmore>Cargar más</button></div>
    </div>
</section>
<?php endif; endif; ?>
