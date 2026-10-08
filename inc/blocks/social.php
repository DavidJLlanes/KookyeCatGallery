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
            <a class="contact__link" href="<?= safe((string) $siteSettings['instagram_url']) ?>" target="_blank" rel="noopener">
                <span class="contact__icon">
                    <span class="contact__logo contact__logo--ig" aria-hidden="true"></span>
                </span>
                <span class="contact__net"><?= site_text_html('text_bad57ef7837c8e6b') ?></span>
                <span class="contact__handle"><?= site_text_html('text_3b20084d3d94b4ee') ?></span>
            </a>
            <a class="contact__link" href="<?= safe((string) $siteSettings['threads_url']) ?>" target="_blank" rel="noopener">
                <span class="contact__icon">
                    <span class="contact__logo contact__logo--threads" aria-hidden="true"></span>
                </span>
                <span class="contact__net"><?= site_text_html('text_3e42e385075b9b56') ?></span>
                <span class="contact__handle"><?= site_text_html('social_threads_user') ?></span>
            </a>
            <?php
            $socialNames = ['facebook'=>'Facebook','x'=>'X','youtube'=>'YouTube','tiktok'=>'TikTok','flickr'=>'Flickr','linkedin'=>'LinkedIn','pinterest'=>'Pinterest','500px'=>'500px','bluesky'=>'Bluesky','mastodon'=>'Mastodon'];
            foreach (($siteSettings['social_links'] ?? []) as $social):
                $network = (string) ($social['network'] ?? '');
                if (!isset($socialNames[$network]) || empty($social['url'])) continue;
            ?>
            <a class="contact__link" href="<?= safe((string) $social['url']) ?>" target="_blank" rel="noopener">
                <span class="contact__icon"><?= social_profile_icon($network) ?></span>
                <span class="contact__net"><?= safe($socialNames[$network]) ?></span>
                <span class="contact__handle"><?= safe((string) (($social['handle'] ?? '') ?: $socialNames[$network])) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
