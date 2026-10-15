<?php
declare(strict_types=1);

/**
 * Genera los archivos públicos de marca a partir de las imágenes que sube el administrador.
 * Los originales se procesan con GD y se descartan; se quitan así los metadatos EXIF y se corrige la orientación.
 *
 * Todo lo que sube el administrador vive en uploads/ (junto a img/, imagenes/, data/ y microblog/ es lo único de la web que PHP
 * necesita poder escribir): el código nunca se modifica, así que la web se puede actualizar sin tocar lo subido.
 *   uploads/profile-photo-custom.webp   foto de perfil (lado mayor ≤ 1600 px)
 *   uploads/site-logo-custom.webp       logo (lado mayor ≤ 1024 px, conserva transparencia)
 *   uploads/icons/site-app-icon-N.png   icono de la aplicación (32, 180, 192 y 512 px)
 *   uploads/site-social-image.jpg       imagen para redes (1200 × 630)
 * Las versiones anteriores de la web guardaban estos archivos en la raíz, en assets/icons/ y en assets/img/: se siguen leyendo.
 */

/** Lado mayor máximo de la foto de perfil y del logo guardados. */
const SITE_BRAND_PROFILE_MAX_SIDE = 1600;
const SITE_BRAND_LOGO_MAX_SIDE = 1024;

/** Carpeta pública de imágenes de marca subidas. */
function site_uploads_dir(?string $rootDir = null): string
{
    return ($rootDir ?? dirname(__DIR__)) . '/uploads';
}

/** Crea (si hace falta) una carpeta de uploads y comprueba que PHP puede escribir en ella; si no, dice cómo arreglarlo. */
function site_brand_prepare_dir(string $directory): void
{
    $fix = ' En el servidor ejecuta «sudo sh scripts/preparar-servidor.sh» (deja todas las carpetas con los permisos correctos) y vuelve a intentarlo.';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('PHP no puede crear la carpeta uploads/.' . $fix);
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('PHP no puede escribir en la carpeta uploads/.' . $fix);
    }
}

function site_brand_decode_image(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

/** Gira la imagen según la orientación EXIF de la foto original (las fotos de móvil llegan «tumbadas» con los píxeles sin girar). */
function site_brand_orient($image, string $path, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) return $image;
    $exif = @exif_read_data($path);
    $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    $angle = match ($orientation) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
    if ($angle === 0) return $image;
    $rotated = imagerotate($image, $angle, 0);
    if (!$rotated) return $image;
    imagedestroy($image);
    return $rotated;
}

function site_brand_upload_image(array $file, string $label, int $maxBytes = 15728640): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException($error === UPLOAD_ERR_NO_FILE ? 'Selecciona ' . $label . '.' : 'No se pudo recibir ' . $label . '.');
    }
    $path = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > $maxBytes || !is_file($path)) {
        throw new RuntimeException($label . ' debe pesar como máximo 15 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    $dimensions = @getimagesize($path);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true) || !is_array($dimensions)) {
        throw new RuntimeException('Formato de imagen no admitido. Usa JPG, PNG o WebP.');
    }
    [$width, $height] = $dimensions;
    if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 40000000) {
        throw new RuntimeException($label . ' tiene unas dimensiones no admitidas.');
    }
    $image = site_brand_decode_image($path, (string) $mime);
    if (!$image) throw new RuntimeException('No se pudo leer ' . $label . '.');
    $image = site_brand_orient($image, $path, (string) $mime);
    return [$image, imagesx($image), imagesy($image)];
}

/** Escribe un archivo de forma atómica (temporal + renombrado) con los permisos de un archivo público. */
function site_brand_write(string $destination, callable $encode, string $what): void
{
    $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
    try {
        if (!$encode($temporary) || !@rename($temporary, $destination)) {
            throw new RuntimeException('No se pudo guardar ' . $what . '. Comprueba los permisos de la carpeta uploads/.');
        }
        @chmod($destination, 0644);
    } finally {
        if (is_file($temporary)) @unlink($temporary);
    }
}

/** Reduce una imagen para que su lado mayor no pase de $maxSide (nunca la amplía) conservando la transparencia. */
function site_brand_scale_down($source, int $width, int $height, int $maxSide)
{
    $scale = min(1.0, $maxSide / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $canvas = imagecreatetruecolor($newWidth, $newHeight);
    if (!$canvas) throw new RuntimeException('No se pudo preparar la imagen.');
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    return $canvas;
}

/**
 * Guarda la foto de perfil o el logo subidos: los reduce al tamaño que usa la web, los pasa a WebP (80 %), corrige la orientación
 * y quita los metadatos. Devuelve la ruta pública para guardarla en los ajustes. Retira las versiones anteriores (también las
 * que guardaban las versiones antiguas en la raíz de la web).
 *
 * @param array<string, mixed> $file elemento de $_FILES
 */
function site_brand_store_image(array $file, string $stem, int $maxSide, string $label, ?string $rootDir = null): string
{
    $rootDir ??= dirname(__DIR__);
    [$source, $width, $height] = site_brand_upload_image($file, $label);
    try {
        $directory = site_uploads_dir($rootDir);
        site_brand_prepare_dir($directory);
        $output = site_brand_scale_down($source, $width, $height, $maxSide);
        try {
            site_brand_write($directory . '/' . $stem . '.webp', static fn(string $path): bool => imagewebp($output, $path, 80), $label);
        } finally {
            imagedestroy($output);
        }
    } finally {
        imagedestroy($source);
    }
    foreach (['jpg', 'png', 'webp'] as $extension) {
        $legacy = $rootDir . '/' . $stem . '.' . $extension;
        if (is_file($legacy)) @unlink($legacy);
    }
    return '/uploads/' . $stem . '.webp';
}

/** Recorta centrado y escala una imagen a un lienzo con proporción fija. */
function site_brand_render_crop($source, int $sourceWidth, int $sourceHeight, int $width, int $height, bool $transparent)
{
    $canvas = imagecreatetruecolor($width, $height);
    if (!$canvas) throw new RuntimeException('No se pudo preparar una imagen de marca.');
    if ($transparent) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $clear);
    } else {
        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);
    }
    $scale = max($width / $sourceWidth, $height / $sourceHeight);
    $cropWidth = (int) ceil($width / $scale);
    $cropHeight = (int) ceil($height / $scale);
    $sourceX = (int) floor(($sourceWidth - $cropWidth) / 2);
    $sourceY = (int) floor(($sourceHeight - $cropHeight) / 2);
    imagecopyresampled($canvas, $source, 0, 0, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);
    return $canvas;
}

