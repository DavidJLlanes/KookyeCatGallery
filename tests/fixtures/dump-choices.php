<?php
declare(strict_types=1);

/**
 * Vuelca en JSON las opciones de personalización que ofrece el sitio, para que las pruebas recorran el
 * catálogo real (y no una lista copiada a mano que se desactualice).
 *
 *   php tests/fixtures/dump-choices.php [<carpeta-del-sitio>]
 */
$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__, 2);
require_once $root . '/inc/site-settings.php';

$choices = site_design_choices();
echo json_encode([
    'palette' => array_keys($choices['palette']),
    'font_pair' => array_keys($choices['font_pair']),
    'header_mobile' => array_keys($choices['header_mobile']),
    'header_desktop' => array_keys($choices['header_desktop']),
    'gallery_mobile' => array_keys($choices['gallery_mobile']),
    'gallery_desktop' => array_keys($choices['gallery_desktop']),
    'hover' => array_keys($choices['hover']),
    'pagination_shape' => array_keys($choices['pagination_shape']),
    'gallery_premium' => array_keys($choices['gallery_premium']),
    'grid' => array_keys($choices['grid']),
], JSON_PRETTY_PRINT), "\n";
