<?php
/**
 * Galería premium «Estilo Tinder» (clave `swipe`): marcado.
 *
 * Una sola pantalla completa con una carta que se desliza a un lado, como en Tinder:
 *   → hacia el lado del CORAZÓN (derecha): da un corazón a la foto y pasa a la siguiente.
 *   ← hacia el otro lado (izquierda, la FLECHA): solo pasa a la siguiente.
 * En los dos casos se avanza a la siguiente foto. La interfaz de la baraja lo indica con un corazón y una flecha: los dos botones de la
 * barra inferior y los sellos que aparecen sobre la carta al arrastrarla. El comportamiento está en assets/premium/swipe/swipe.js; los
 * estilos de la interfaz, en assets/premium/deck/deck.css, y los de la carta, en assets/premium/swipe/swipe.css.
 *
 * Contrato con el JS y el CSS
 * ---------------------------
 *   #deck                      Raíz (misma clase e id que la baraja, así sirve su interfaz). data-layout="swipe".
 *   .deck__card                Una carta por foto, con los mismos data-* que las tarjetas estándar. El JS le pone data-pos
 *                              (0 = encima, 1..2 = asoman debajo, 3 = oculta, -1 = ya pasada).
 *   [data-heart-photo]         Botón de corazón OCULTO de cada carta: el JS lo pulsa al dar un corazón y main.js hace el resto.
 *   .swipe__stamp--like/--pass Sellos del corazón (derecha) y de la flecha (izquierda) sobre la carta.
 *   [data-swipe-like|pass]     Botones de la barra: corazón (da un corazón y pasa) y flecha (solo pasa).
 *   [data-swipe-end]           Aviso de «ya has visto todas las fotos» con [data-swipe-restart].
 *   [data-deck-exit], [data-deck-exit-up]   Botones de salir (como en la baraja).
 *
 * Variables: $galleryItems, $author, $rootDir (las aporta page_block_render()).
 * Se incluye desde inc/blocks/gallery.php cuando hay fotos y esta galería premium está activa.
 */
