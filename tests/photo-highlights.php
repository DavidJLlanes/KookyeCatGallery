<?php
declare(strict_types=1);

// Fotos fijadas (`# Fijada: 1`) y destacadas (`# Destacada: 1`): se leen y se guardan en el .txt, las fijadas van arriba conservando su
// orden y el resto no se mueve, y la IA no pierde esas marcas (ni el orden manual) al reescribir un .txt.
// Uso: php tests/photo-highlights.php

require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/site-settings.php';
require_once dirname(__DIR__) . '/inc/ai-text.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'photo-highlights-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);

// 1. Lectura del .txt.
file_put_contents("$tmp/a.txt", "Título\n---\nDescripción\n\n# Categoría: Viajes\n# Destacada: 1\n# Fijada: 1\n# Orden: 3\n");
$meta = read_sidecar("$tmp/a.txt");
check($meta['featured'] === true && $meta['pinned'] === true && $meta['order'] === 3 && $meta['title'] === 'Título', 'Se leen destacada, fijada y orden.');
file_put_contents("$tmp/b.txt", "Solo título\n\n# Destacada: 1\n");
$meta = read_sidecar("$tmp/b.txt");
check($meta['featured'] === true && $meta['pinned'] === false, 'Destacada no implica fijada.');
check(read_sidecar("$tmp/no-existe.txt")['pinned'] === false, 'Sin .txt no hay fijada.');

// 2. enrich_with_sidecar y orden: las fijadas arriba, en su orden relativo; las demás no se mueven; destacada no cambia de sitio.
file_put_contents("$tmp/p1.txt", "P1\n\n# Fijada: 1\n");
file_put_contents("$tmp/p2.txt", "P2\n\n# Fijada: 1\n# Destacada: 1\n");
file_put_contents("$tmp/f1.txt", "F1\n\n# Destacada: 1\n");
file_put_contents("$tmp/n1.txt", "N1\n");
$items = enrich_with_sidecar([['sidecar' => "$tmp/n1.txt"], ['sidecar' => "$tmp/p1.txt"], ['sidecar' => "$tmp/f1.txt"], ['sidecar' => "$tmp/p2.txt"]]);
$order = array_column(pin_to_top($items), 'title');
check($order === ['P1', 'P2', 'N1', 'F1'], 'Fijadas arriba en su orden; el resto igual: ' . implode(',', $order));
check(pin_to_top([['title' => 'x']]) === [['title' => 'x']], 'Sin fijadas no cambia nada.');
check(array_column(pin_to_top($items), 'featured')[2] === false, 'La destacada conserva su sitio.');

// 3. El panel guarda y lee los dos estados.
require_once dirname(__DIR__) . '/inc/admin-photos.php';
$text = managedSidecarText(['title' => 'T', 'description' => '', 'category' => 'C', 'latitude' => null, 'longitude' => null, 'slug' => 't', 'featured' => true, 'pinned' => true, 'draft' => false, 'order' => 2]);
check(str_contains($text, "# Destacada: 1") && str_contains($text, "# Fijada: 1") && str_contains($text, '# Orden: 2'), 'El panel escribe destacada, fijada y orden.');
$text = managedSidecarText(['title' => 'T', 'description' => '', 'category' => 'C', 'latitude' => null, 'longitude' => null, 'slug' => 't', 'featured' => false, 'pinned' => false, 'draft' => false, 'order' => null]);
check(!str_contains($text, 'Fijada') && !str_contains($text, 'Destacada'), 'Sin marcar no se escribe nada.');

// 4. La IA conserva las marcas y el orden al reescribir el .txt.
$generator = (new ReflectionClass('AiTextGenerator'))->newInstanceWithoutConstructor();
$write = new ReflectionMethod($generator, 'writeSidecar');
$write->setAccessible(true);
file_put_contents("$tmp/ia.txt", "Antiguo\n\n# Slug: antiguo\n# Categoría: Mar\n# Destacada: 1\n# Fijada: 1\n# Orden: -4\n# Borrador: 1\n");
$write->invoke($generator, "$tmp/ia.txt", 'Nuevo título', 'Nueva descripción', 'gemini');
$kept = read_sidecar("$tmp/ia.txt");
check($kept['title'] === 'Nuevo título' && $kept['featured'] && $kept['pinned'] && $kept['draft'] && $kept['order'] === -4 && $kept['category'] === 'Mar', 'La IA conserva destacada, fijada, borrador, orden y categoría.');

// 5. Piezas visuales presentes: marcado de la tarjeta, distintivo y hoja de estilos en el manifiesto.
$gallery = (string) file_get_contents(dirname(__DIR__) . '/inc/blocks/gallery.php');
check(str_contains($gallery, 'card--pinned') && str_contains($gallery, 'card--featured') && str_contains($gallery, 'card__badge') && str_contains($gallery, 'card__frame'), 'La tarjeta de la galería marca fijadas y destacadas.');
$manifest = (string) file_get_contents(dirname(__DIR__) . '/assets/css/components/manifest.json');
check(str_contains($manifest, '"photo-highlights"') && is_file(dirname(__DIR__) . '/assets/css/components/photo-highlights.css'), 'La hoja de estilos de fijadas y destacadas está registrada.');

foreach (glob("$tmp/*") ?: [] as $file) @unlink($file);
@rmdir($tmp);
echo "Photo highlights tests passed.\n";
