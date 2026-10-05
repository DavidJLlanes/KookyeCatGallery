<?php
declare(strict_types=1);

/** Adjacent public photos in the gallery order. Photos without a permalink are skipped. */
function photo_neighbors(array $items, string $slug): array
{
    $photos = array_values(array_filter($items, static fn(array $photo): bool => !empty($photo['slug'])));
    foreach ($photos as $index => $photo) {
        if ($photo['slug'] === $slug) {
            return ['previous' => $photos[$index - 1] ?? null, 'next' => $photos[$index + 1] ?? null];
        }
    }
    return ['previous' => null, 'next' => null];
}
