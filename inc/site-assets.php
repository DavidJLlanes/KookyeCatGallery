<?php
declare(strict_types=1);

use Kcg\Core\Registry;

/**
 * Estilos y scripts de la web pública, cargados según lo que cada página y cada opción necesitan:
 *
 *   · Componentes de CSS (assets/css/components/, ordenados por manifest.json) por tipo de página: las páginas legales
 *     solo cargan lo que dibujan; la portada, la foto, el microblog y el panel cargan todos.
 *   · Archivos extra de las opciones elegidas: cada opción de themes/ declara en su definición los que necesita (`css` y
 *     `js`, rutas desde la raíz del sitio) y la página solo carga los de las opciones activas. Así una cabecera, una
 *     galería o un efecto que no se usan no cuestan ni un byte.
 */

/**
 * Versión de un archivo para el parámetro ?v= (cambia cuando cambia su contenido, aunque el FTP conserve la fecha).
 * $relative es la ruta desde la raíz del sitio, sin barra inicial.
 */
function site_asset_version(string $relative): string
{
    $path = dirname(__DIR__) . '/' . $relative;
    if (!is_file($path)) return (string) time();
    return substr(hash_file('crc32b', $path) ?: (string) filemtime($path), 0, 8);
}

/**
 * Componentes de assets/css/components/ que carga un tipo de página, en orden de cascada (manifest.json).
 * Perfiles: «full» (portada, foto, microblog y panel) y «legal» (páginas de texto). El orden y la pertenencia a cada
 * perfil se declaran en el manifiesto; los componentes son los trozos del antiguo style.css.
 *
 * @return list<string> rutas desde la raíz del sitio
 */
function site_style_components(string $profile = 'full'): array
{
    static $manifest;
    $manifest ??= json_decode((string) file_get_contents(dirname(__DIR__) . '/assets/css/components/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $files = [];
    foreach ($manifest['components'] as $component) {
        if (in_array($profile, $component['profiles'], true)) $files[] = 'assets/css/components/' . $component['id'] . '.css';
    }
    return $files;
}

/** Etiquetas <link> de los componentes de estilo de un perfil de página. */
function site_style_tags(string $profile = 'full'): string
{
    $html = '';
    foreach (site_style_components($profile) as $file) {
        $html .= '<link rel="stylesheet" href="/' . $file . '?v=' . site_asset_version($file) . '">' . "\n    ";
    }
    return rtrim($html);
}

/**
 * Archivos extra de las opciones activas, sin repetir y en este orden: cabecera móvil, cabecera de escritorio.
 *
 * @return array{css: list<string>, js: list<string>}
 */
function site_option_assets(array $settings): array
{
    $assets = ['css' => [], 'js' => []];
    foreach (['headers-mobile' => 'header_mobile', 'headers-desktop' => 'header_desktop'] as $type => $setting) {
        $option = Registry::for($type)->get((string) ($settings[$setting] ?? ''));
        if ($option === null) continue;
        foreach (['css', 'js'] as $kind) {
            foreach ($option[$kind] ?? [] as $file) {
                if (!in_array($file, $assets[$kind], true)) $assets[$kind][] = $file;
            }
        }
    }
    return $assets;
}

/** Etiquetas <link> de los estilos extra de las opciones activas (con versión por contenido). */
function site_option_css_tags(array $settings): string
{
    $html = '';
    foreach (site_option_assets($settings)['css'] as $file) {
        $html .= '<link rel="stylesheet" href="/' . $file . '?v=' . site_asset_version($file) . '">' . "\n    ";
    }
    return rtrim($html);
}

/** Etiquetas <script type="module"> de los scripts extra de las opciones activas. */
function site_option_js_tags(array $settings): string
{
    $html = '';
    foreach (site_option_assets($settings)['js'] as $file) {
        $html .= '<script type="module" src="/' . $file . '?v=' . site_asset_version($file) . '"></script>' . "\n";
    }
    return rtrim($html);
}

/**
 * Módulos ES que necesita un módulo de entrada (sus `import` relativos, de forma recursiva), sin repetir y en el orden en
 * que los evalúa el navegador (primero las dependencias). El último de la lista es el propio módulo de entrada.
 *
 * @return list<string> rutas desde la raíz del sitio, sin barra inicial
 */
function site_module_graph(string $entry): array
{
    $order = [];
    $seen = [];
    $visit = static function (string $file) use (&$visit, &$order, &$seen): void {
        if (isset($seen[$file])) return;
        $seen[$file] = true;
        $path = dirname(__DIR__) . '/' . $file;
        if (is_file($path) && preg_match_all("/^import (?:\{[^}]*\} from )?'(\.[^']+)';/m", (string) file_get_contents($path), $imports)) {
            foreach ($imports[1] as $relative) {
                $parts = [];
                foreach (explode('/', dirname($file) . '/' . $relative) as $segment) {
                    if ($segment === '..') array_pop($parts);
                    elseif ($segment !== '.' && $segment !== '') $parts[] = $segment;
                }
                $visit(implode('/', $parts));
            }
        }
        $order[] = $file;
    };
    $visit($entry);
    return $order;
}

/**
 * <head> de un módulo de entrada: import map que añade ?v= (versión por contenido) a cada submódulo —las importaciones
 * relativas no heredan el ?v= del archivo que las importa— y modulepreload de todos ellos para que se descarguen en paralelo
 * en vez de en cascada. La entrada en sí se enlaza con site_module_script_tag().
 */
function site_module_head_tags(string $entry): string
{
    $modules = array_slice(site_module_graph($entry), 0, -1); // sin la entrada: es la última del recorrido
    if (!$modules) return '';
    $map = [];
    $html = '';
    foreach ($modules as $file) {
        $versioned = '/' . $file . '?v=' . site_asset_version($file);
        $map['/' . $file] = $versioned;
        $html .= '<link rel="modulepreload" href="' . $versioned . '">' . "\n    ";
    }
    return '<script type="importmap">' . json_encode(['imports' => $map], JSON_UNESCAPED_SLASHES) . '</script>' . "\n    " . rtrim($html);
}

/** <script type="module"> de un módulo de entrada (deferido, en orden con el resto de scripts diferidos de la página). */
function site_module_script_tag(string $entry): string
{
    return '<script type="module" src="/' . $entry . '?v=' . site_asset_version($entry) . '"></script>';
}
