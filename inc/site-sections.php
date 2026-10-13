<?php
declare(strict_types=1);

/**
 * Bloques reordenables de la portada: definiciones, interruptores y orden. Cada bloque tiene su archivo en inc/blocks/ (ver inc/page-blocks.php).
 * (Cargado por inc/site-settings.php.)
 */

/**
 * Bloques reordenables de la portada, en su orden por defecto. Cada uno tiene su archivo en
 * inc/blocks/ (ver inc/page-blocks.php).
 * label: nombre en el panel · hint: ayuda · toggle: ajuste `show_*` que lo activa (null = siempre visible).
 */
function site_section_definitions(): array
{
    return [
        'categories' => ['label' => 'Categorías', 'hint' => 'Filtro por categorías', 'toggle' => 'show_categories'],
        'gallery' => ['label' => 'Bloque de galería', 'hint' => 'Fotos, buscador y presentación', 'toggle' => null],
        'map' => ['label' => 'Mapa', 'hint' => 'Mapa de ubicaciones', 'toggle' => 'show_map'],
        'project' => ['label' => '«El proyecto»', 'hint' => 'Texto y retrato del proyecto', 'toggle' => 'show_project'],
        'microblog' => ['label' => 'Microblog', 'hint' => 'Feed de notas, citas, enlaces e imágenes', 'toggle' => 'show_microblog'],
        'social' => ['label' => 'Redes Sociales', 'hint' => 'Perfiles y enlaces sociales', 'toggle' => 'show_social'],
    ];
}

/**
 * Nombres de los bloques reordenables (clave => etiqueta).
 */
function site_section_labels(): array
{
    return array_map(static fn(array $definition): string => $definition['label'], site_section_definitions());
}

/**
 * Ajustes de tipo interruptor (true/false): el de cada bloque que se puede ocultar más los de las
 * dos cabeceras. Los usan la validación y los atributos del <body>, así que añadir un bloque nuevo
 * con `toggle` en site_section_definitions() basta para que se valide y llegue al CSS.
 */
function site_toggle_keys(): array
{
    $keys = array_values(array_filter(array_column(site_section_definitions(), 'toggle')));
    return array_merge($keys, ['show_header_mobile', 'show_header_desktop']);
}

/**
 * Normaliza `section_order`: descarta claves desconocidas o repetidas y añade al final las que
 * falten, para que siempre estén todos los bloques.
 */
function site_section_order_normalize(mixed $order): array
{
    $known = array_keys(site_section_labels());
    $result = [];
    foreach (is_array($order) ? $order : [] as $key) {
        if (is_string($key) && in_array($key, $known, true) && !in_array($key, $result, true)) $result[] = $key;
    }
    return array_merge($result, array_values(array_diff($known, $result)));
}
