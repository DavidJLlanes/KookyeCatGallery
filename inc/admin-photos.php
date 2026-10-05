<?php

declare(strict_types=1);

/**
 * Todas las operaciones de administración se limitan a originales reales
 * situados directamente en la carpeta privada de fotografías.
 */
function managedPhotoPath(string $filename): ?string
{
    if ($filename === '' || basename($filename) !== $filename || str_contains($filename, "\0")
        || !preg_match('/\.(?:jpe?g|png)$/i', $filename)
        || str_ends_with(strtolower($filename), '.edit.jpg')) return null;

    $directory = realpath(UPLOAD_DIRECTORY);
    $path = realpath(UPLOAD_DIRECTORY . '/' . $filename);
    if ($directory === false || $path === false || dirname($path) !== $directory
        || is_link(UPLOAD_DIRECTORY . '/' . $filename) || !is_file($path)) return null;
    return $path;
}

function managedPhotoData(string $filename): ?array
{
    $original = managedPhotoPath($filename);
    if ($original === null) return null;

    $base = pathinfo($filename, PATHINFO_FILENAME);
    $sidecar = UPLOAD_DIRECTORY . '/' . $base . '.txt';
    $metadata = is_link($sidecar) ? read_sidecar('') : read_sidecar($sidecar);
    $fromName = parseFilenameMetadata($filename);
    $edited = UPLOAD_DIRECTORY . '/' . $base . '.edit.jpg';
    $title = $metadata['title'] !== '' ? $metadata['title'] : (string) $fromName['clean'];
    $category = $metadata['category'] !== '' ? $metadata['category'] : (string) $fromName['category'];
    $latitude = (float) ($metadata['latitude'] ?: $fromName['lat']);
    $longitude = (float) ($metadata['longitude'] ?: $fromName['lng']);
    $thumbnail = 'imagenes/mobile/' . $base . '.webp';

    return [
        'filename' => $filename,
        'original' => $original,
        'edited' => is_file($edited) && !is_link($edited),
        'title' => $title,
        'description' => (string) $metadata['description'],
        'category' => $category,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'slug' => (string) $metadata['slug'],
        'featured' => !empty($metadata['featured']),
        'draft' => !empty($metadata['draft']),
        'order' => $metadata['order'],
        'thumbnail' => is_file(__DIR__ . '/../' . $thumbnail)
            ? photo_asset_url($thumbnail, dirname(__DIR__))
            : '/admin.php?source=' . rawurlencode($filename),
        'mtime' => (int) (filemtime($original) ?: 0),
    ];
}

function managedPhotos(): array
{
    $photos = [];
    foreach (@scandir(UPLOAD_DIRECTORY) ?: [] as $filename) {
        $photo = managedPhotoData($filename);
        if ($photo !== null) $photos[] = $photo;
    }
    usort($photos, static function(array $a, array $b): int {
        $ao = $a['order']; $bo = $b['order'];
        if ($ao !== null || $bo !== null) {
            if ($ao === null) return 1;
            if ($bo === null) return -1;
            if ($ao !== $bo) return $ao <=> $bo;
        }
        return $b['mtime'] <=> $a['mtime'];
    });
    return $photos;
}

