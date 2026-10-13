<?php
declare(strict_types=1);

/**
 * Crea los datos de prueba deterministas de una copia temporal del sitio (nunca toca los datos reales).
 *
 *   php tests/fixtures/make-site-fixtures.php <carpeta-del-sitio> [<carpeta-de-config>]
 *
 * Genera en <carpeta-del-sitio>:
 *   img/            24 fotografías sintéticas (JPG) con su texto (.txt): título, descripción, categoría, coordenadas y slug.
 *                   Las fechas de modificación son fijas para que el orden de la galería sea siempre el mismo.
 *   microblog/      imágenes del microblog.
 * y en <carpeta-de-config>:
 *   microblog/posts.json   posts con fechas fijas (normal, destacado, súper destacado, fijado, hilo, cita, enlaces, imágenes).
 *
 * Los colores salen solo del índice de la foto, así que dos ejecuciones producen exactamente los mismos archivos.
 */

if ($argc < 2) { fwrite(STDERR, "Uso: php make-site-fixtures.php <carpeta-del-sitio> [<carpeta-de-config>]\n"); exit(2); }
$site = rtrim(str_replace('\\', '/', $argv[1]), '/');
$config = rtrim(str_replace('\\', '/', $argv[2] ?? ($site . '/_config')), '/');
if (!function_exists('imagecreatetruecolor')) { fwrite(STDERR, "Hace falta la extensión GD.\n"); exit(2); }

foreach (["$site/img", "$site/microblog", "$config/microblog"] as $dir) if (!is_dir($dir)) mkdir($dir, 0775, true);

/** Gradiente determinista a partir del índice. */
function fixture_picture(string $path, int $w, int $h, int $seed, bool $png = false): void
{
    $image = imagecreatetruecolor($w, $h);
    $hue = ($seed * 47) % 360;
    for ($y = 0; $y < $h; $y++) {
        [$r, $g, $b] = fixture_hsl(($hue + $y / $h * 50) % 360, 0.55, 0.18 + 0.5 * (1 - $y / $h));
        imageline($image, 0, $y, $w, $y, imagecolorallocate($image, $r, $g, $b));
    }
    // Una marca por foto para que cada miniatura sea distinguible en las capturas.
    $mark = imagecolorallocate($image, 255, 255, 255);
    for ($i = 0; $i <= $seed % 5; $i++) imagefilledrectangle($image, 20 + $i * 34, 20, 40 + $i * 34, 40, $mark);
    $png ? imagepng($image, $path) : imagejpeg($image, $path, 86);
}

function fixture_hsl(float $h, float $s, float $l): array
{
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    [$r, $g, $b] = match (true) { $h < 60 => [$c, $x, 0], $h < 120 => [$x, $c, 0], $h < 180 => [0, $c, $x], $h < 240 => [0, $x, $c], $h < 300 => [$x, 0, $c], default => [$c, 0, $x] };
    return [(int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255)];
}

$categories = ['Catedral', 'Paisajes', 'Calles', 'Nocturna', 'Agua', 'Arquitectura'];
$shapes = [[1600, 1067], [1067, 1600], [1600, 1200], [1200, 1600], [1600, 900], [1200, 1200]];
for ($i = 1; $i <= 24; $i++) {
    $name = sprintf('foto-de-prueba-%02d', $i);
    [$w, $h] = $shapes[$i % count($shapes)];
    fixture_picture("$site/img/$name.jpg", $w, $h, $i);
    $category = $categories[$i % count($categories)];
    $lat = number_format(42.55 + ($i % 7) * 0.012, 4, '.', '');
    $lng = number_format(-5.60 + ($i % 5) * 0.015, 4, '.', '');
    $text = "Fotografía de prueba número $i\n---\nDescripción de la fotografía $i, una escena de $category para comprobar el diseño.\n\n"
        . "# Categoría: $category\n# Coordenadas: $lat,$lng\n"
        . ($i % 9 === 0 ? "# Destacada: 1\n" : '')
        . "# Slug: foto-de-prueba-$i\n";
    file_put_contents("$site/img/$name.txt", $text);
    touch("$site/img/$name.jpg", 1760000000 + $i * 86400);
    touch("$site/img/$name.txt", 1760000000 + $i * 86400);
}

// Microblog: posts con fechas fijas (mediodía UTC) para que las capturas no cambien con el día.
$image = static function (string $file, int $seed, int $w, int $h) use ($site): array {
    fixture_picture("$site/microblog/$file", $w, $h, $seed);
    return ['file' => $file, 'w' => $w, 'h' => $h, 'alt' => "Imagen $seed"];
};
$base = 1760000000;
$post = static fn(string $id, ?string $parent, int $offset, int $level, string $text, array $extra = []): array => array_merge([
    'id' => $id, 'parent' => $parent, 'created' => $base + $offset, 'updated' => $base + $offset, 'level' => $level, 'pinned' => false,
    'text' => $text, 'quote' => null, 'links' => [], 'images' => [],
], $extra);
$posts = [
    $post('aaaaaaaa01', null, 100, 0, 'Una nota sencilla con un enlace https://example.com/uno y otro [con texto](https://example.org/dos).'),
    $post('aaaaaaaa02', null, 200, 1, "Un post destacado.\n\nTiene dos párrafos y una cita.", ['quote' => ['text' => 'La luz lo es todo.', 'source' => 'Autor de prueba', 'url' => 'https://example.com/cita']]),
    $post('aaaaaaaa03', null, 300, 2, 'Súper destacado, con enlace en tarjeta y respuestas.', ['links' => [['url' => 'https://example.com/expo', 'title' => 'Exposición de prueba']]]),
    $post('aaaaaaaa04', 'aaaaaaaa03', 400, 0, 'Primera respuesta del hilo.'),
    $post('aaaaaaaa05', 'aaaaaaaa03', 500, 0, 'Segunda respuesta del hilo.'),
    $post('aaaaaaaa06', 'aaaaaaaa03', 600, 0, 'Tercera respuesta (se oculta en la portada).'),
    $post('aaaaaaaa07', null, 700, 0, 'Cuatro imágenes.', ['images' => [$image('mb-fixture-1.jpg', 1, 1200, 800), $image('mb-fixture-2.jpg', 2, 800, 1200), $image('mb-fixture-3.jpg', 3, 1000, 1000), $image('mb-fixture-4.jpg', 4, 1200, 900)]]),
    $post('aaaaaaaa08', null, 800, 0, 'Una sola imagen.', ['images' => [$image('mb-fixture-5.jpg', 5, 1500, 900)]]),
    $post('aaaaaaaa09', null, 900, 0, 'Post fijado arriba del feed.', ['pinned' => true]),
];
for ($i = 10; $i <= 20; $i++) $posts[] = $post('aaaaaaaa' . $i, null, $i * 100, 0, "Nota de relleno número $i para comprobar «Cargar más».");
file_put_contents("$config/microblog/posts.json", json_encode(['posts' => $posts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo "Fixtures creados en $site\n";
