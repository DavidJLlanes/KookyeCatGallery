<?php
/**
 * Galería premium «Estilo Burbujas» (clave `bubbles`): marcado.
 *
 * Sirve a dos galerías premium: «Estilo Burbujas» (círculos, clave `bubbles`) y «Estilo Cuadrados» (cuadrados, clave `squares`);
 * la forma va en data-shape (`circle` o `square`).
 *
 * Una sola pantalla completa (100 % de ancho y de alto del dispositivo). Las fotos son círculos con un pequeño marco, de
 * distintos tamaños, repartidos al azar por toda la pantalla y flotando lentamente. Se paginan según «Fotos visibles a la
 * vez» (la única opción de la galería estándar que respeta). Comparte con «Estilo Baraja» la fijación en móvil, la parada
 * del scroll, los botones de salir, los filtros y la vuelta desde la ficha de una foto.
 * El comportamiento está en assets/premium/bubbles/bubbles.js y los estilos y animaciones en bubbles.css.
 *
 * Contrato con el JS y el CSS
 * ---------------------------
 *   #bubbles                   Raíz. data-total = número de fotos; data-shape = `circle` o `square`.
 *   .bubbles__field            Zona donde el JS reparte los círculos (deja sitio a las herramientas y a la paginación).
 *   .bubbles__item             Una burbuja (círculo o cuadrado) por foto, con los mismos data-* que las tarjetas estándar. El JS le pone su
 *                              posición y tamaño (left/top/width/height) y lo oculta si no está en la página actual.
 *   .bubbles__ui               Controles superpuestos: herramientas, paginación minimalista, salidas y pista.
 *   [data-bubbles-prev|next]   Página anterior / siguiente. [data-bubbles-current|total]: «1 / 7».
 *   [data-bubbles-exit]        Salir hacia abajo. [data-bubbles-exit-up]: salir hacia arriba (oculto si no hay nada encima).
 *   #favoritesToggle           Mismos id que en la galería estándar: main.js gestiona su estado.
 *   #slideshowStart
 *
 * Variables: $galleryItems, $author, $rootDir (las aporta page_block_render()).
 * Se incluye desde inc/blocks/gallery.php cuando hay fotos y esta galería premium está activa.
 */
$bubblesTotal = count($galleryItems);
// «Estilo Burbujas» (círculos) y «Estilo Cuadrados» comparten este marcado: la forma sale de la galería premium activa.
$bubblesShape = site_premium_gallery_active($siteSettings ?? null) === 'squares' ? 'square' : 'circle';
?>
<!-- GALERÍA PREMIUM · ESTILO BURBUJAS -->
<main id="galeria" class="gallery-section gallery-section--premium" data-premium-gallery="bubbles">
    <section class="bubbles" id="bubbles" role="region" aria-label="<?= site_text_html($bubblesShape === 'square' ? 'squares_label' : 'bubbles_label') ?>" data-shape="<?= $bubblesShape ?>" data-total="<?= $bubblesTotal ?>" tabindex="0">
        <span class="bubbles__glow bubbles__glow--a" aria-hidden="true"></span>
        <span class="bubbles__glow bubbles__glow--b" aria-hidden="true"></span>

        <div class="bubbles__field" data-bubbles-field>
            <?php foreach ($galleryItems as $i => $item): ?>
                <?php
                $hasSlug = !empty($item['slug']);
                $title   = (string) ($item['title'] ?? '');
                $alt     = $title !== '' ? $title : site_text('text_c7ea52a379d46ca5') . $author;
                $tag     = $hasSlug ? 'a' : 'button';
                ?>
                <article class="bubbles__item"
                         data-index="<?= $i ?>"
                         data-title="<?= safe($title) ?>"
                         data-description="<?= safe($item['description'] ?? '') ?>"
                         data-full="<?= safe(photo_asset_url($item['desktop'], $rootDir)) ?>"
                         data-category="<?= safe($item['category'] ?? '') ?>"
                         data-slug="<?= safe($item['slug'] ?? '') ?>"
                         data-lat="<?= $item['latitude'] ?? 0 ?>"
                         data-lng="<?= $item['longitude'] ?? 0 ?>"
                         <?= $i >= 12 ? 'hidden' : '' ?>>
                    <div class="bubbles__float">
                        <<?= $tag ?> class="bubbles__link"
                            <?php if ($hasSlug): ?>href="/foto/<?= safe($item['slug']) ?>"<?php else: ?>type="button"<?php endif; ?>
                            aria-label="<?= site_text_html('text_199d79e67ea29a83') ?><?= safe($title ?: site_text('text_08e81d4e64f6b4ff')) ?>">
                            <picture class="bubbles__picture">
                                <source media="(max-width: 768px)" srcset="<?= safe(photo_asset_url($item['mobile'], $rootDir)) ?>" type="image/webp">
                                <img class="bubbles__img"
                                     src="<?= safe(photo_asset_url($item['desktop'], $rootDir)) ?>"
                                     alt="<?= safe($alt) ?>"
                                     loading="<?= $i < 12 ? 'eager' : 'lazy' ?>"
                                     decoding="async" draggable="false">
                            </picture>
                        </<?= $tag ?>>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="bubbles__empty" data-bubbles-empty hidden><?= site_text_html('bubbles_empty_filter') ?></p>

        <div class="bubbles__ui">
            <div class="bubbles__tools" aria-label="Herramientas de galería">
                <button type="button" class="gallery-app-tool" id="favoritesToggle" aria-pressed="false">♡ Favoritas</button>
                <button type="button" class="gallery-app-tool" id="slideshowStart">▶ Presentación</button>
            </div>

            <!-- Paginación minimalista: flechas finas y «1 / 7». Las flechas dobles salen de la galería hacia arriba o hacia abajo. -->
            <nav class="bubbles__pager" aria-label="Paginación">
                <button type="button" class="bubbles__exit" data-bubbles-exit-up hidden aria-label="<?= site_text_html('deck_exit_up_label') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 11l6-6 6 6M6 18l6-6 6 6"/></svg>
                </button>
                <button type="button" class="bubbles__arrow" data-bubbles-prev aria-label="<?= site_text_html('bubbles_prev') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
                </button>
                <p class="bubbles__counter" aria-live="polite"><span data-bubbles-current>1</span><span class="bubbles__counter-sep">/</span><span data-bubbles-total>1</span></p>
                <button type="button" class="bubbles__arrow" data-bubbles-next aria-label="<?= site_text_html('bubbles_next') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
                </button>
                <button type="button" class="bubbles__exit" data-bubbles-exit aria-label="<?= site_text_html('deck_exit_label') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l6 6 6-6M6 13l6 6 6-6"/></svg>
                </button>
            </nav>

            <p class="bubbles__hint" data-bubbles-hint>
                <span class="bubbles__hint--touch"><?= site_text_html('bubbles_hint_touch') ?></span>
                <span class="bubbles__hint--pointer"><?= site_text_html('bubbles_hint_wheel') ?></span>
            </p>
        </div>
    </section>
</main>
