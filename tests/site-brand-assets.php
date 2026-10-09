<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/site-brand-assets.php';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD no está disponible.\n");
    exit(1);
}

$root = sys_get_temp_dir() . '/site-brand-test-' . bin2hex(random_bytes(6));
mkdir($root, 0755, true);

// A settings save without icon uploads must be a safe no-op.
site_brand_assets_process([], $root);
$iconPath = $root . '/icon.png';
$socialPath = $root . '/social.png';
$icon = imagecreatetruecolor(512, 512);
imagealphablending($icon, false);
imagesavealpha($icon, true);
imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 32, 80, 120, 0));
imagepng($icon, $iconPath);
imagedestroy($icon);
$social = imagecreatetruecolor(1200, 630);
imagefill($social, 0, 0, imagecolorallocate($social, 120, 40, 30));
imagepng($social, $socialPath);
imagedestroy($social);

site_brand_assets_process([
    'app_icon_file' => ['error' => UPLOAD_ERR_OK, 'tmp_name' => $iconPath, 'size' => filesize($iconPath)],
    'og_image_file' => ['error' => UPLOAD_ERR_OK, 'tmp_name' => $socialPath, 'size' => filesize($socialPath)],
], $root);

foreach ([32, 180, 192, 512] as $size) {
    $image = $root . '/assets/icons/site-app-icon-' . $size . '.png';
    $dimensions = is_file($image) ? getimagesize($image) : false;
    if (!$dimensions || $dimensions[0] !== $size || $dimensions[1] !== $size) {
        throw new RuntimeException('Variante de icono incorrecta: ' . $size);
    }
}
$og = getimagesize($root . '/assets/img/site-social-image.jpg');
if (!$og || $og[0] !== 1200 || $og[1] !== 630 || $og['mime'] !== 'image/jpeg') {
    throw new RuntimeException('La imagen Open Graph no se generó a 1200 × 630 JPEG.');
}
foreach (glob($root . '/assets/*/site-*') ?: [] as $file) unlink($file);
@rmdir($root . '/assets/icons');
@rmdir($root . '/assets/img');
@rmdir($root . '/assets');
@unlink($iconPath);
@unlink($socialPath);
@rmdir($root);
echo "OK: iconos 32/180/192/512 e imagen social 1200x630.\n";
