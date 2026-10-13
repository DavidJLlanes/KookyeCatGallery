<?php
declare(strict_types=1);

/**
 * Presupuesto de rendimiento: lo que carga cada tipo de página (estilos y scripts propios, en bytes sin comprimir, y
 * número de archivos) no puede crecer sin que alguien suba el presupuesto a propósito en tests/performance-budget.json.
 *
 *   php tests/performance-budget.php            comprueba los presupuestos
 *   php tests/performance-budget.php --report   muestra las cifras actuales (para ajustar el presupuesto)
 *
 * También comprueba que las opciones no elegidas no cuestan nada: una cabecera especial solo carga sus archivos si es la
 * elegida, la web mínima no carga Leaflet ni el CSS del microblog y las páginas legales no cargan la portada.
 */

$root = dirname(__DIR__);
$report = in_array('--report', $argv ?? [], true);
$budgets = json_decode((string) file_get_contents(__DIR__ . '/performance-budget.json'), true, 512, JSON_THROW_ON_ERROR)['paginas'];

$config = sys_get_temp_dir() . '/performance-budget-' . bin2hex(random_bytes(4));
mkdir($config . '/microblog', 0775, true);

// Las páginas se generan en una copia del sitio: index.php escribe sitemap.xml, robots.txt y miniaturas, y la prueba no debe tocar los archivos reales.
$site = $config . '/site';
$skip = ['.git', 'node_modules', 'img', 'imagenes', 'microblog', 'visual', 'golden', 'log', 'var', 'data', 'docs', 'tests', '.github', 'subirweb'];
$copy = static function (string $from, string $to) use (&$copy, $skip): void {
    @mkdir($to, 0775, true);
    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || in_array($entry, $skip, true)) continue;
        is_dir("$from/$entry") ? $copy("$from/$entry", "$to/$entry") : copy("$from/$entry", "$to/$entry");
    }
};
$copy($root, $site);
@mkdir($site . '/data', 0775, true);
putenv('SITE_CONFIG_DIR=' . $config);
putenv('MICROBLOG_DATA_DIR=' . $config . '/microblog');

/** HTML de una página con unos ajustes dados (ejecutada en un proceso aparte, como en producción). */
function budget_render(string $root, string $page, array $settings): string
{
    global $config, $site;
    file_put_contents($config . '/site-settings.json', json_encode($settings));
    $php = PHP_BINARY;
    $command = escapeshellarg($php) . ' -d display_errors=0 ' . escapeshellarg($site . '/' . $page);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, $site, ['SITE_CONFIG_DIR' => $config, 'MICROBLOG_DATA_DIR' => $config . '/microblog', 'PATH' => (string) getenv('PATH'), 'PHPRC' => (string) getenv('PHPRC'), 'SystemRoot' => (string) getenv('SystemRoot')]);
    $html = (string) stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    proc_close($process);
    return $html;
}

/** Archivos propios (estilos y scripts) que enlaza un HTML y sus bytes. */
function budget_assets(string $root, string $html): array
{
    preg_match_all('#<link[^>]+rel="stylesheet"[^>]+href="(/[^"?]+)#', $html, $css);
    preg_match_all('#<script[^>]+src="(/[^"?]+)#', $html, $js);
    preg_match_all('#<link[^>]+rel="modulepreload"[^>]+href="(/[^"?]+)#', $html, $preloaded); // módulos de main.js
    $js[1] = array_merge($js[1], $preloaded[1]);
    $external = preg_match_all('#<script[^>]+src="https?://#', $html) + preg_match_all('#<link[^>]+rel="stylesheet"[^>]+href="https?://#', $html);
    $files = ['css' => array_values(array_unique($css[1])), 'js' => array_values(array_unique($js[1]))];
    $bytes = ['css' => 0, 'js' => 0];
    foreach ($files as $kind => $list) foreach ($list as $path) $bytes[$kind] += is_file($root . $path) ? (int) filesize($root . $path) : 0;
    return ['files' => $files, 'bytes' => $bytes, 'external' => $external, 'inlineCss' => (function () use ($html): int {
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $html, $styles);
        return strlen(implode('', $styles[1]));
    })()];
}

$hidden = ['show_map' => false, 'show_project' => false, 'show_microblog' => false, 'show_social' => false, 'show_categories' => false];
$cases = [
    'portada' => ['index.php', []],
    'portada-minima' => ['index.php', $hidden],
    'portada-cabeceras-especiales' => ['index.php', ['header_mobile' => 'reel', 'header_desktop' => 'museum']],
    'portada-orbita-observatorio' => ['index.php', ['header_mobile' => 'orbit', 'header_desktop' => 'observatory']],
    'aviso-legal' => ['aviso-legal.php', []],
    'politica-privacidad' => ['politica-privacidad.php', []],
    'politica-cookies' => ['politica-cookies.php', []],
];