/**
 * Crea iconos cuadrados de favicon, Apple Touch y PWA, y la imagen social 1200×630 en uploads/.
 * La raíz se puede sustituir en pruebas para no escribir en los archivos de la aplicación.
 */
function site_brand_assets_process(array $files, ?string $rootDir = null): void
{
    $rootDir ??= dirname(__DIR__);
    $icon = $files['app_icon_file'] ?? null;
    if (is_array($icon) && (int) ($icon['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$source, $width, $height] = site_brand_upload_image($icon, 'el icono de la aplicación');
        try {
            if (min($width, $height) < 512 || abs($width - $height) > max($width, $height) * 0.02) {
                throw new RuntimeException('El icono debe ser cuadrado y medir al menos 512 × 512 px.');
            }
            $directory = site_uploads_dir($rootDir) . '/icons';
            site_brand_prepare_dir(site_uploads_dir($rootDir));
            site_brand_prepare_dir($directory);
            foreach ([32, 180, 192, 512] as $size) {
                $output = site_brand_render_crop($source, $width, $height, $size, $size, true);
                try {
                    site_brand_write($directory . '/site-app-icon-' . $size . '.png', static fn(string $path): bool => imagepng($output, $path, 8), 'el icono de ' . $size . ' px');
                } finally {
                    imagedestroy($output);
                }
            }
        } finally {
            imagedestroy($source);
        }
    }

    $social = $files['og_image_file'] ?? null;
    if (is_array($social) && (int) ($social['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$source, $width, $height] = site_brand_upload_image($social, 'la imagen para redes sociales');
        try {
            if ($width < 600 || $height < 315) {
                throw new RuntimeException('La imagen para redes sociales debe medir al menos 600 × 315 px; se recomienda 1200 × 630 px.');
            }
            $directory = site_uploads_dir($rootDir);
            site_brand_prepare_dir($directory);
            $output = site_brand_render_crop($source, $width, $height, 1200, 630, false);
            try {
                site_brand_write($directory . '/site-social-image.jpg', static fn(string $path): bool => imagejpeg($output, $path, 88), 'la imagen para redes sociales');
            } finally {
                imagedestroy($output);
            }
        } finally {
            imagedestroy($source);
        }
    }
}

/** Tipo MIME de un icono según su extensión. */
function site_brand_icon_type(string $url): string
{
    return match (strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION))) {
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        default => 'image/png',
    };
}

/** Ruta pública del icono propio de un tamaño (uploads/ o, en instalaciones antiguas, assets/icons/), o null si no se ha subido. */
function site_brand_custom_icon(int $size): ?string
{
    $root = dirname(__DIR__);
    foreach (['/uploads/icons/', '/assets/icons/'] as $folder) {
        if (is_file($root . $folder . 'site-app-icon-' . $size . '.png')) return $folder . 'site-app-icon-' . $size . '.png';
    }
    return null;
}

/** ¿Se ha subido un icono propio? */
function site_brand_has_custom_icon(): bool
{
    return site_brand_custom_icon(192) !== null;
}

/** URL con versión de caché para una variante de icono personalizada o predeterminada. */
function site_brand_icon_url(int $size): string
{
    $root = dirname(__DIR__);
    $fallbacks = [
        32 => '/assets/icons/favicon-32.png',
        180 => '/assets/icons/apple-touch-icon.png',
        192 => '/assets/icons/pwa-icon-192.webp',
        512 => '/assets/icons/pwa-icon-512.webp',
    ];
    $path = site_brand_custom_icon($size) ?? ($fallbacks[$size] ?? $fallbacks[512]);
    // Sin icono propio ni iconos predeterminados de la instalación, el del núcleo.
    if (!is_file($root . $path)) $path = '/favicon.svg';
    $absolute = $root . $path;
    return $path . (is_file($absolute) ? '?v=' . (string) filemtime($absolute) : '');
}

/** URL de la imagen Open Graph personalizada, si se ha subido. */
function site_brand_og_url(): string
{
    $root = dirname(__DIR__);
    foreach (['/uploads/site-social-image.jpg', '/assets/img/site-social-image.jpg'] as $path) {
        if (is_file($root . $path)) return $path . '?v=' . (string) filemtime($root . $path);
    }
    return '';
}
