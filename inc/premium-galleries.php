<?php
declare(strict_types=1);

/**
 * Galerías premium: registro y utilidades.
 *
 * Una galería premium sustituye por completo al bloque «Galería» estándar. Es un diseño cerrado,
 * pensado como un todo (maquetación, animación e interacción), que se integra con la paleta de
 * colores (variables CSS --bg, --fg, --accent…) y con las cabeceras (siempre van encima del bloque).
 *
 * Estructura de cada galería premium (ejemplo: «deck», Estilo Baraja):
 *
 *   inc/premium/<clave>.php            Marcado HTML. Se incluye desde inc/blocks/gallery.php.
 *   assets/premium/<clave>/<clave>.css Estilos (solo se cargan si la galería está activa).
 *   assets/premium/<clave>/<clave>.js  Comportamiento (solo se carga si la galería está activa).
 *
 * Mientras hay una galería premium activa se IGNORAN los ajustes estándar de la galería
 * (ver site_premium_ignored_settings()) y el panel desactiva sus campos.
 *
 * Para añadir una galería premium nueva: ver docs/galerias-premium.md.
 */

/**
 * Catálogo de galerías premium: clave => datos.
 *
 *   label        Nombre que se muestra en el panel.
 *   description  Una frase para el panel.
 *   partial      Archivo de marcado dentro de inc/premium/ (sin .php).
 *   css / js     Rutas de los recursos, relativas a la raíz de la web.
 *   honors       Ajustes de la galería estándar que SÍ aplica (el resto se ignora). Opcional.
 *
 * @return array<string, array{label: string, description: string, partial: string, css: string, js: string, honors?: list<string>}>
 */
function site_premium_gallery_definitions(): array
{
    return [
        'deck' => [
            'label' => 'Estilo Baraja',
            'description' => 'Pantalla completa. Las fotos se reparten como una baraja de cartas: cada foto sale hacia la izquierda '
                . 'dejando ver el mazo en 3D. Se avanza con la rueda del ratón o deslizando el dedo hacia arriba.',
            'partial' => 'deck',
            'css' => 'assets/premium/deck/deck.css',
            'js' => 'assets/premium/deck/deck.js',
        ],
        'bubbles' => [
            'label' => 'Estilo Burbujas',
            'description' => 'Pantalla completa. Las fotos son círculos de distintos tamaños con un pequeño marco, repartidos al azar por toda '
                . 'la pantalla y flotando lentamente. Al pulsar uno se abre la foto con un efecto rebote. Respeta «Fotos visibles a la vez» y se pagina.',
            'partial' => 'bubbles',
            'css' => 'assets/premium/bubbles/bubbles.css',
            'js' => 'assets/premium/bubbles/bubbles.js',
            'honors' => ['photos_mobile', 'photos_desktop'],
        ],
        // «Estilo Cuadrados» es «Estilo Burbujas» con cuadrados: comparte marcado, estilos y comportamiento (la forma la fija
        // el atributo data-shape del marcado, que sale de la clave de la galería activa).
        'squares' => [
            'label' => 'Estilo Cuadrados',
            'description' => 'Igual que «Estilo Burbujas», pero con cuadrados de distintos tamaños: pantalla completa, repartidos al azar, '
                . 'flotando lentamente, paginados, y la foto se abre con un efecto rebote.',
            'partial' => 'bubbles',
            'css' => 'assets/premium/bubbles/bubbles.css',
            'js' => 'assets/premium/bubbles/bubbles.js',
            'honors' => ['photos_mobile', 'photos_desktop'],
        ],
    ];
}

/**
 * Opciones del selector «Galería premium»: 'none' (galería estándar) más cada galería premium.
 *
 * @return array<string, string>
 */
function site_premium_gallery_choices(): array
{
    $choices = ['none' => 'Ninguna · usar la galería estándar'];
    foreach (site_premium_gallery_definitions() as $key => $definition) $choices[$key] = $definition['label'];
    return $choices;
}

/**
 * Todos los ajustes de la galería estándar (los que una galería premium puede ignorar).
 *
 * @return list<string>
 */
function site_standard_gallery_settings(): array
{
    return ['grid', 'gallery_mobile', 'gallery_desktop', 'pagination_shape',
        'columns_mobile', 'columns_desktop', 'photos_mobile', 'photos_desktop'];
}

/**
 * Ajustes de la galería estándar que una galería premium ignora.
 *
 * Con una galería premium activa el panel desactiva estos campos (no se envían al guardar, así
 * que se conservan los valores guardados) y la web no los aplica. Cada galería puede respetar algunos
 * (clave `honors` de su definición): p. ej. «Estilo Burbujas» sigue usando «Fotos visibles a la vez».
 *
 * @param string|null $gallery Clave de la galería premium; null = ninguno respetado (todos ignorados).
 * @return list<string>
 */
function site_premium_ignored_settings(?string $gallery = null): array
{
    $honors = $gallery !== null ? (site_premium_gallery_definitions()[$gallery]['honors'] ?? []) : [];
    return array_values(array_diff(site_standard_gallery_settings(), $honors));
}

/**
 * Clave de la galería premium activa, o null si se usa la galería estándar.
 *
 * @param array|null $settings Ajustes ya cargados; null = los guardados.
 */
function site_premium_gallery_active(?array $settings = null): ?string
{
    $settings ??= site_settings_load();
    $key = (string) ($settings['gallery_premium'] ?? 'none');
    return isset(site_premium_gallery_definitions()[$key]) ? $key : null;
}

/**
 * Etiqueta <link> con los estilos de la galería premium activa (cadena vacía si no hay ninguna).
 * Se imprime dentro de <head>.
 */
function premium_gallery_head_tags(): string
{
    $key = site_premium_gallery_active();
    if ($key === null) return '';
    $css = site_premium_gallery_definitions()[$key]['css'];
    return '<link rel="stylesheet" href="/' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '?v='
        . htmlspecialchars(asset_ver(dirname(__DIR__) . '/' . $css), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Etiqueta <script> con el comportamiento de la galería premium activa (cadena vacía si no hay ninguna).
 * Se imprime al final del <body>, después de main.js.
 */
function premium_gallery_script_tags(): string
{
    $key = site_premium_gallery_active();
    if ($key === null) return '';
    $js = site_premium_gallery_definitions()[$key]['js'];
    return '<script src="/' . htmlspecialchars($js, ENT_QUOTES, 'UTF-8') . '?v='
        . htmlspecialchars(asset_ver(dirname(__DIR__) . '/' . $js), ENT_QUOTES, 'UTF-8') . '" defer></script>';
}
