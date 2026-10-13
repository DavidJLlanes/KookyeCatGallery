<?php
declare(strict_types=1);

// Procesador de imágenes: la versión de escritorio queda con el lado mayor en 1800 px como máximo y la de móvil con 800 de ancho
// y 1200 de alto como máximo, ambas en WebP al 80 %. Nunca se amplía. Las fotos generadas con una receta anterior se rehacen
// poco a poco (como mucho 4 por llamada) y las nuevas se procesan siempre.
// Uso: php tests/image-processor.php

require_once dirname(__DIR__) . '/inc/processor.php';

if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
    echo "Image processor tests skipped (GD con WebP no disponible).\n";
    exit(0);
}

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'image-processor-' . bin2hex(random_bytes(4));
foreach (['img', 'imagenes/desktop', 'imagenes/mobile', 'data'] as $folder) mkdir("$tmp/$folder", 0775, true);

function photo(string $path, int $w, int $h): void
{
    $image = imagecreatetruecolor($w, $h);
    imagefill($image, 0, 0, imagecolorallocate($image, 90, 120, 200));
    imagejpeg($image, $path, 85);
    imagedestroy($image);
}

function size(string $path): array
{
    $info = getimagesize($path);
    check($info !== false && $info[2] === IMAGETYPE_WEBP, "$path no es WebP.");
    return [$info[0], $info[1]];
}

photo("$tmp/img/ancha.jpg", 4000, 3000);
photo("$tmp/img/alta.jpg", 2000, 4000);
photo("$tmp/img/pequena.jpg", 640, 480);

$processor = new ImageProcessor($tmp);
$items = $processor->processAll();
check(count($items) === 3, 'Tres fotos procesadas.');
check(size("$tmp/imagenes/desktop/ancha.webp") === [1800, 1350], 'Apaisada: lado mayor 1800 en escritorio.');
check(size("$tmp/imagenes/desktop/alta.webp") === [900, 1800], 'Vertical: el lado mayor (el alto) queda en 1800, no solo el ancho.');
check(size("$tmp/imagenes/desktop/pequena.webp") === [640, 480], 'Las fotos pequeñas no se amplían.');
check(size("$tmp/imagenes/mobile/ancha.webp") === [800, 600], 'Móvil: 800 de ancho.');
check(size("$tmp/imagenes/mobile/alta.webp") === [600, 1200], 'Móvil vertical: el alto no pasa de 1200.');
$byName = array_column($items, null, 'original');
check($byName['alta.jpg']['desktop_w'] === 900 && $byName['alta.jpg']['desktop_h'] === 1800, 'Las medidas guardadas coinciden con las del archivo.');
// Calidad 80: un degradado se codifica más pequeño que a 88 (la receta anterior).
$noise = imagecreatetruecolor(1800, 1200);
mt_srand(7);
for ($i = 0; $i < 6000; $i++) imagefilledellipse($noise, mt_rand(0, 1800), mt_rand(0, 1200), mt_rand(10, 140), mt_rand(10, 140), imagecolorallocate($noise, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
imagewebp($noise, "$tmp/q80.webp", 80);
imagewebp($noise, "$tmp/q88.webp", 88);
check(filesize("$tmp/q80.webp") < filesize("$tmp/q88.webp"), 'La calidad 80 pesa menos que la 88.');
check(ImageProcessor::QUALITY === 80 && ImageProcessor::DESKTOP_MAX_SIDE === 1800, 'Límites de publicación: 1800 px y calidad 80.');

// Receta anterior: las fotos ya generadas se rehacen de 4 en 4 y las nuevas, al momento.
$cachePath = "$tmp/data/cache.json";
$cache = json_decode((string) file_get_contents($cachePath), true);
check(($cache['ancha.jpg']['recipe'] ?? '') !== '', 'La caché guarda la receta.');
for ($i = 1; $i <= 6; $i++) photo("$tmp/img/vieja$i.jpg", 3000, 2000);
(new ImageProcessor($tmp))->processAll();
$cache = json_decode((string) file_get_contents($cachePath), true);
foreach (array_keys($cache) as $name) unset($cache[$name]['recipe']);
file_put_contents($cachePath, json_encode($cache));
photo("$tmp/img/nueva.jpg", 3000, 2000);
(new ImageProcessor($tmp))->processAll();
$cache = json_decode((string) file_get_contents($cachePath), true);
$redone = count(array_filter($cache, static fn(array $entry): bool => ($entry['recipe'] ?? '') !== ''));
check(isset($cache['nueva.jpg']['recipe']), 'La foto nueva se procesa siempre.');
check($redone === 1 + 4, "Se rehacen 4 fotos antiguas más la nueva (hecho: $redone).");
(new ImageProcessor($tmp))->processAll();
(new ImageProcessor($tmp))->processAll();
$cache = json_decode((string) file_get_contents($cachePath), true);
check(count(array_filter($cache, static fn(array $entry): bool => ($entry['recipe'] ?? '') === '')) === 0, 'Tras unas llamadas todas las fotos tienen la receta actual.');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
    $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
}
@rmdir($tmp);
echo "Image processor tests passed.\n";
