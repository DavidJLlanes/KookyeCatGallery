<?php
/**
 * Bloque «Galería»: introducción, fotos, herramientas y paginación.
 *
 * Bloque principal: siempre visible. Su posición la fija `section_order`. El JS (main.js) pagina y filtra las tarjetas `.card` de #masonry.
 *
 * Variables: $author, $galleryItems, $heroItem
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
<main id="galeria" class="gallery-section">


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
                data-full="<?= safe(photo_asset_url($item['desktop'], $rootDir)) ?>"
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
                        <source media="(max-width: 768px)" srcset="<?= safe(photo_asset_url($item['mobile'], $rootDir)) ?>" type="image/webp">
                        <img
                            class="card__img"
                            loading="lazy"
                            decoding="async"
                            src="<?= safe(photo_asset_url($item['desktop'], $rootDir)) ?>"
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
