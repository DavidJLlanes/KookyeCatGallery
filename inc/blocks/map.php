<?php
/**
 * Bloque «Mapa»: ubicación de las fotos.
 *
 * Se activa con `show_map`. Su posición la fija `section_order`.
 *
 * Variables: $categories, $siteSettings
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
<?php if ($siteSettings['show_map']): ?>
<section class="map-section" id="mapa" data-site-section="map" aria-labelledby="mapHeading">
    <div class="map-section__head">
        <span class="map-section__eyebrow"><?= site_text_html('text_d50e1dd2fb1b8143') ?></span>
        <h2 class="map-section__title" id="mapHeading"><?= site_text_html('text_525b8adbcb4f9654') ?></h2>
        <label class="map-filter" for="mapCategory">
            <span><?= site_text_html('text_b22780340ae5569f') ?></span>
            <select id="mapCategory" aria-label="<?= site_text_html('text_68b4717007382b83') ?>">
                <option value=""><?= site_text_html('text_425a839def0b84fd') ?></option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= safe($cat) ?>"><?= safe($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="map-filter__chevron" aria-hidden="true">⌄</span>
        </label>
    </div>
    <div class="map-container" id="mapContainer" data-reveal><div id="map" class="map"></div></div>
</section>
<?php endif; ?>
