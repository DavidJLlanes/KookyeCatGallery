<?php
/**
 * Bloque «Enlaces sociales» (contacto).
 *
 * Se activa con `show_social`. Su posición la fija `section_order`. También se muestra al final de la página de una foto (/foto/slug).
 *
 * Variables: $siteSettings
 * (las aporta page_block_render() desde $pageContext, definido en index.php).
 */
?>
<!-- CONTACTO -->
<?php if ($siteSettings['show_social']): ?>
<section class="contact" id="contacto" data-site-section="social">
    <div class="contact__glow" aria-hidden="true"></div>
    <div class="contact__inner">
        <span class="contact__eyebrow"><?= site_text_html('text_bdd8f61b94c52379') ?></span>
        <h2 class="contact__title"><?= site_text_html('text_49a006d539216e16') ?><br><?= site_text_html('text_f366ccf006a44b3f') ?></h2>
        <p class="contact__text"><?= site_text_html('text_ab3ceef210237f9d') ?></p>
        <div class="contact__links">
            <?php
            $socialNames = site_social_networks();
            foreach (($siteSettings['social_links'] ?? []) as $social):
                $network = (string) ($social['network'] ?? '');
                if (!isset($socialNames[$network]) || empty($social['url'])) continue;
                $logoClass = $network === 'instagram' ? 'contact__logo--ig' : ($network === 'threads' ? 'contact__logo--threads' : '');
            ?>
            <a class="contact__link" href="<?= safe((string) $social['url']) ?>" target="_blank" rel="noopener">
                <span class="contact__icon">
                    <?php if ($logoClass !== ''): ?><span class="contact__logo <?= $logoClass ?>" aria-hidden="true"></span><?php else: ?><?= social_profile_icon($network) ?><?php endif; ?>
                </span>
                <span class="contact__net"><?= safe($socialNames[$network]) ?></span>
                <span class="contact__handle"><?= safe((string) (($social['handle'] ?? '') ?: $socialNames[$network])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
