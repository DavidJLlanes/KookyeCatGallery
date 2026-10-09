<?php
declare(strict_types=1);

/**
 * Localiza la carpeta privada de esta instalación.
 * Prioridad: variable del hosting, hermana de la raíz pública (../config), y var/ como último recurso.
 */
function gallery_private_directory(bool $create = false): string
{
    $configured = trim((string) (getenv('GALLERY_PRIVATE_DIR') ?: ''));
    if ($configured !== '') {
        $path = rtrim($configured, '/\\');
        if ($create && !is_dir($path)) @mkdir($path, 0750, true);
        return $path;
    }

    // Estructura típica de hosting compartido: cuenta/config junto a cuenta/public_html.
    $outside = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config';
    if (is_dir($outside)) return $outside;
    $accountRoot = dirname($outside);
    if (is_writable($accountRoot)) {
        if (!$create || (@mkdir($outside, 0750) && is_dir($outside))) return $outside;
    }

    // Fallback para hostings que impiden escribir fuera del document root.
    $fallback = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var';
    if ($create && !is_dir($fallback)) @mkdir($fallback, 0750, true);
    return $fallback;
}

function gallery_public_url(): string
{
    $configured = trim((string) (getenv('GALLERY_PUBLIC_URL') ?: ''));
    if ($configured !== '') return rtrim($configured, '/');

    $urlFile = gallery_private_directory() . DIRECTORY_SEPARATOR . 'public-url.txt';
    $stored = is_file($urlFile) ? trim((string) @file_get_contents($urlFile)) : '';
    return $stored !== '' ? rtrim($stored, '/') : 'https://example.com';
}
