<?php
declare(strict_types=1);

/**
 * Exporta el núcleo (todo menos lo propio de esta web) a una carpeta, listo para publicarlo o para actualizar otra instalación.
 *
 *   php scripts/export-core.php <carpeta-destino> [<versión>] [--produccion]
 *
 * Con --produccion el paquete es el que se sube a un hosting: sin pruebas, sin documentación de desarrollo ni herramientas
 * de mantenimiento (lo que lista «produccion_excluir» en scripts/core-files.json). Conserva scripts/update.php para poder
 * actualizar después y la guía de instalación (docs/instalacion.md).
 *
 * Parte de los archivos versionados con git, quita lo que lista scripts/core-files.json («excluir»), pone en su sitio los
 * archivos genéricos de core/defaults/ («sustituir») y escribe core-manifest.json con la huella de cada archivo.
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';

use Kcg\Core\Release\Manifest;

$root = dirname(__DIR__);
$production = in_array('--produccion', $argv, true);
$arguments = array_values(array_filter(array_slice($argv, 1), static fn(string $a): bool => !str_starts_with($a, '--')));
$destination = rtrim(str_replace('\\', '/', $arguments[0] ?? ''), '/');
if ($destination === '') { fwrite(STDERR, "Uso: php scripts/export-core.php <carpeta-destino> [<versión>] [--produccion]\n"); exit(2); }
$rules = json_decode((string) file_get_contents(__DIR__ . '/core-files.json'), true, 512, JSON_THROW_ON_ERROR);
if (is_dir($destination) && count(scandir($destination)) > 2) { fwrite(STDERR, "La carpeta de destino no está vacía: $destination\n"); exit(2); }
$version = ($arguments[1] ?? '') ?: trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse --short HEAD 2>/dev/null')) ?: date('Ymd');

$tracked = array_filter(explode("\n", (string) shell_exec('git -C ' . escapeshellarg($root) . ' ls-files')));
$exclusions = array_merge($rules['excluir'], $production ? ($rules['produccion_excluir'] ?? []) : []);
$excluded = static function (string $path) use ($exclusions): bool {
    foreach ($exclusions as $pattern) {
        if ($path === $pattern || (str_ends_with($pattern, '/') && str_starts_with($path, $pattern)) || (str_contains($pattern, '*') && fnmatch($pattern, $path))) return true;
    }
    return false;
};
$copied = 0;
foreach ($tracked as $path) {
    $path = trim($path);
    if ($path === '' || !is_file("$root/$path") || $excluded($path) || isset($rules['sustituir'][$path]) || Manifest::isInstallationPath($path)) continue;
    @mkdir(dirname("$destination/$path"), 0775, true);
    copy("$root/$path", "$destination/$path");
    $copied++;
}
foreach ($rules['sustituir'] as $path => $source) {
    if ($production && in_array($path, $exclusions, true)) continue; // solo si se excluye por su nombre exacto (docs/instalacion.md sí viaja)
    @mkdir(dirname("$destination/$path"), 0775, true);
    if (!copy("$root/$source", "$destination/$path")) { fwrite(STDERR, "No se pudo copiar $source\n"); exit(1); }
    $copied++;
}
foreach ($rules['carpetas'] ?? [] as $folder) { // carpetas de datos vacías (un .gitkeep las conserva al copiar o descomprimir)
    @mkdir("$destination/$folder", 0775, true);
    if (!is_file("$destination/$folder/.gitkeep")) file_put_contents("$destination/$folder/.gitkeep", '');
}
// El service worker nombra sus cachés con la versión: con una versión nueva, los navegadores sueltan lo viejo y cargan los archivos nuevos.
if (preg_match('/^[a-f0-9]{7,40}$/i', $version) && is_file("$destination/service-worker.js")) {
    $worker = (string) file_get_contents("$destination/service-worker.js");
    $stamped = preg_replace("/const BUILD_VERSION = '[^']*';/", "const BUILD_VERSION = '" . strtolower($version) . "';", $worker, 1, $replacements);
    if ($replacements === 1) file_put_contents("$destination/service-worker.js", $stamped);
}
Manifest::write($destination, $version, Manifest::scan($destination));
// version.json: la web (pwa.js) pregunta por él para avisar de versiones nuevas; sin el archivo cada visita da un 404.
if (preg_match('/^[a-f0-9]{7,40}$/i', $version)) file_put_contents("$destination/version.json", json_encode(['version' => strtolower($version)]) . "
");
echo "Núcleo exportado a $destination ($copied archivos, versión $version).\n";