$problems = [];
$measured = [];
foreach ($cases as $name => [$page, $settings]) {
    $html = budget_render($root, $page, $settings);
    if (strlen($html) < 500) { $problems[] = "$name: la página no se pudo generar (" . strlen($html) . ' bytes).'; continue; }
    $assets = budget_assets($root, $html);
    $measured[$name] = $assets;
    $budget = $budgets[$name] ?? null;
    if ($report) {
        printf("%-30s css %6d B (%2d archivos) · js %6d B (%2d archivos) · externos %d · css inline %5d B\n", $name, $assets['bytes']['css'], count($assets['files']['css']), $assets['bytes']['js'], count($assets['files']['js']), $assets['external'], $assets['inlineCss']);
        continue;
    }
    if ($budget === null) { $problems[] = "$name: no tiene presupuesto en tests/performance-budget.json (ejecuta con --report)."; continue; }
    foreach (['css' => 'cssBytes', 'js' => 'jsBytes'] as $kind => $key) {
        if ($assets['bytes'][$kind] > $budget[$key]) $problems[] = sprintf('%s: %s %d B supera el presupuesto de %d B.', $name, strtoupper($kind), $assets['bytes'][$kind], $budget[$key]);
    }
    if (count($assets['files']['css']) + count($assets['files']['js']) > $budget['archivos']) $problems[] = sprintf('%s: %d archivos propios superan el presupuesto de %d.', $name, count($assets['files']['css']) + count($assets['files']['js']), $budget['archivos']);
    if ($assets['external'] > $budget['externos']) $problems[] = sprintf('%s: %d recursos externos superan el presupuesto de %d.', $name, $assets['external'], $budget['externos']);
}
if ($report) exit(0);

// Lo que no se usa no se carga.
$has = static fn(string $case, string $kind, string $needle): bool => (bool) array_filter($measured[$case]['files'][$kind] ?? [], static fn($f) => str_contains($f, $needle));
if (isset($measured['portada-minima'])) {
    $problems = array_merge($problems, array_filter([
        $measured['portada-minima']['external'] > 0 ? 'portada-minima: no debería cargar recursos externos (Leaflet) sin el mapa.' : null,
        $has('portada-minima', 'css', 'microblog.css') ? 'portada-minima: no debería cargar microblog.css sin el microblog.' : null,
        $has('portada-minima', 'js', 'microblog.js') ? 'portada-minima: no debería cargar microblog.js sin el microblog.' : null,
    ]));
}
if (isset($measured['portada'])) {
    $problems = array_merge($problems, array_filter([
        $has('portada', 'css', 'worlds/') ? 'portada: la cabecera básica no necesita las hojas de assets/css/worlds/.' : null,
        $has('portada', 'js', 'interface-worlds.js') ? 'portada: la cabecera básica no necesita interface-worlds.js.' : null,
        !$has('portada', 'css', 'fab-menu.css') ? 'portada: falta fab-menu.css (el menú del botón «+» se pinta siempre).' : null,
    ]));
}
if (isset($measured['portada-cabeceras-especiales'])) {
    $worlds = array_values(array_filter($measured['portada-cabeceras-especiales']['files']['css'], static fn($f) => str_contains($f, 'worlds/')));
    $expected = ['/assets/css/worlds/mobile-reel.css', '/assets/css/worlds/mobile-common.css', '/assets/css/worlds/desktop-museum.css', '/assets/css/worlds/desktop-common.css'];
    if (array_diff($worlds, $expected) || array_diff($expected, $worlds)) $problems[] = 'portada-cabeceras-especiales: solo deben cargarse las hojas de «reel» y «museum» (' . implode(', ', $worlds) . ').';
    if ($has('portada-cabeceras-especiales', 'js', 'interface-worlds.js')) $problems[] = 'portada-cabeceras-especiales: reel y museum no usan el lienzo 3D.';
}
if (isset($measured['portada-orbita-observatorio']) && !$has('portada-orbita-observatorio', 'js', 'interface-worlds.js')) $problems[] = 'portada-orbita-observatorio: falta interface-worlds.js.';
foreach (['aviso-legal', 'politica-privacidad', 'politica-cookies'] as $legal) {
    if (!isset($measured[$legal])) continue;
    foreach (['hero.css', 'lightbox.css', 'masonry.css', 'main.js', 'microblog'] as $needle) {
        if ($has($legal, 'css', $needle) || $has($legal, 'js', $needle)) $problems[] = "$legal: no debería cargar «$needle» (es de la portada).";
    }
}

$remove = static function (string $path) use (&$remove): void {
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        is_dir("$path/$entry") ? $remove("$path/$entry") : @unlink("$path/$entry");
    }
    @rmdir($path);
};
$remove($config);
if ($problems) {
    fwrite(STDERR, 'FALLO del presupuesto (' . count($problems) . "):\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}
printf("OK: presupuesto de rendimiento (%d tipos de página).\n", count($measured));
