<?php
declare(strict_types=1);

/**
 * Colecciones de la galería: metadatos públicos (descripción y portada) guardados en la configuración privada.
 * (Cargado por inc/site-settings.php.)
 */

/**
 * Metadatos públicos de colecciones, almacenados en la configuración privada.
 */
function gallery_collections_path(): string
{
    return site_config_dir() . '/collections.json';
}

function gallery_collections_load(): array
{
    $path = gallery_collections_path();
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) return [];
    $out = [];
    foreach ($decoded as $name => $data) {
        if (!is_string($name) || !is_array($data)) continue;
        $name = trim($name);
        $description = trim((string) ($data['description'] ?? ''));
        $cover = trim((string) ($data['cover'] ?? ''));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 64
            || mb_strlen($description, 'UTF-8') > 280
            || preg_match('/[\\x00-\\x1F\\x7F]/u', $name . $description)
            || ($cover !== '' && (basename($cover) !== $cover || !preg_match('/\\.(?:jpe?g|png)$/i', $cover)))) { continue; }
        $out[$name] = ['description' => $description, 'cover' => $cover, 'manual' => !empty($data['manual'])];
    }
    return $out;
}

function gallery_collection_save(string $name, string $description, string $cover, array $photos): void
{
    $name = trim($name);
    $description = trim($description);
    $cover = trim($cover);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 64
        || mb_strlen($description, 'UTF-8') > 280
        || preg_match('/[\\x00-\\x1F\\x7F]/u', $name . $description)) {
        throw new RuntimeException('Revisa el nombre y la descripción de la colección.');
    }
    if ($cover !== '') {
        $validCover = false;
        foreach ($photos as $photo) {
            $photoCategory = trim((string) ($photo['category'] ?? ''));
            if (($photoCategory !== '' ? $photoCategory : 'Sin categoría') === $name
                && hash_equals((string) ($photo['filename'] ?? ''), $cover)) {
                $validCover = managedPhotoPath($cover) !== null;
                break;
            }
        }
        if (!$validCover) throw new RuntimeException('La portada debe ser una fotografía de esta colección.');
    }
    $collections = gallery_collections_load();
    $manual = !empty($collections[$name]['manual']);
    $collections[$name] = ['description' => $description, 'cover' => $cover, 'manual' => $manual];
    gallery_collections_write($collections);
}

function gallery_collection_create(string $name): void
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 64 || preg_match('/[\\x00-\\x1F\\x7F]/u', $name)) {
        throw new RuntimeException('Escribe un nombre de categoría válido (hasta 64 caracteres).');
    }
    $collections = gallery_collections_load();
    if (isset($collections[$name])) throw new RuntimeException('Ya existe una categoría con ese nombre.');
    $collections[$name] = ['description' => '', 'cover' => '', 'manual' => true];
    gallery_collections_write($collections);
}

function gallery_collection_delete_empty(string $name, array $photos): void
{
    $name = trim($name);
    foreach ($photos as $photo) {
        $category = trim((string) ($photo['category'] ?? ''));
        if (($category !== '' ? $category : 'Sin categoría') === $name) {
            throw new RuntimeException('La categoría ya tiene fotografías y no se puede eliminar desde aquí.');
        }
    }
    $collections = gallery_collections_load();
    if (!isset($collections[$name]) || empty($collections[$name]['manual'])) {
        throw new RuntimeException('No se encontró una categoría vacía para eliminar.');
    }
    unset($collections[$name]);
    gallery_collections_write($collections);
}

function gallery_collections_write(array $collections): void
{
    $path = gallery_collections_path();
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo crear la carpeta privada de configuración.');
    }
    $json = json_encode($collections, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $temporary = tempnam($directory, '.collections-');
    if ($temporary === false) throw new RuntimeException('No se pudo preparar el guardado de colecciones.');
    try {
        if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !@chmod($temporary, 0640) || is_link($path) || !@rename($temporary, $path)) {
            throw new RuntimeException('No se pudieron guardar los datos de las colecciones.');
        }
    } finally {
        if (is_file($temporary)) @unlink($temporary);
    }
}

/**
 * Conserva metadatos válidos tras renombrar o eliminar una categoría.
 */
function gallery_collections_sync_categories(array $photos): void
{
    $present = [];
    foreach ($photos as $photo) {
        $name = trim((string) ($photo['category'] ?? ''));
        $present[$name !== '' ? $name : 'Sin categoría'] = true;
    }
    $collections = gallery_collections_load();
    $changed = false;
    foreach ($collections as $name => $data) {
        if (!isset($present[$name])) {
            if (empty($data['manual'])) {
                unset($collections[$name]);
                $changed = true;
            } elseif (($data['cover'] ?? '') !== '') {
                $collections[$name]['cover'] = '';
                $changed = true;
            }
            continue;
        }
        if (($data['cover'] ?? '') !== '') {
            $coverFound = false;
            foreach ($photos as $photo) {
                $photoCategory = trim((string) ($photo['category'] ?? ''));
                if (($photoCategory !== '' ? $photoCategory : 'Sin categoría') === $name
                    && ($photo['filename'] ?? '') === $data['cover']) {
                    $coverFound = true;
                    break;
                }
            }
            if (!$coverFound) {
                $collections[$name]['cover'] = '';
                $changed = true;
            }
        }
    }
    foreach (array_keys($present) as $name) {
        if (!isset($collections[$name])) {
            $collections[$name] = ['description' => '', 'cover' => ''];
            $changed = true;
        }
    }
    if ($changed) gallery_collections_write($collections);
}
