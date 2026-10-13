<?php
declare(strict_types=1);

/**
 * Actualiza el núcleo de esta instalación con copia previa y vuelta atrás.
 *
 *   php scripts/update.php <carpeta-con-la-version-nueva>            aplica la actualización
 *   php scripts/update.php <carpeta> --plan                          solo muestra qué cambiaría
 *   php scripts/update.php <carpeta> --overwrite-modified            pisa también archivos del núcleo modificados a mano
 *   php scripts/update.php --list                                    lista las copias de seguridad
 *   php scripts/update.php --rollback [<copia>]                      restaura la última copia (o la indicada)
 *
 * La carpeta nueva es un paquete del núcleo (lleva core-manifest.json; ver scripts/export-core.php). Nunca se tocan
 * installation.php, las fotos, los datos ni la carpeta privada. Las copias se guardan en <carpeta privada>/backups/.
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

use Kcg\Core\Installation;
use Kcg\Core\Release\Updater;

$arguments = array_slice($argv, 1);
$flags = array_values(array_filter($arguments, static fn(string $a): bool => str_starts_with($a, '--')));
$positional = array_values(array_filter($arguments, static fn(string $a): bool => !str_starts_with($a, '--')));
$root = dirname(__DIR__);
$privateDir = Installation::privateDir();
$backups = $privateDir . '/backups';

try {
    if (in_array('--list', $flags, true)) {
        $ids = Updater::backups($backups);
        echo $ids ? implode("\n", $ids) . "\n" : "No hay copias de seguridad.\n";
        exit(0);
    }
    if (in_array('--rollback', $flags, true)) {
        $id = Updater::rollback($root, $backups, $positional[0] ?? null, $privateDir);
        echo "Restaurada la copia $id.\n";
        exit(0);
    }
    if (!$positional) {
        fwrite(STDERR, "Indica la carpeta con la versión nueva (o --list / --rollback). Ver el encabezado de scripts/update.php.\n");
        exit(2);
    }
    $updater = new Updater($root, $positional[0], $backups, $privateDir);
    $plan = $updater->plan();
    echo "Versión actual: {$plan['from']}  →  nueva: {$plan['to']}\n";
    echo count($plan['add']) . ' archivos nuevos, ' . count($plan['change']) . ' modificados, ' . count($plan['remove']) . " retirados.\n";
    if ($plan['modified']) echo "Modificados a mano en esta instalación (se perderían):\n  - " . implode("\n  - ", $plan['modified']) . "\n";
    if (in_array('--plan', $flags, true)) exit(0);
    $id = $updater->apply(in_array('--overwrite-modified', $flags, true));
    echo "Actualización aplicada. Copia de seguridad: $id\nSi algo no va bien: php scripts/update.php --rollback $id\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
