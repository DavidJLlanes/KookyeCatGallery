<?php
declare(strict_types=1);

/**
 * Bloques de la portada.
 *
 * La portada se compone de piezas independientes, una por archivo en inc/blocks/:
 *
 *   Cabeceras (fijas, no reordenables)   header-desktop · header-mobile
 *   Bloques reordenables                 categories · gallery · map · project · social
 *
 * El orden y la visibilidad de los bloques reordenables se configuran en el panel
 * (Diseño → Estructura de la página) y se guardan en `section_order` y `show_*`.
 * La definición de cada bloque (nombre, ayuda e interruptor) está en
 * site_section_definitions(), dentro de inc/site-settings.php.
 *
 * Para añadir un bloque nuevo: ver docs/estructura-de-bloques.md.
 */

/**
 * Pinta un bloque y devuelve su HTML.
 *
 * El archivo se incluye dentro de esta función, así que solo ve las variables de
 * $context (no el ámbito global de index.php). Cada bloque decide por sí mismo si
 * se muestra (p. ej. según `show_map`) y devuelve cadena vacía cuando no procede.
 *
 * @param string $name    Nombre del archivo en inc/blocks/ (sin .php).
 * @param array  $context Variables disponibles para el bloque.
 */
function page_block_render(string $name, array $context): string
{
    // Solo nombres conocidos: evita incluir archivos arbitrarios.
    if (!preg_match('/^[a-z-]+$/', $name) || !is_file(__DIR__ . '/blocks/' . $name . '.php')) return '';
    extract($context, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/blocks/' . $name . '.php';
    return (string) ob_get_clean();
}

/**
 * Pinta los bloques reordenables de la portada en el orden configurado.
 *
 * @param array  $context Variables disponibles para los bloques.
 * @param mixed  $order   Valor de `section_order` (se normaliza: claves desconocidas
 *                        o repetidas se descartan y las que falten se añaden al final).
 */
function page_blocks_render_home(array $context, mixed $order): string
{
    $html = '';
    foreach (site_section_order_normalize($order) as $key) $html .= page_block_render($key, $context);
    return $html;
}
