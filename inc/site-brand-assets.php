<?php
declare(strict_types=1);

/**
 * Genera los archivos públicos de marca a partir de las imágenes que sube el administrador.
 * Los originales se procesan con GD y se descartan; se quitan así los metadatos EXIF.
 */
function site_brand_decode_image(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
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
    return [$image, (int) $width, (int) $height];
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
 * Crea iconos cuadrados de favicon, Apple Touch y PWA, y la imagen social 1200×630.
 * La raíz se puede sustituir en pruebas para no escribir en los archivos de la aplicación.
 */
function site_brand_assets_process(array $files, ?string $rootDir = null): array
{
    $rootDir ??= dirname(__DIR__);
    $icon = $files['app_icon_file'] ?? null;
    if (is_array($icon) && (int) ($icon['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$source, $width, $height] = site_brand_upload_image($icon, 'el icono de la aplicación');
        try {
            if (min($width, $height) < 512 || abs($width - $height) > max($width, $height) * 0.02) {
                throw new RuntimeException('El icono debe ser cuadrado y medir al menos 512 × 512 px.');
            }
            $directory = $rootDir . '/assets/icons';
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('No se pudo preparar la carpeta de iconos.');
            }
            foreach ([32, 180, 192, 512] as $size) {
                $output = site_brand_render_crop($source, $width, $height, $size, $size, true);
                $destination = $directory . '/site-app-icon-' . $size . '.png';
                $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
                try {
                    if (!imagepng($output, $temporary, 8) || !@rename($temporary, $destination)) {
                        throw new RuntimeException('No se pudo generar el icono de ' . $size . ' px.');
                    }
                    @chmod($destination, 0644);
                } finally {
                    imagedestroy($output);
                    if (is_file($temporary)) @unlink($temporary);
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
            $output = site_brand_render_crop($source, $width, $height, 1200, 630, false);
            $directory = $rootDir . '/assets/img';
            if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
                imagedestroy($output);
                throw new RuntimeException('No se pudo preparar la carpeta de imágenes.');
            }
            $destination = $directory . '/site-social-image.jpg';
            $temporary = $destination . '.tmp-' . bin2hex(random_bytes(6));
            try {
                if (!imagejpeg($output, $temporary, 88) || !@rename($temporary, $destination)) {
                    throw new RuntimeException('No se pudo generar la imagen para redes sociales.');
                }
                @chmod($destination, 0644);
            } finally {
                imagedestroy($output);
                if (is_file($temporary)) @unlink($temporary);
            }
        } finally {
            imagedestroy($source);
        }
    }
}

/** URL con versión de caché para una variante de icono personalizada o predeterminada. */
function site_brand_icon_url(int $size): string
{
    $root = dirname(__DIR__);
    $custom = $root . '/assets/icons/site-app-icon-' . $size . '.png';
    $fallbacks = [
        32 => '/assets/icons/favicon-32.png',
        180 => '/assets/icons/apple-touch-icon.png',
        192 => '/assets/icons/pwa-icon-192.webp',
        512 => '/assets/icons/pwa-icon-512.webp',
    ];
    $path = is_file($custom) ? '/assets/icons/site-app-icon-' . $size . '.png' : ($fallbacks[$size] ?? $fallbacks[512]);
    $absolute = $root . $path;
    return $path . (is_file($absolute) ? '?v=' . (string) filemtime($absolute) : '');
}

/** URL de la imagen Open Graph personalizada, si se ha subido. */
function site_brand_og_url(): string
{
    $path = dirname(__DIR__) . '/assets/img/site-social-image.jpg';
    return is_file($path) ? '/assets/img/site-social-image.jpg?v=' . (string) filemtime($path) : '';
}
