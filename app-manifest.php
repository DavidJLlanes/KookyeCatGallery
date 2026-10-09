<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/site-settings.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$customIcon = is_file(__DIR__ . '/assets/icons/site-app-icon-192.png');
$icons = [];
foreach ([192, 512] as $size) {
    $icons[] = [
        'src' => site_brand_icon_url($size),
        'sizes' => $size . 'x' . $size,
        'type' => $customIcon ? 'image/png' : 'image/webp',
        'purpose' => 'any',
    ];
}
if ($customIcon) {
    $icons[] = [
        'src' => site_brand_icon_url(512),
        'sizes' => '512x512',
        'type' => 'image/png',
        'purpose' => 'maskable',
    ];
}
$name = site_title();
$manifest = [
    'name' => $name,
    'short_name' => mb_substr($name, 0, 12, 'UTF-8'),
    'id' => '/',
    'start_url' => '/?source=pwa',
    'scope' => '/',
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'background_color' => '#0a0a0a',
    'theme_color' => '#0a0a0a',
    'description' => $name,
    'lang' => 'es',
    'icons' => $icons,
];
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
