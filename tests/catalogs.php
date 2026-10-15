<?php
declare(strict_types=1);

/**
 * Prueba de los catálogos de themes/: cada opción declarada tiene todo lo que necesita.
 *   · paletas: colores válidos, contraste legible y modo claro/oscuro coherente con el fondo;
 *   · fuentes: archivos WOFF2 y licencia presentes; las parejas apuntan a familias que existen;
 *   · cabeceras, hover, proporciones, paginación y galerías: tienen reglas CSS (salvo la opción base);
 *   · los valores por defecto existen en su catálogo.
 * Esta prueba es lo que permite añadir una opción creando solo un archivo: si falta algo, falla aquí.
 */

require_once __DIR__ . '/../inc/site-settings.php';

use Kcg\Core\Registry;
use Kcg\Themes\Fonts;

$problems = [];
$fail = static function (string $message) use (&$problems): void { $problems[] = $message; };
$root = dirname(__DIR__);

$luminance = static function (string $hex): float {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = preg_replace('/./', '$0$0', $hex);
    $channels = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    foreach ($channels as &$value) {
        $value /= 255;
        $value = $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
};
$contrast = static function (string $a, string $b) use ($luminance): float {
    [$x, $y] = [$luminance($a), $luminance($b)];
    if ($x < $y) [$x, $y] = [$y, $x];
    return ($x + 0.05) / ($y + 0.05);
};

// Todos los registros se cargan (Registry lanza una excepción si falta una clave obligatoria o un id no es válido).
$types = ['palettes', 'fonts', 'pairs', 'galleries', 'headers-mobile', 'headers-desktop', 'hover', 'grids', 'pagination-shapes', 'badges', 'socials'];
foreach ($types as $type) {
    try {
        if (Registry::for($type)->all() === []) $fail("themes/$type está vacío.");
    } catch (Throwable $error) {
        $fail($error->getMessage());
    }
}
if ($problems) { fwrite(STDERR, "FALLO de catálogos:\n - " . implode("\n - ", $problems) . "\n"); exit(1); }

// Paletas.
$required = ['bg', 'bg-soft', 'bg-card', 'fg', 'fg-soft', 'fg-dim', 'accent', 'accent-soft', 'line'];
foreach (Registry::for('palettes')->all() as $id => $palette) {
    if (!in_array($palette['mode'], ['light', 'dark'], true)) $fail("Paleta $id: «mode» debe ser light o dark.");
    if ($palette['vars'] === []) continue; // la paleta base usa style.css
    foreach ($required as $name) {
        $value = $palette['vars'][$name] ?? null;
        if (!is_string($value) || !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) $fail("Paleta $id: el color «$name» falta o no es un hex válido.");
    }
    if ($problems) continue;
    $vars = $palette['vars'];
    $isLight = $luminance($vars['bg']) > 0.4;
    if ($isLight !== ($palette['mode'] === 'light')) $fail("Paleta $id: «mode» es {$palette['mode']} pero el fondo es " . ($isLight ? 'claro' : 'oscuro') . '.');
    foreach (['fg' => 7.0, 'fg-soft' => 4.5, 'fg-dim' => 4.0, 'accent' => 4.5] as $name => $minimum) {
        $ratio = $contrast($vars[$name], $vars['bg']);
        if ($ratio < $minimum) $fail(sprintf('Paleta %s: contraste de «%s» sobre el fondo %.1f:1, mínimo %.1f:1.', $id, $name, $ratio, $minimum));
    }
    foreach ($palette['css'] ?? [] as $rule) {
        if (!str_contains($rule, '{p}')) $fail("Paleta $id: cada regla de «css» debe empezar por {p} (el selector de la paleta).");
    }
}

// Redes sociales: glifo de una sola tinta, sin colores propios (para que se adapte a cualquier paleta).
foreach (Registry::for('socials')->all() as $id => $network) {
    if (preg_match('/(fill|stroke|style)=|<(text|image|script|foreignObject)/i', (string) $network['svg'])) $fail("Red $id: el icono debe ser solo trazados y heredar el color (sin fill, stroke, style ni texto).");
    if (!str_contains((string) $network['svg'], '<path')) $fail("Red $id: el icono no tiene trazado.");
}

// Fuentes.
foreach (Registry::for('fonts')->all() as $id => $family) {
    if (!preg_match('/^\d{3}( \d{3})?$/', (string) $family['weights'])) $fail("Fuente $id: «weights» debe ser como «400» o «300 700».");
    foreach (Fonts::subsets() as $subset) {
        if (!is_file(Fonts::fontFile($id, $subset))) $fail("Fuente $id: falta assets/fonts/$id-$subset.woff2.");
    }
    if (!is_file($root . "/assets/fonts/$id-OFL.txt")) $fail("Fuente $id: falta la licencia assets/fonts/$id-OFL.txt.");
}
foreach (Registry::for('pairs')->all() as $id => $pair) {
    foreach (['heading', 'body'] as $role) {
        if (!Registry::for('fonts')->has($pair[$role])) $fail("Pareja $id: la familia «{$pair[$role]}» ($role) no existe en themes/fonts/.");
    }
    if (!isset(Fonts::GROUPS[$pair['group']])) $fail("Pareja $id: el grupo «{$pair['group']}» no existe (" . implode(', ', array_keys(Fonts::GROUPS)) . ').');
}

// Galerías estándar.
foreach (Registry::for('galleries')->all() as $id => $gallery) {
    foreach (['columns', 'photos', 'grid', 'pagination'] as $key) {
        if (!array_key_exists($key, $gallery['capabilities']) || !is_bool($gallery['capabilities'][$key])) $fail("Galería $id: falta la capacidad booleana «$key».");
    }
    if (array_diff($gallery['devices'], ['mobile', 'desktop']) || $gallery['devices'] === []) $fail("Galería $id: «devices» debe contener mobile y/o desktop.");
}

// Reglas CSS y JS de cada opción (la opción base no necesita ninguna).
$css = '';
foreach (array_merge(glob($root . '/assets/css/*.css') ?: [], glob($root . '/assets/css/worlds/*.css') ?: []) as $file) $css .= file_get_contents($file);
$sources = $css;
foreach (array_merge(glob($root . '/assets/js/*.js') ?: [], glob($root . '/assets/premium/*/*.js') ?: []) as $file) $sources .= file_get_contents($file);
$cssAttribute = ['hover' => 'data-hover', 'grids' => 'data-grid', 'pagination-shapes' => 'data-pagination-shape',
    'headers-mobile' => 'data-header-mobile', 'headers-desktop' => 'data-header-desktop'];
$base = ['hover' => 'soft', 'grids' => 'adaptive', 'pagination-shapes' => 'circle', 'headers-mobile' => 'current', 'headers-desktop' => 'current'];
foreach ($cssAttribute as $type => $attribute) {
    foreach (Registry::for($type)->ids() as $id) {
        if ($id === $base[$type]) continue;
        if (!str_contains($css, $attribute . '="' . $id . '"')) $fail("themes/$type/$id.php: no hay reglas CSS para $attribute=\"$id\" en assets/css/.");
    }
}
foreach (Registry::for('galleries')->ids() as $id) {
    if ($id === 'standard') continue;
    if (!str_contains($sources, "'" . $id . "'") && !str_contains($sources, '"' . $id . '"')) $fail("themes/galleries/$id.php: ningún JS o CSS de assets/ conoce el diseño «$id».");
}

// Archivos extra que declaran las opciones (`css` y `js`): existen, y cada hoja de assets/css/worlds/ la usa alguna opción.
$declared = [];
foreach (['headers-mobile', 'headers-desktop'] as $type) {
    foreach (Registry::for($type)->all() as $id => $option) {
        foreach (['css', 'js'] as $kind) {
            foreach ($option[$kind] ?? [] as $file) {
                $declared[$file] = true;
                if (!is_file($root . '/' . $file)) $fail("themes/$type/$id.php: «{$file}» ($kind) no existe.");
            }
        }
    }
}
foreach (glob($root . '/assets/css/worlds/*.css') ?: [] as $file) {
    if (!isset($declared['assets/css/worlds/' . basename($file)])) $fail('assets/css/worlds/' . basename($file) . ' no lo usa ninguna cabecera (declárala en css de themes/headers-*/).');
}

// Componentes de estilo (assets/css/components/): el manifiesto y los archivos coinciden, y el service worker los precachea todos
// (si un archivo del precaché no existe, el service worker no se instala).
$manifest = json_decode((string) file_get_contents($root . '/assets/css/components/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$components = array_column($manifest['components'], 'id');
foreach ($components as $id) {
    if (!is_file($root . "/assets/css/components/{$id}.css")) $fail("manifest.json: falta assets/css/components/{$id}.css.");
}
foreach (glob($root . '/assets/css/components/*.css') ?: [] as $file) {
    if (!in_array(basename($file, '.css'), $components, true)) $fail('assets/css/components/' . basename($file) . ' no está en manifest.json.');
}
foreach ($manifest['components'] as $component) {
    if (!array_intersect($component['profiles'], ['full', 'legal'])) $fail("manifest.json: el componente {$component['id']} no pertenece a ningún perfil (full o legal).");
    if (!in_array('full', $component['profiles'], true)) $fail("manifest.json: el perfil full debe incluir {$component['id']} (el panel y la portada cargan todo).");
}
preg_match_all("#'/assets/css/components/([a-z0-9-]+)\\.css'#", (string) file_get_contents($root . '/service-worker.js'), $precached);
if (array_values($precached[1]) !== $components) $fail('service-worker.js: la lista de componentes precacheados no coincide con assets/css/components/manifest.json (mismo contenido y orden).');
// Módulos de JavaScript (assets/js/main.js y assets/js/app/): cada import existe y el service worker precachea todos.
$graph = site_module_graph('assets/js/main.js');
foreach ($graph as $module) {
    if (!is_file($root . '/' . $module)) $fail("assets/js/main.js importa «{$module}» y no existe.");
}
$swSource = (string) file_get_contents($root . '/service-worker.js');
foreach (array_slice($graph, 0, -1) as $module) {
    if (!str_contains($swSource, "'/{$module}'")) $fail("service-worker.js: falta precachear /{$module} (es un módulo de main.js).");
}
foreach (glob($root . '/assets/js/app/*.js') ?: [] as $file) {
    if (!in_array('assets/js/app/' . basename($file), $graph, true)) $fail('assets/js/app/' . basename($file) . ' no lo importa nadie desde main.js.');
}
foreach (['hero', 'lightbox', 'masonry'] as $heavy) {
    if (in_array('legal', $manifest['components'][array_search($heavy, $components, true)]['profiles'], true)) $fail("manifest.json: «{$heavy}» no debe cargarse en las páginas legales.");
}

// Los valores por defecto existen en su catálogo.
$defaults = site_settings_defaults();
foreach (site_design_choices() as $key => $choices) {
    if ($key === 'gallery_premium') continue;
    if (!array_key_exists($defaults[$key], $choices)) $fail("El valor por defecto de «$key» («{$defaults[$key]}») no está en su catálogo.");
}

// Cada opción visible en el panel viene de un registro: ninguna lista de opciones escrita a mano.
$choices = site_design_choices();
foreach (['palette' => 'palettes', 'hover' => 'hover', 'grid' => 'grids', 'pagination_shape' => 'pagination-shapes', 'header_mobile' => 'headers-mobile', 'header_desktop' => 'headers-desktop'] as $setting => $type) {
    if (array_keys($choices[$setting]) !== Registry::for($type)->ids()) $fail("«$setting» no coincide con el registro themes/$type.");
}
if (array_keys($choices['font_pair']) !== Registry::for('pairs')->ids()) $fail('«font_pair» no coincide con el registro themes/pairs.');

if ($problems) {
    fwrite(STDERR, 'FALLO de catálogos (' . count($problems) . "):\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}
printf("OK: catálogos (%d paletas, %d fuentes, %d parejas, %d galerías, %d cabeceras móvil y %d de escritorio, %d hover).\n",
    count(Registry::for('palettes')->all()), count(Registry::for('fonts')->all()), count(Registry::for('pairs')->all()),
    count(Registry::for('galleries')->all()), count(Registry::for('headers-mobile')->all()), count(Registry::for('headers-desktop')->all()), count(Registry::for('hover')->all()));