function managedPhotoSource(string $filename, string $variant): void
{
    $photo = managedPhotoData($filename);
    if ($photo === null) uploadJson(404, ['ok' => false, 'error' => 'Fotografía no encontrada.']);
    $edited = UPLOAD_DIRECTORY . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.edit.jpg';
    $path = $variant === 'original' || !$photo['edited'] ? $photo['original'] : $edited;
    if (!in_array($variant, ['original', 'published'], true) || !is_file($path) || is_link($path)) {
        uploadJson(404, ['ok' => false, 'error' => 'Versión no disponible.']);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        uploadJson(415, ['ok' => false, 'error' => 'Formato de imagen no válido.']);
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function managedSidecarText(array $fields): string
{
    $text = $fields['title'];
    if ($fields['description'] !== '') $text .= "\n---\n" . $fields['description'];
    $text .= "\n\n# Categoría: " . $fields['category'];
    if ($fields['latitude'] !== null) {
        $text .= "\n# Coordenadas: " . $fields['latitude'] . ',' . $fields['longitude'];
    }
    $text .= "\n# Slug: " . $fields['slug'];
    if (!empty($fields['featured'])) $text .= "\n# Destacada: 1";
    if (!empty($fields['draft'])) $text .= "\n# Borrador: 1";
    if ($fields['order'] !== null) $text .= "\n# Orden: " . (int) $fields['order'];
    $text .= "\n";
    return $text;
}

function managedValidateFields(array $input, string $filename): array
{
    $title = trim((string) ($input['title'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    $description = trim(str_replace("\r\n", "\n", (string) ($input['description'] ?? '')));
    $slug = trim((string) ($input['slug'] ?? ''));
    $featured = ($input['featured'] ?? '') === '1';
    $draft = ($input['draft'] ?? '') === '1';
    $existingMeta = read_sidecar(UPLOAD_DIRECTORY . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.txt');
    $order = $existingMeta['order'];
    if ($slug === '') $slug = generate_slug($title);

    if ($title === '' || mb_strlen($title, 'UTF-8') > 120 || $category === ''
        || mb_strlen($category, 'UTF-8') > 64 || mb_strlen($description, 'UTF-8') > 5000
        || preg_match('/[\x00-\x1F\x7F]/u', $title . $category)
        || preg_match('/^[ \t]*(?:#|---[ \t]*$)/m', $description)
        || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 80) {
        throw new RuntimeException('Revisa el nombre, la categoría, la descripción y el enlace de la foto.');
    }

    $latRaw = trim((string) ($input['latitude'] ?? ''));
    $lngRaw = trim((string) ($input['longitude'] ?? ''));
    $latitude = null;
    $longitude = null;
    if ($latRaw !== '' || $lngRaw !== '') {
        if (!is_numeric($latRaw) || !is_numeric($lngRaw) || abs((float) $latRaw) > 90 || abs((float) $lngRaw) > 180) {
            throw new RuntimeException('Indica latitud y longitud válidas, o deja ambas vacías.');
        }
        $latitude = round((float) $latRaw, 6);
        $longitude = round((float) $lngRaw, 6);
    }

    foreach (@scandir(UPLOAD_DIRECTORY) ?: [] as $other) {
        if (!str_ends_with(strtolower($other), '.txt')
            || $other === pathinfo($filename, PATHINFO_FILENAME) . '.txt') continue;
        $otherSidecar = UPLOAD_DIRECTORY . '/' . $other;
        if (is_link($otherSidecar)) continue;
        $otherMeta = read_sidecar($otherSidecar);
        $otherSlug = $otherMeta['slug'] !== '' ? $otherMeta['slug'] : generate_slug($otherMeta['title']);
        if ($otherSlug !== '' && $otherSlug === $slug) {
            throw new RuntimeException('Ese enlace ya pertenece a otra fotografía.');
        }
    }

    return compact('title', 'description', 'category', 'latitude', 'longitude', 'slug', 'featured', 'draft', 'order');
}

function managedSavePhoto(array $input, ?array $editedUpload): void
{
    $filename = (string) ($input['file'] ?? '');
    $original = managedPhotoPath($filename);
    if ($original === null) throw new RuntimeException('Fotografía no encontrada.');
    if (!is_writable(UPLOAD_DIRECTORY)) throw new RuntimeException('La carpeta de fotos no permite guardar cambios.');
    $fields = managedValidateFields($input, $filename);
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $sidecar = UPLOAD_DIRECTORY . '/' . $base . '.txt';
    $edited = UPLOAD_DIRECTORY . '/' . $base . '.edit.jpg';
    if (is_link($sidecar) || is_link($edited)) throw new RuntimeException('Hay un enlace de archivo no permitido.');

    $restore = ($input['restore_original'] ?? '') === '1';
    $hasUpload = is_array($editedUpload) && (int) ($editedUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($restore && $hasUpload) throw new RuntimeException('No se puede restaurar y editar a la vez.');
    if ($hasUpload) {
        $error = (int) ($editedUpload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException(uploadIniErrorMessage($error));
        if ((int) ($editedUpload['size'] ?? 0) < 1 || (int) $editedUpload['size'] > MAX_IMAGE_BYTES
            || !is_uploaded_file((string) ($editedUpload['tmp_name'] ?? ''))) {
            throw new RuntimeException('La versión editada debe ser una imagen válida de hasta 15 MB.');
        }
        if ((new finfo(FILEINFO_MIME_TYPE))->file($editedUpload['tmp_name']) !== 'image/jpeg'
            || !@getimagesize($editedUpload['tmp_name'])) {
            throw new RuntimeException('La versión editada no es un JPEG válido.');
        }
    }

    $metadataTemp = tempnam(UPLOAD_DIRECTORY, '.djl-meta-');
    if ($metadataTemp === false) throw new RuntimeException('No se pudo preparar el cambio de datos.');
    $imageTemp = null;
    try {
        if (file_put_contents($metadataTemp, managedSidecarText($fields), LOCK_EX) === false) {
            throw new RuntimeException('No se pudieron escribir los datos de la fotografía.');
        }
        if ($hasUpload) {
            $imageTemp = tempnam(UPLOAD_DIRECTORY, '.djl-edit-');
            if ($imageTemp === false || !move_uploaded_file($editedUpload['tmp_name'], $imageTemp)) {
                throw new RuntimeException('No se pudo preparar la imagen editada.');
            }
        }
        if (!rename($metadataTemp, $sidecar)) throw new RuntimeException('No se pudieron guardar los datos.');
        $metadataTemp = null;
        @chmod($sidecar, 0644);
        if ($imageTemp !== null) {
            if (!rename($imageTemp, $edited)) throw new RuntimeException('Los datos se guardaron, pero no la imagen editada. Reinténtalo.');
            $imageTemp = null;
            @chmod($edited, 0644);
        } elseif ($restore && is_file($edited) && !unlink($edited)) {
            throw new RuntimeException('Los datos se guardaron, pero no se pudo restaurar el original.');
        }
        if ($hasUpload || $restore) {
            // Forzar la regeneración incluso si dos ediciones comparten segundo y tamaño.
            foreach (['desktop', 'mobile'] as $size) {
                $derived = dirname(__DIR__) . '/imagenes/' . $size . '/' . $base . '.webp';
                if (is_link($derived)) throw new RuntimeException('Hay una miniatura enlazada no permitida.');
                if (is_file($derived) && !@unlink($derived)) {
                    throw new RuntimeException('No se pudo actualizar una miniatura. Los cambios se han guardado; revisa permisos.');
                }
            }
        }
    } finally {
        if ($metadataTemp !== null) @unlink($metadataTemp);
        if ($imageTemp !== null) @unlink($imageTemp);
    }
}

function managedDeletePhoto(string $filename): void
{
    $original = managedPhotoPath($filename);
    if ($original === null) throw new RuntimeException('Fotografía no encontrada.');
    if (!is_writable(UPLOAD_DIRECTORY)) throw new RuntimeException('La carpeta de fotos no permite eliminar.');
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $paths = [
        UPLOAD_DIRECTORY . '/' . $base . '.edit.jpg',
        UPLOAD_DIRECTORY . '/' . $base . '.txt',
        __DIR__ . '/../imagenes/desktop/' . $base . '.webp',
        __DIR__ . '/../imagenes/mobile/' . $base . '.webp',
        $original,
    ];
    foreach ($paths as $path) {
        if (is_link($path)) throw new RuntimeException('No se puede eliminar un enlace de archivo.');
    }
    foreach ($paths as $path) {
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('No se pudieron eliminar todos los archivos. La fotografía original se conserva si es posible.');
        }
    }
}

function managedChangePhotoCategory(string $filename, string $category): void
{
    $photo = managedPhotoData($filename);
    if ($photo === null) throw new RuntimeException('No se encontró una de las fotografías seleccionadas.');
    $category = trim($category);
    if ($category === '' || mb_strlen($category, 'UTF-8') > 64 || preg_match('/[\x00-\x1F\x7F]/u', $category)) {
        throw new RuntimeException('El nombre de categoría debe tener entre 1 y 64 caracteres.');
    }
    if (!is_writable(UPLOAD_DIRECTORY)) throw new RuntimeException('La carpeta de fotos no permite guardar cambios.');
    $sidecar = UPLOAD_DIRECTORY . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.txt';
    if (is_link($sidecar)) throw new RuntimeException('Hay un enlace de archivo no permitido.');
    if (is_file($sidecar)) {
        $content = @file_get_contents($sidecar);
        if (!is_string($content)) throw new RuntimeException('No se pudieron leer los datos de una fotografía.');
        $line = '# Categoría: ' . $category;
        if (preg_match('/^# Categoría:.*$/m', $content)) {
            $content = preg_replace('/^# Categoría:.*$/m', $line, $content) ?? $content;
        } else {
            $content = rtrim($content) . "\n" . $line . "\n";
        }
    } else {
        $meta = read_sidecar('');
        $content = managedSidecarText([
            'title' => $photo['title'], 'description' => $photo['description'], 'category' => $category,
            'latitude' => null, 'longitude' => null, 'slug' => (string) $meta['slug'],
            'featured' => $photo['featured'], 'draft' => $photo['draft'], 'order' => $photo['order'],
        ]);
    }
    $temporary = tempnam(UPLOAD_DIRECTORY, '.djl-category-');
    if ($temporary === false) throw new RuntimeException('No se pudo preparar el cambio de categoría.');
    try {
        if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $sidecar)) {
            throw new RuntimeException('No se pudo guardar el cambio de categoría.');
        }
        @chmod($sidecar, 0644);
    } finally {
        if (is_file($temporary)) @unlink($temporary);
    }
}

function managedApplyCategoryAction(string $source, string $operation, string $target, array $existingCategories, string $confirmation = ''): int
{
    $source = trim($source);
    $photos = array_values(array_filter(managedPhotos(), static function(array $photo) use ($source): bool {
        $category = trim((string) $photo['category']);
        return ($category !== '' ? $category : 'Sin categoría') === $source;
    }));
    if ($source === '' || !$photos) throw new RuntimeException('La categoría no existe o no contiene fotografías.');
    if (!is_writable(UPLOAD_DIRECTORY)) throw new RuntimeException('La carpeta de fotos no permite guardar cambios.');
    if ($operation === 'delete_photos') {
        if ($confirmation !== 'ELIMINAR') throw new RuntimeException('Escribe ELIMINAR para confirmar el borrado masivo.');
        foreach ($photos as $photo) managedPhotoPath($photo['filename']) ?? throw new RuntimeException('Una fotografía ya no está disponible.');
        foreach ($photos as $photo) managedDeletePhoto($photo['filename']);
        return count($photos);
    }
    $target = trim($target);
    if ($target === '' || mb_strlen($target, 'UTF-8') > 64 || preg_match('/[\x00-\x1F\x7F]/u', $target)) {
        throw new RuntimeException('El nombre de categoría debe tener entre 1 y 64 caracteres.');
    }
    if ($target === $source) throw new RuntimeException('Elige un nombre o una categoría distinta.');
    if ($operation === 'move_existing' && !in_array($target, $existingCategories, true)) {
        throw new RuntimeException('Selecciona una categoría existente.');
    }
    if ($operation === 'move_new' && in_array($target, $existingCategories, true)) {
        throw new RuntimeException('Esa categoría ya existe. Elige «Mover a existente» o usa otro nombre.');
    }
    if (!in_array($operation, ['rename', 'move_existing', 'move_new'], true)) throw new RuntimeException('Selecciona una acción válida.');
    foreach ($photos as $photo) managedChangePhotoCategory($photo['filename'], $target);
    return count($photos);
}


function managedReorderPhotos(array $filenames): void
{
    $photos = managedPhotos();
    $known = array_column($photos, 'filename');
    if (count($filenames) !== count($known) || count(array_unique($filenames)) !== count($filenames)
        || array_diff($filenames, $known) || array_diff($known, $filenames)) {
        throw new RuntimeException('El orden recibido no coincide con la biblioteca actual.');
    }
    foreach ($filenames as $position => $filename) {
        $path = managedPhotoPath((string) $filename);
        if ($path === null) throw new RuntimeException('Fotografía no encontrada al guardar el orden.');
        $sidecar = UPLOAD_DIRECTORY . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.txt';
        if (is_link($sidecar)) throw new RuntimeException('Hay un enlace de archivo no permitido.');
        $raw = is_file($sidecar) ? (string) file_get_contents($sidecar) : '';
        $line = '# Orden: ' . ($position + 1);
        if (preg_match('/^# Orden:\s*-?\d+\s*$/m', $raw)) {
            $raw = preg_replace('/^# Orden:\s*-?\d+\s*$/m', $line, $raw, 1);
        } else {
            $raw = rtrim($raw) . "\n" . $line . "\n";
        }
        if (file_put_contents($sidecar, $raw, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el orden de las fotografías.');
        }
        @chmod($sidecar, 0644);
    }
}
