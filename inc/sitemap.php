<?php
/**
 * Genera sitemap.xml automáticamente con todas las imágenes.
 * Usa la extensión Image de sitemap (https://www.sitemaps.org/protocol.html)
 * que Google interpreta para indexar imágenes en Google Images.
 *
 * Sólo regenera si hay cambios respecto al cache.
 */

declare(strict_types=1);

function generate_sitemap(string $baseDir, string $siteUrl, array $items): void
{
    $sitemapPath = $baseDir . '/sitemap.xml';
    $signaturePath = $baseDir . '/data/sitemap.sig';

    $signature = md5(json_encode(array_map(
        fn($i) => ($i['original'] ?? '') . '|' . ($i['mtime'] ?? 0) . '|' . ($i['slug'] ?? ''),
        $items
    )) ?: '');

    $previousSig = is_file($signaturePath) ? file_get_contents($signaturePath) : '';
    if ($previousSig === $signature && is_file($sitemapPath)) {
        return;
    }

    $lastmod = !empty($items) ? date('c', max(array_column($items, 'mtime') ?: [time()])) : date('c');

    $esc = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
    $imgXml = function (array $it) use ($siteUrl, $esc): string {
        $loc     = $esc($siteUrl . '/' . url_path($it['desktop']));
        $title   = $esc($it['title']       ?: 'Fotografía de gatos');
        $caption = $esc($it['description'] ?: 'Fotografía de gatos por Kookye Cat Gallery');
        return "      <image:image>\n"
             . "        <image:loc>{$loc}</image:loc>\n"
             . "        <image:title>{$title}</image:title>\n"
             . "        <image:caption>{$caption}</image:caption>\n"
             . "        <image:geo_location>la escena</image:geo_location>\n"
             . "      </image:image>\n";
    };

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    // Página principal (priority 1.0). Anida solo las imágenes que aún no tienen URL propia.
    $xml .= "  <url>\n";
    $xml .= "    <loc>" . $esc($siteUrl . '/') . "</loc>\n";
    $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
    $xml .= "    <changefreq>weekly</changefreq>\n";
    $xml .= "    <priority>1.0</priority>\n";
    foreach ($items as $it) {
        if (empty($it['slug'])) {
            $xml .= $imgXml($it);
        }
    }
    $xml .= "  </url>\n";

    // Una URL propia por cada foto con slug, con su imagen anidada
    foreach (array_slice($items, 0, 2000) as $it) {
        if (empty($it['slug'])) continue;
        $photoLastmod = !empty($it['mtime']) ? date('c', (int) $it['mtime']) : $lastmod;
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . $esc($siteUrl . '/foto/' . $it['slug']) . "</loc>\n";
        $xml .= "    <lastmod>{$photoLastmod}</lastmod>\n";
        $xml .= "    <changefreq>monthly</changefreq>\n";
        $xml .= "    <priority>0.8</priority>\n";
        $xml .= $imgXml($it);
        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";

    if (@file_put_contents($sitemapPath, $xml) !== false) {
        @file_put_contents($signaturePath, $signature);
    }
}
