<?php
declare(strict_types=1);

// Imágenes de marca subidas desde el panel: icono, imagen social, foto de perfil y logo. Todo se guarda en uploads/ (nunca en la
// carpeta del código), reducido, en el formato y con la orientación que usa la web.

require_once __DIR__ . '/../inc/site-brand-assets.php';

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD no está disponible.\n");
    exit(1);
}

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function remove_tree(string $path): void
{
    if (!is_dir($path)) { @unlink($path); return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') remove_tree($path . '/' . $entry);
    }
    @rmdir($path);
}

$root = sys_get_temp_dir() . '/site-brand-test-' . bin2hex(random_bytes(6));
mkdir($root, 0755, true);

// A settings save without icon uploads must be a safe no-op.
site_brand_assets_process([], $root);
check(!is_dir($root . '/uploads'), 'Sin subidas no se crea nada.');

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
    $image = $root . '/uploads/icons/site-app-icon-' . $size . '.png';
    $dimensions = is_file($image) ? getimagesize($image) : false;
    check($dimensions && $dimensions[0] === $size && $dimensions[1] === $size, 'Variante de icono incorrecta en uploads/icons: ' . $size);
}
$og = getimagesize($root . '/uploads/site-social-image.jpg');
check($og && $og[0] === 1200 && $og[1] === 630 && $og['mime'] === 'image/jpeg', 'La imagen Open Graph no se generó a 1200 × 630 JPEG en uploads/.');
check(!is_dir($root . '/assets'), 'El código de la web (assets/) no se toca.');

// Foto de perfil enorme: se reduce a 1600 px, pasa a WebP y se guarda en uploads/.
$bigPath = $root . '/grande.jpg';
$big = imagecreatetruecolor(4000, 5000);
imagefill($big, 0, 0, imagecolorallocate($big, 90, 140, 200));
imagejpeg($big, $bigPath, 90);
imagedestroy($big);
file_put_contents($root . '/profile-photo-custom.jpg', 'versión antigua en la raíz');
$url = site_brand_store_image(['error' => UPLOAD_ERR_OK, 'tmp_name' => $bigPath, 'size' => filesize($bigPath)], 'profile-photo-custom', SITE_BRAND_PROFILE_MAX_SIDE, 'la foto de perfil', $root);
check($url === '/uploads/profile-photo-custom.webp', 'La ruta guardada apunta a uploads/: ' . $url);
$stored = getimagesize($root . '/uploads/profile-photo-custom.webp');
check($stored && $stored['mime'] === 'image/webp' && max($stored[0], $stored[1]) === 1600 && $stored[0] === 1280 && $stored[1] === 1600, 'La foto de perfil queda a 1280 × 1600 WebP.');
check(!is_file($root . '/profile-photo-custom.jpg'), 'La versión antigua de la raíz se retira.');
check(filesize($root . '/uploads/profile-photo-custom.webp') < filesize($bigPath), 'La copia guardada pesa menos que el original.');
check(!glob($root . '/uploads/*.tmp-*'), 'No quedan temporales.');

// Una imagen pequeña no se amplía; el logo conserva la transparencia.
$smallPath = $root . '/logo.png';
$small = imagecreatetruecolor(300, 300);
imagealphablending($small, false);
imagesavealpha($small, true);
imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
imagepng($small, $smallPath);
imagedestroy($small);
site_brand_store_image(['error' => UPLOAD_ERR_OK, 'tmp_name' => $smallPath, 'size' => filesize($smallPath)], 'site-logo-custom', SITE_BRAND_LOGO_MAX_SIDE, 'el logo', $root);
$logo = getimagesize($root . '/uploads/site-logo-custom.webp');
check($logo && $logo[0] === 300 && $logo[1] === 300, 'El logo pequeño no se amplía.');
$logoImage = imagecreatefromwebp($root . '/uploads/site-logo-custom.webp');
check(((imagecolorat($logoImage, 10, 10) >> 24) & 0x7F) > 100, 'El logo conserva la transparencia.');
imagedestroy($logoImage);

// Un archivo que no es imagen se rechaza.
file_put_contents($root . '/no-imagen.png', '<?php echo 1;');
try {
    site_brand_store_image(['error' => UPLOAD_ERR_OK, 'tmp_name' => $root . '/no-imagen.png', 'size' => 13], 'site-logo-custom', 1024, 'el logo', $root);
    check(false, 'Un archivo que no es imagen debe rechazarse.');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'Formato'), 'Mensaje de formato no admitido.');
}

// Sin permiso de escritura en uploads/ (aquí: existe un archivo con ese nombre) el mensaje dice qué hacer, con el script del servidor.
$blocked = $root . '/bloqueado';
mkdir($blocked, 0755, true);
file_put_contents($blocked . '/uploads', 'soy un archivo');
try {
    site_brand_store_image(['error' => UPLOAD_ERR_OK, 'tmp_name' => $smallPath, 'size' => filesize($smallPath)], 'site-logo-custom', 1024, 'el logo', $blocked);
    check(false, 'Sin carpeta uploads/ escribible debe fallar.');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'uploads/') && str_contains($error->getMessage(), 'preparar-servidor.sh'), 'El error indica la carpeta y el script que lo arregla: ' . $error->getMessage());
}

remove_tree($root);
echo "OK: iconos, imagen social, perfil y logo en uploads/ (reducidos, WebP, sin tocar el código).\n";
