<?php
/**
 * Galería premium «Estilo Cilindro» (clave `cylinder`): marcado.
 *
 * Una sola pantalla completa (100 % de ancho y de alto del dispositivo) con las fotos de distintos tamaños colocadas en varias filas
 * alrededor de un cilindro giratorio. Lleva la MISMA interfaz que «Estilo Baraja»: herramientas (Favoritas y Presentación), barra
 * con flechas y contador, botones «Salir» (abajo y arriba) y pista. La geometría se calcula y se pinta en
 * assets/premium/cylinder/cylinder.js (ver su cabecera); los estilos de las fotos están en cylinder.css y los de la interfaz en
 * assets/premium/deck/deck.css (se cargan los dos).
 *
 * Contrato con el JS y el CSS
 * ---------------------------
 *   #deck                      Raíz (la misma clase y el mismo id que en la baraja, así sirve su interfaz). data-layout="cylinder".
 *   [data-cyl-stage]           Zona donde el JS coloca las fotos (deja sitio a las herramientas y a la barra de abajo).
 *   .cyl__item                 Una foto, con los mismos data-* que las tarjetas estándar. El JS le pone tamaño y transformación 3D.
 *   .deck__ui y su contenido   Interfaz de la baraja: [data-deck-prev|next|current|total], [data-deck-exit], [data-deck-exit-up].
 *   #favoritesToggle           Mismos id que en la galería estándar: main.js gestiona su estado.
 *   #slideshowStart
 *
 * Variables: $galleryItems, $author, $rootDir (las aporta page_block_render()).
 * Se incluye desde inc/blocks/gallery.php cuando hay fotos y esta galería premium está activa.
 */
$cylinderTotal = count($galleryItems);
?>
<!-- GALERÍA PREMIUM · ESTILO CILINDRO -->
<main id="galeria" class="gallery-section gallery-section--premium" data-premium-gallery="cylinder">
    <section class="deck" id="deck" role="region" aria-roledescription="carrusel"
             aria-label="<?= site_text_html('cylinder_label') ?>" data-layout="cylinder" data-total="<?= $cylinderTotal ?>" tabindex="0">

        <div class="cyl__stage" data-cyl-stage>
            <?php foreach ($galleryItems as $i => $item): ?>
                <?php
                $hasSlug = !empty($item['slug']);
                $title   = (string) ($item['title'] ?? '');
                $alt     = $title !== '' ? $title : site_text('text_c7ea52a379d46ca5') . $author;
                $tag     = $hasSlug ? 'a' : 'button';
                ?>
                <article class="cyl__item"
                         data-index="<?= $i ?>"
                         data-title="<?= safe($title) ?>"
                         data-description="<?= safe($item['description'] ?? '') ?>"
                         data-full="<?= safe(photo_asset_url($item['desktop'], $rootDir)) ?>"
                         data-category="<?= safe($item['category'] ?? '') ?>"
                         data-slug="<?= safe($item['slug'] ?? '') ?>"
                         data-lat="<?= $item['latitude'] ?? 0 ?>"
                         data-lng="<?= $item['longitude'] ?? 0 ?>"
                         style="display:none">
                    <<?= $tag ?> class="cyl__link"
                        <?php if ($hasSlug): ?>href="/foto/<?= safe($item['slug']) ?>"<?php else: ?>type="button"<?php endif; ?>
                        aria-label="<?= site_text_html('text_199d79e67ea29a83') ?><?= safe($title ?: site_text('text_08e81d4e64f6b4ff')) ?>">
                        <picture class="cyl__picture">
                            <source media="(max-width: 768px)" srcset="<?= safe(photo_asset_url($item['mobile'], $rootDir)) ?>" type="image/webp">
                            <img class="cyl__img"
                                 src="<?= safe(photo_asset_url($item['desktop'], $rootDir)) ?>"
                                 alt="<?= safe($alt) ?>"
                                 loading="<?= $i < 12 ? 'eager' : 'lazy' ?>"
                                 decoding="async" draggable="false">
                        </picture>
                    </<?= $tag ?>>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="deck__empty" data-deck-empty hidden><?= site_text_html('deck_empty_filter') ?></p>

        <div class="deck__ui">
            <div class="deck__tools" aria-label="Herramientas de galería">
                <button type="button" class="gallery-app-tool" id="favoritesToggle" aria-pressed="false">♡ Favoritas</button>
                <button type="button" class="gallery-app-tool" id="slideshowStart">▶ Presentación</button>
            </div>

            <div class="deck__nav">
                <!-- Salir hacia arriba: sube hasta lo que hay antes de la galería (el JS lo oculta si no hay nada encima). -->
                <button type="button" class="deck__exit deck__exit--up" data-deck-exit-up hidden aria-label="<?= site_text_html('deck_exit_up_label') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 11l6-6 6 6M6 18l6-6 6 6"/></svg>
                </button>
                <span class="deck__nav-sep" aria-hidden="true"></span>
                <button type="button" class="deck__arrow" data-deck-prev aria-label="<?= site_text_html('deck_prev') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                </button>
                <p class="deck__counter" aria-live="polite"><span data-deck-current>1</span><span class="deck__counter-sep">/</span><span data-deck-total><?= $cylinderTotal ?></span></p>
                <button type="button" class="deck__arrow" data-deck-next aria-label="<?= site_text_html('deck_next') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                </button>
                <span class="deck__nav-sep" aria-hidden="true"></span>
                <!-- Salir del modo pantalla completa: baja la página hasta lo que sigue a la galería. -->
                <button type="button" class="deck__exit" data-deck-exit aria-label="<?= site_text_html('deck_exit_label') ?>">
                    <span class="deck__exit-text"><?= site_text_html('deck_exit') ?></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l6 6 6-6M6 13l6 6 6-6"/></svg>
                </button>
            </div>

            <p class="deck__hint" data-deck-hint>
                <span class="deck__hint--touch"><?= site_text_html('deck_hint_touch') ?></span>
                <span class="deck__hint--pointer"><?= site_text_html('deck_hint_wheel') ?></span>
            </p>
        </div>
    </section>
</main>
