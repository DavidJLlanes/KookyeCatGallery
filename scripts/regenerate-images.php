<?php
declare(strict_types=1);

/**
 * Genera de una vez las versiones WebP de todas las fotos (escritorio y móvil) con la receta actual: lado mayor 1800 px y calidad 80.
 *
 *   php scripts/regenerate-images.php
 *
 * Tras actualizar la web, las fotos ya publicadas con una receta anterior se rehacen solas, pero solo unas pocas por visita para no frenarla.
 * Este script las rehace todas ahora, desde la línea de comandos (ejecútalo con el usuario del servidor web, p. ej.
 * `sudo -u www-data php scripts/regenerate-images.php`, para que los archivos queden con sus permisos). No toca los originales.
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/inc/processor.php';

$root = dirname(__DIR__);
$started = microtime(true);
$items = (new ImageProcessor($root))->processAll(PHP_INT_MAX);
$cache = json_decode((string) @file_get_contents($root . '/data/cache.json'), true);
$cache = is_array($cache) ? $cache : [];
printf("%d fotos revisadas; %d con la receta actual (%.1f s).\n", count($items), count(array_filter($cache, static fn($entry): bool => !empty($entry['recipe']))), microtime(true) - $started);