$swipeTotal = count($galleryItems);
?>
<!-- GALERÍA PREMIUM · ESTILO TINDER -->
<main id="galeria" class="gallery-section gallery-section--premium" data-premium-gallery="swipe">
    <section class="deck" id="deck" role="region" aria-roledescription="carrusel"
             aria-label="<?= site_text_html('swipe_label') ?>" data-layout="swipe" data-total="<?= $swipeTotal ?>" tabindex="0">

        <div class="deck__stage">
            <?php foreach ($galleryItems as $i => $item): ?>
                <?php
                $hasSlug  = !empty($item['slug']);
                $title    = (string) ($item['title'] ?? '');
                $aspect   = max(0.01, (float) ($item['aspect'] ?? 1.5));
                $alt      = $title !== '' ? $title : site_text('text_c7ea52a379d46ca5') . $author;
                $linkTag  = $hasSlug ? 'a' : 'button';
                ?>
                <article class="deck__card"
                         data-pos="<?= min($i, 3) ?>"
                         data-index="<?= $i ?>"
                         data-aspect="<?= number_format($aspect, 4, '.', '') ?>"
                         data-title="<?= safe($title) ?>"
                         data-description="<?= safe($item['description'] ?? '') ?>"
                         data-full="<?= safe(photo_asset_url($item['desktop'], $rootDir)) ?>"
                         data-category="<?= safe($item['category'] ?? '') ?>"
                         data-slug="<?= safe($item['slug'] ?? '') ?>"
                         data-lat="<?= $item['latitude'] ?? 0 ?>"
                         data-lng="<?= $item['longitude'] ?? 0 ?>"
                         role="group" aria-roledescription="diapositiva"
                         aria-label="<?= $i + 1 ?> / <?= $swipeTotal ?>">
                    <<?= $linkTag ?> class="deck__link"
                        <?php if ($hasSlug): ?>href="/foto/<?= safe($item['slug']) ?>"<?php else: ?>type="button"<?php endif; ?>
                        draggable="false"
                        aria-label="<?= site_text_html('text_199d79e67ea29a83') ?><?= safe($title ?: site_text('text_08e81d4e64f6b4ff')) ?>">
                        <picture class="deck__picture">
                            <source media="(max-width: 768px)" srcset="<?= safe(photo_asset_url($item['mobile'], $rootDir)) ?>" type="image/webp">
                            <img class="deck__img"
                                 src="<?= safe(photo_asset_url($item['desktop'], $rootDir)) ?>"
                                 alt="<?= safe($alt) ?>"
                                 loading="<?= $i < 3 ? 'eager' : 'lazy' ?>"
                                 decoding="async" draggable="false">
                        </picture>
                    </<?= $linkTag ?>>
                    <?php if ($hasSlug): ?>
                    <span class="card__heart-count deck__hearts" data-heart-display="<?= safe($item['slug']) ?>" aria-label="Corazones">♡ <span data-heart-count>0</span></span>
                    <!-- Corazón oculto: swipe.js lo pulsa al deslizar hacia el corazón y main.js guarda el corazón (contador, estado y servidor). -->
                    <button type="button" class="swipe__heart-btn" data-heart-photo="<?= safe($item['slug']) ?>" hidden aria-hidden="true" tabindex="-1">♡ <span data-heart-count>0</span></button>
                    <?php endif; ?>
                    <!-- Sellos: el corazón (derecha) y la flecha (izquierda). Se ven al arrastrar la carta hacia ese lado. -->
                    <span class="swipe__stamp swipe__stamp--like" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7.5-4.6-9.6-9.4C.9 8.2 2.6 4.5 6.2 4.5c2.1 0 3.8 1.2 5.8 3.3 2-2.1 3.7-3.3 5.8-3.3 3.6 0 5.3 3.7 3.8 7.1C19.5 16.4 12 21 12 21z"/></svg>
                    </span>
                    <span class="swipe__stamp swipe__stamp--pass" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12H5M11 6l-6 6 6 6"/></svg>
                    </span>
                    <div class="deck__caption">
                        <?php if ($title !== ''): ?><h3 class="deck__title"><?= safe($title) ?></h3><?php endif; ?>
                        <?php if (!empty($item['category'])): ?><p class="deck__category"><?= safe($item['category']) ?></p><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>

            <!-- Fin de las fotos. -->
            <div class="swipe__end" data-swipe-end>
                <p><?= site_text_html('swipe_end') ?></p>
                <button type="button" class="swipe__restart" data-swipe-restart><?= site_text_html('swipe_restart') ?></button>
            </div>
        </div>

        <p class="deck__empty" data-deck-empty hidden><?= site_text_html('deck_empty_filter') ?></p>

        <div class="deck__ui">
            <div class="deck__tools" aria-label="Herramientas de galería">
                <button type="button" class="gallery-app-tool" id="favoritesToggle" aria-pressed="false">♡ Favoritas</button>
                <button type="button" class="gallery-app-tool" id="slideshowStart">▶ Presentación</button>
            </div>

            <div class="deck__nav">
                <button type="button" class="deck__exit deck__exit--up" data-deck-exit-up hidden aria-label="<?= site_text_html('deck_exit_up_label') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 11l6-6 6 6M6 18l6-6 6 6"/></svg>
                </button>
                <span class="deck__nav-sep" aria-hidden="true"></span>
                <!-- La flecha (izquierda) y el corazón (derecha) indican hacia dónde deslizar y qué pasa. -->
                <button type="button" class="deck__arrow swipe__btn swipe__btn--pass" data-swipe-pass aria-label="<?= site_text_html('swipe_pass_label') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12H5M11 6l-6 6 6 6"/></svg>
                </button>
                <p class="deck__counter" aria-live="polite"><span data-deck-current>1</span><span class="deck__counter-sep">/</span><span data-deck-total><?= $swipeTotal ?></span></p>
                <button type="button" class="deck__arrow swipe__btn swipe__btn--like" data-swipe-like aria-label="<?= site_text_html('swipe_like_label') ?>">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21s-7.5-4.6-9.6-9.4C.9 8.2 2.6 4.5 6.2 4.5c2.1 0 3.8 1.2 5.8 3.3 2-2.1 3.7-3.3 5.8-3.3 3.6 0 5.3 3.7 3.8 7.1C19.5 16.4 12 21 12 21z"/></svg>
                </button>
                <span class="deck__nav-sep" aria-hidden="true"></span>
                <button type="button" class="deck__exit" data-deck-exit aria-label="<?= site_text_html('deck_exit_label') ?>">
                    <span class="deck__exit-text"><?= site_text_html('deck_exit') ?></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l6 6 6-6M6 13l6 6 6-6"/></svg>
                </button>
            </div>

            <p class="deck__hint" data-deck-hint>
                <span class="deck__hint--touch"><?= site_text_html('swipe_hint_touch') ?></span>
                <span class="deck__hint--pointer"><?= site_text_html('swipe_hint_wheel') ?></span>
            </p>
        </div>
    </section>
</main>
