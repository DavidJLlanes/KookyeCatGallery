<?php
/**
 * Bloque «Categorías»: filtro por categoría de la galería.
 *
 * Se activa con `show_categories` y solo se pinta si hay fotos y categorías. Su posición la fija `section_order`.
 *
 * Variables: $categories, $heroItem, $siteSettings
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
    <?php if ($siteSettings['show_categories'] && $heroItem && !empty($categories)): ?>
    <!-- SELECTOR DE CATEGORÍAS -->
    <section class="categories-block" aria-labelledby="categoriesTitle">
    <h2 class="categories-block__title" id="categoriesTitle" data-reveal><?= site_inline_text_html('categories_title') ?></h2>
    <div class="categories-filter" id="categoriesFilter" data-site-section="categories" data-reveal>
        <div class="categories-filter__inner">
            <button class="categories-filter__chip is-active" data-category=""><?= site_text_html('text_aff4d19d6ee43b20') ?></button>
            <?php foreach ($categories as $cat): ?>
            <button class="categories-filter__chip" data-category="<?= safe($cat) ?>"><?= safe($cat) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    </section>
    <?php endif; ?>
