<?php
/**
 * Galería premium «Estilo Baraja» (clave `deck`): marcado.
 *
 * Sirve a tres galerías premium: «Estilo Baraja» (clave `deck`, disposición `stack`), «Estilo Tambor» (clave `drum`) y «Estilo Polaroids»
 * (clave `polaroid`). Mismo marcado e interfaz; cambia solo el CSS que coloca las cartas (data-layout). En las Polaroids cada
 * carta lleva tres valores estables (--j1..--j3) para su desorden.
 *
 * Una sola pantalla completa (100 % de ancho y de alto del dispositivo) con las fotos apiladas como
 * una baraja de cartas. El comportamiento (rueda, deslizar, teclado, botones) está en
 * assets/premium/deck/deck.js y los estilos y la animación en assets/premium/deck/deck.css.
 *
 * Contrato con el JS y el CSS
 * ---------------------------
 *   #deck                      Raíz. data-total = número de fotos; data-layout = `stack` (baraja) o `drum` (tambor).
 *   .deck__card                Una carta por foto. El JS le pone data-pos (posición respecto a la carta
 *                              activa: 0 = encima, 1..3 = mazo, -1 = ya pasada) y el CSS la coloca en 3D.
 *   .deck__card[data-*]        category, slug, title… (los mismos data-* que las tarjetas estándar).
 *   .deck__ui                  Controles superpuestos: contador, flechas, botón «Salir», herramientas y pista.
 *   [data-deck-exit]           Botón «Salir»: baja la página hasta lo que sigue a la galería (deck.js, sección 9).
 *   [data-deck-exit-up]        Botón «Salir hacia arriba»: sube hasta lo que hay antes (oculto si no hay nada encima).
 *   #favoritesToggle           Mismos id que en la galería estándar: main.js gestiona su estado.
 *   #slideshowStart
 *
 * Variables: $galleryItems, $author, $rootDir (las aporta page_block_render()).
 * Se incluye desde inc/blocks/gallery.php cuando hay fotos y esta galería premium está activa.
 */
$deckTotal = count($galleryItems);
// «Estilo Baraja» (pila de cartas) y «Estilo Tambor» (cilindro 3D) comparten este marcado y la interfaz: la disposición sale de la
// galería premium activa (data-layout) y la coloca el CSS.
$deckActive = site_premium_gallery_active($siteSettings ?? null);
$deckKey = in_array($deckActive, ['drum', 'polaroid', 'coverflow'], true) ? $deckActive : 'deck';
// Valores «aleatorios» pero estables de cada foto (0-1), para el desorden de las Polaroids.
// Posición «oculta» de cada disposición (la que tienen las cartas lejanas): baraja 4, tambor 3, Polaroids 5. Si el marcado inicial pusiera
// a todas en una posición visible (antes de que el JS ordene las cartas), se verían decenas de fotos a la vez y se cargarían todas.
$deckHidden = ['deck' => 4, 'drum' => 3, 'polaroid' => 5, 'coverflow' => 3][$deckKey];
$deckJitter = static fn(int $i, int $salt): float => fmod(abs(sin(($i + 1) * 12.9898 + $salt * 78.233) * 43758.5453), 1.0);
?>
<!-- GALERÍA PREMIUM · <?= safe(site_premium_gallery_definitions()[$deckActive]['label'] ?? 'Estilo Baraja') ?> -->
<main id="galeria" class="gallery-section gallery-section--premium" data-premium-gallery="<?= $deckKey ?>">
    <section class="deck" id="deck" role="region" aria-roledescription="carrusel"
             aria-label="<?= site_text_html($deckKey === 'deck' ? 'deck_label' : $deckKey . '_label') ?>" data-layout="<?= $deckKey === 'deck' ? 'stack' : $deckKey ?>" data-total="<?= $deckTotal ?>" tabindex="0">

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
                         <?php if ($deckKey === 'coverflow'): ?>style="--coverflow-aspect:<?= number_format($aspect, 4, '.', '') ?>"<?php elseif ($deckKey === 'polaroid'): ?>style="--j1:<?= number_format($deckJitter($i, 1), 3, '.', '') ?>;--j2:<?= number_format($deckJitter($i, 2), 3, '.', '') ?>;--j3:<?= number_format($deckJitter($i, 3), 3, '.', '') ?>"<?php endif; ?>
                         data-pos="<?= min($i, $deckHidden) ?>"
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
                         aria-label="<?= $i + 1 ?> / <?= $deckTotal ?>">
                    <<?= $linkTag ?> class="deck__link"
                        <?php if ($hasSlug): ?>href="/foto/<?= safe($item['slug']) ?>"<?php else: ?>type="button"<?php endif; ?>
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
                    <?php endif; ?>
                    <div class="deck__caption">
                        <?php if ($title !== ''): ?><h3 class="deck__title"><?= safe($title) ?></h3><?php endif; ?>
                        <?php if (!empty($item['category'])): ?><p class="deck__category"><?= safe($item['category']) ?></p><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="deck__empty" data-deck-empty hidden><?= site_inline_text_html('deck_empty_filter') ?></p>

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
                <p class="deck__counter" aria-live="polite"><span data-deck-current>1</span><span class="deck__counter-sep">/</span><span data-deck-total><?= $deckTotal ?></span></p>
                <button type="button" class="deck__arrow" data-deck-next aria-label="<?= site_text_html('deck_next') ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                </button>
                <span class="deck__nav-sep" aria-hidden="true"></span>
                <!-- Salir del modo pantalla completa: baja la página hasta lo que sigue a la galería. -->
                <button type="button" class="deck__exit" data-deck-exit aria-label="<?= site_text_html('deck_exit_label') ?>">
                    <span class="deck__exit-text"><?= site_inline_text_html('deck_exit') ?></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l6 6 6-6M6 13l6 6 6-6"/></svg>
                </button>
            </div>

            <p class="deck__hint" data-deck-hint>
                <span class="deck__hint--touch"><?= site_inline_text_html('deck_hint_touch') ?></span>
                <span class="deck__hint--pointer"><?= site_inline_text_html('deck_hint_wheel') ?></span>
            </p>
        </div>
    </section>
</main>
