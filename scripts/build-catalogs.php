<?php
declare(strict_types=1);

/**
 * Genera docs/catalogos.md con todas las opciones de diseño que hay en themes/ (paletas, fuentes, parejas, cabeceras, galerías…),
 * leídas de los registros: añadir un archivo a themes/ lo añade al catálogo sin tocar nada más.
 *
 *   php scripts/build-catalogs.php          escribe docs/catalogos.md
 *   php scripts/build-catalogs.php --check  no escribe; sale con error si el archivo está desactualizado (lo usa tests/catalog-docs.php)
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

use Kcg\Core\Registry;

/** Texto del catálogo a partir de los registros. */
function catalog_markdown(): string
{
    $row = static fn(array $cells): string => '| ' . implode(' | ', array_map(static fn($c): string => str_replace(['|', "\n"], ['\\|', ' '], (string) $c), $cells)) . ' |';
    $table = static function (array $headers, array $rows) use ($row): string {
        return $row($headers) . "\n" . $row(array_fill(0, count($headers), '---')) . "\n" . implode("\n", array_map($row, $rows)) . "\n";
    };
    $count = static fn(string $type): int => count(Registry::for($type)->all());

    $out = "# Catálogo de opciones de diseño\n\n"
        . "Generado por `php scripts/build-catalogs.php` a partir de `themes/`; no se edita a mano (la prueba `tests/catalog-docs.php` falla si está desactualizado). "
        . "Para añadir una opción, crea su archivo en la carpeta que corresponda (ver [CONTRIBUTING.md](CONTRIBUTING.md)).\n\n";

    $rows = [];
    foreach (Registry::for('palettes')->all() as $id => $palette) {
        $vars = $palette['vars'] ?? [];
        $rows[] = [$id, $palette['name'], $palette['mode'] === 'dark' ? 'oscuro' : 'claro', $vars['bg'] ?? 'del diseño base', $vars['fg'] ?? 'del diseño base', $vars['accent'] ?? 'del diseño base'];
    }
    $out .= '## Paletas de color (' . $count('palettes') . ")\n\n" . $table(['Id', 'Nombre', 'Modo', 'Fondo', 'Texto', 'Acento'], $rows) . "\n";

    $rows = [];
    foreach (Registry::for('fonts')->all() as $id => $font) $rows[] = [$id, $font['name'], $font['weights']];
    $out .= '## Familias tipográficas (' . $count('fonts') . ")\n\n" . $table(['Id', 'Nombre', 'Pesos'], $rows) . "\n";

    $rows = [];
    $fonts = Registry::for('fonts');
    foreach (Registry::for('pairs')->all() as $id => $pair) {
        $rows[] = [$id, $pair['name'], $pair['group'], ($fonts->get($pair['heading'])['name'] ?? $pair['heading']) . ' + ' . ($fonts->get($pair['body'])['name'] ?? $pair['body'])];
    }
    $out .= '## Parejas de tipografías (' . $count('pairs') . ")\n\n" . $table(['Id', 'Nombre', 'Grupo', 'Títulos + texto'], $rows) . "\n";

    foreach (['headers-mobile' => 'Cabeceras de móvil', 'headers-desktop' => 'Cabeceras de escritorio'] as $type => $title) {
        $rows = [];
        foreach (Registry::for($type)->all() as $id => $header) {
            $files = array_merge($header['css'] ?? [], $header['js'] ?? []);
            $rows[] = [$id, $header['name'], $files ? implode(', ', array_map(static fn(string $f): string => '`' . $f . '`', $files)) : 'solo el estilo común'];
        }
        $out .= "## $title (" . $count($type) . ")\n\n" . $table(['Id', 'Nombre', 'Archivos propios'], $rows) . "\n";
    }

    $rows = [];
    foreach (Registry::for('galleries')->all() as $id => $gallery) {
        $rows[] = [$id, $gallery['name'], implode(', ', $gallery['devices']), implode(', ', $gallery['capabilities'] ?? [])];
    }
    $out .= '## Galerías estándar (' . $count('galleries') . ")\n\n" . $table(['Id', 'Nombre', 'Dispositivos', 'Controles'], $rows) . "\n";

    foreach (['hover' => 'Efectos hover', 'grids' => 'Proporciones de foto', 'pagination-shapes' => 'Formas de paginación'] as $type => $title) {
        $rows = [];
        foreach (Registry::for($type)->all() as $id => $option) $rows[] = [$id, $option['name']];
        $out .= "## $title (" . $count($type) . ")\n\n" . $table(['Id', 'Nombre'], $rows) . "\n";
    }

    return rtrim($out) . "\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $target = dirname(__DIR__) . '/docs/catalogos.md';
    $markdown = catalog_markdown();
    if (in_array('--check', $argv ?? [], true)) {
        $current = is_file($target) ? str_replace("\r\n", "\n", (string) file_get_contents($target)) : '';
        if ($current !== $markdown) { fwrite(STDERR, "docs/catalogos.md está desactualizado: ejecuta php scripts/build-catalogs.php\n"); exit(1); }
        echo "OK: docs/catalogos.md al día.\n";
        exit(0);
    }
    file_put_contents($target, $markdown);
    echo "Escrito docs/catalogos.md.\n";
}
