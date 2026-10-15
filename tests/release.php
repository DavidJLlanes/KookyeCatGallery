<?php
declare(strict_types=1);

// Pruebas de la exportación del núcleo y del proceso de actualización (Fase 8): qué va en el núcleo, qué nunca se toca,
// la copia de seguridad, la vuelta atrás y qué pasa si la versión nueva viene rota o si alguien editó el núcleo a mano.
// Uso: php tests/release.php  (usa carpetas temporales; necesita git)

$root = dirname(__DIR__);
require_once $root . '/core/bootstrap.php';

use Kcg\Core\Release\Manifest;
use Kcg\Core\Release\Updater;

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'release-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function put(string $path, string $content): void
{
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, $content);
}
function remove_tree(string $path): void
{
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        is_dir("$path/$entry") ? remove_tree("$path/$entry") : @unlink("$path/$entry");
    }
    @rmdir($path);
}
function copy_tree(string $from, string $to): void
{
    @mkdir($to, 0775, true);
    foreach (scandir($from) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        is_dir("$from/$entry") ? copy_tree("$from/$entry", "$to/$entry") : copy("$from/$entry", "$to/$entry");
    }
}
/** @return array<string, string> ruta => huella de todo el árbol */
function snapshot(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) $files[substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $dir)) + 1)] = sha1_file($file->getPathname());
    }
    ksort($files);
    return $files;
}

// -----------------------------------------------------------------------------------------------------------------
// 1. Exportar el núcleo
// -----------------------------------------------------------------------------------------------------------------
$core = $tmp . '/core-v1';
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/export-core.php') . ' ' . escapeshellarg($core) . ' v1 2>&1', $output, $status);
check($status === 0 && is_file($core . '/core-manifest.json'), 'La exportación del núcleo falló: ' . implode(' ', $output));
$manifest = Manifest::read($core);
check($manifest !== null && $manifest['version'] === 'v1' && count($manifest['files']) > 300, 'El manifiesto del núcleo debe listar sus archivos.');
foreach (['installation.php', 'david.webp', 'escudoleon.webp', 'assets/icons/apple-touch-icon.png', '.github/workflows/deploy.yml', 'docs/refactor/plan.md'] as $own) {
    check(!is_file("$core/$own"), "El núcleo no debe llevar «{$own}» (es de esta web).");
}
foreach (['installation.example.php', 'core/Installation.php', 'index.php', 'profile-placeholder.svg', 'scripts/update.php', 'scripts/export-core.php', 'favicon.svg', 'README.md', '.htaccess', 'robots.txt'] as $needed) {
    check(is_file("$core/$needed"), "El núcleo debe llevar «{$needed}».");
}
check(file_get_contents($core . '/favicon.svg') === file_get_contents($root . '/scripts/defaults/favicon.svg'), 'El favicon del núcleo debe ser el genérico.');
check(file_get_contents($core . '/.htaccess') === file_get_contents($root . '/scripts/defaults/htaccess'), 'El .htaccess del núcleo debe ser el genérico.');
check(is_file($core . '/install.php') && !isset($manifest['files']['install.php']), 'install.php va en el paquete (instalación nueva) pero no en el manifiesto: una actualización nunca lo vuelve a poner.');
check(is_file($core . '/LICENSE') && is_file($core . '/var/.htaccess') && is_file($core . '/img/.htaccess'), 'El paquete lleva licencia y protección de carpetas privadas.');
check(!isset($manifest['files']['core-manifest.json']) && !isset($manifest['files']['installation.php']), 'El manifiesto no lista archivos de la instalación.');

// Paquete para hosting (--produccion): sin pruebas ni documentación de desarrollo, con lo necesario para instalar y actualizar.
$prod = $tmp . '/core-prod';
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/export-core.php') . ' ' . escapeshellarg($prod) . ' v1 --produccion 2>&1', $prodOutput, $prodStatus);
check($prodStatus === 0 && is_file($prod . '/core-manifest.json'), 'La exportación de producción falló: ' . implode(' ', $prodOutput));
foreach (['tests', '.github', 'scripts/defaults', 'scripts/export-core.php', 'scripts/core-files.json', 'docs/CONTRIBUTING.md', 'docs/refactor', 'pendiente.md'] as $dev) {
    check(!file_exists("$prod/$dev"), "El paquete de producción no debe llevar «{$dev}».");
}
foreach (['index.php', 'install.php', 'scripts/update.php', 'core/Release/Updater.php', 'docs/instalacion.md', 'README.md', 'LICENSE', 'installation.example.php'] as $needed) {
    check(is_file("$prod/$needed"), "El paquete de producción debe llevar «{$needed}».");
}
$hexProd = $tmp . '/core-hex';
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/scripts/export-core.php') . ' ' . escapeshellarg($hexProd) . ' abc1234 --produccion 2>&1');
check(is_file($hexProd . '/version.json') && json_decode((string) file_get_contents($hexProd . '/version.json'), true)['version'] === 'abc1234' && !isset(Manifest::read($hexProd)['files']['version.json']),
    'Con una versión hexadecimal el paquete trae version.json (la web lo consulta) y no lo lista en el manifiesto.');
check(str_contains((string) file_get_contents($hexProd . '/service-worker.js'), "const BUILD_VERSION = 'abc1234';"), 'El service worker lleva la versión del paquete (así los navegadores renuevan sus cachés).');
check(count(Manifest::read($prod)['files']) < count($manifest['files']) && !isset(Manifest::read($prod)['files']['tests/release.php']), 'El manifiesto de producción no lista las pruebas.');

// El núcleo no menciona ninguna web concreta en su código (el README sí enlaza al repositorio del proyecto).
$mentions = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($core, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($core) + 1);
    if (!$file->isFile() || !preg_match('/\.(php|js|json|html|xml|svg|css|txt|md|example)$/', $relative) || str_starts_with($relative, 'tests/') || str_starts_with($relative, 'docs/') || in_array($relative, ['README.md', 'scripts/defaults/README.md'], true)) continue; // el README enlaza al repositorio público del proyecto
    if (preg_match('/davidjimenezllanes|djl\.com\.es|davidjllanes/i', (string) file_get_contents($file->getPathname()))) $mentions[] = $relative;
}
check(!$mentions, 'El núcleo menciona una web concreta en: ' . implode(', ', $mentions));

// -----------------------------------------------------------------------------------------------------------------
// 2. Instalación de prueba con la versión 1 + lo suyo
// -----------------------------------------------------------------------------------------------------------------
$site = $tmp . '/site';
$private = $tmp . '/private';
copy_tree($core, $site);
put($site . '/installation.php', "<?php return ['site_url' => 'https://prueba.example'];\n");
put($site . '/img/foto.jpg', 'foto');
put($site . '/data/cache.json', '{"cache":true}');
put($site . '/microblog/imagen.webp', 'imagen');
put($private . '/site-settings.json', '{"palette":"ocean"}');
$ownBefore = array_filter(snapshot($site), static fn(string $path): bool => Manifest::isInstallationPath($path) && $path !== Manifest::FILE, ARRAY_FILTER_USE_KEY);
check(count($ownBefore) >= 4, 'Faltan archivos propios en la instalación de prueba.');
$v1 = snapshot($site);

// Versión 2 = versión 1 con un archivo cambiado, uno nuevo y uno retirado.
$next = $tmp . '/core-v2';
copy_tree($core, $next);
put($next . '/inc/helpers.php', file_get_contents($core . '/inc/helpers.php') . "\n// cambio de la versión 2\n");
put($next . '/inc/nuevo-de-prueba.php', "<?php\n// archivo nuevo\n");
unlink($next . '/pendiente.md');
Manifest::write($next, 'v2', Manifest::scan($next));

// -----------------------------------------------------------------------------------------------------------------
// 3. Plan, aplicación y vuelta atrás
// -----------------------------------------------------------------------------------------------------------------
$backups = $private . '/backups';
$updater = new Updater($site, $next, $backups, $private);
$plan = $updater->plan();
check($plan['to'] === 'v2' && $plan['from'] === 'v1' && $plan['add'] === ['inc/nuevo-de-prueba.php'] && $plan['change'] === ['inc/helpers.php'] && $plan['remove'] === ['pendiente.md'] && $plan['modified'] === [],
    'El plan debe listar exactamente lo que cambia: ' . json_encode($plan));
check(snapshot($site) === $v1, 'plan() no debe tocar nada.');

put($private . '/site-settings.json', '{"palette":"ocean","antes":"de actualizar"}');
$id = $updater->apply();
check(is_file($site . '/inc/nuevo-de-prueba.php') && !is_file($site . '/pendiente.md') && str_contains((string) file_get_contents($site . '/inc/helpers.php'), 'cambio de la versión 2'), 'La actualización debe aplicar los cambios.');
check(Manifest::read($site)['version'] === 'v2', 'El manifiesto instalado debe pasar a la versión nueva.');
check(array_filter(snapshot($site), static fn(string $path): bool => Manifest::isInstallationPath($path) && $path !== Manifest::FILE, ARRAY_FILTER_USE_KEY) === $ownBefore, 'Actualizar no debe tocar nada propio de la instalación.');
check(is_dir("$backups/$id") && Updater::backups($backups) === [$id], 'Debe quedar una copia de seguridad.');
check(file_get_contents("$backups/$id/private/site-settings.json") === '{"palette":"ocean","antes":"de actualizar"}', 'La copia debe incluir los ajustes.');

put($private . '/site-settings.json', '{"palette":"ocean","cambiado":"despues"}');
check(Updater::rollback($site, $backups, null, $private) === $id, 'rollback() sin id debe restaurar la última copia.');
check(snapshot($site) === $v1, 'Tras la vuelta atrás el sitio debe quedar exactamente como estaba.');
check(file_get_contents($private . '/site-settings.json') === '{"palette":"ocean","antes":"de actualizar"}', 'La vuelta atrás restaura también los ajustes.');

// -----------------------------------------------------------------------------------------------------------------
// 4. Un archivo del núcleo editado a mano no se pisa sin permiso
// -----------------------------------------------------------------------------------------------------------------
put($site . '/inc/helpers.php', file_get_contents($site . '/inc/helpers.php') . "\n// mi retoque\n");
$edited = snapshot($site);
$plan = (new Updater($site, $next, $backups, $private))->plan();
check($plan['modified'] === ['inc/helpers.php'], 'El plan debe avisar del archivo modificado a mano.');
try {
    (new Updater($site, $next, $backups, $private))->apply();
    check(false, 'Sin --overwrite-modified no debe pisar un archivo modificado.');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'inc/helpers.php') && snapshot($site) === $edited, 'El rechazo debe nombrar el archivo y no cambiar nada.');
}
$id = (new Updater($site, $next, $backups, $private))->apply(true);
check(!str_contains((string) file_get_contents($site . '/inc/helpers.php'), 'mi retoque'), 'Con permiso se aplica la versión nueva.');
Updater::rollback($site, $backups, $id, $private);
check(snapshot($site) === $edited && str_contains((string) file_get_contents($site . '/inc/helpers.php'), 'mi retoque'), 'La copia conserva el retoque a mano y la vuelta atrás lo devuelve.');
put($site . '/inc/helpers.php', file_get_contents($core . '/inc/helpers.php'));
check(snapshot($site) === $v1, 'Estado base recuperado.');

// -----------------------------------------------------------------------------------------------------------------
// 5. Una versión rota se deshace sola
// -----------------------------------------------------------------------------------------------------------------
$broken = $tmp . '/core-roto';
copy_tree($next, $broken);
put($broken . '/inc/helpers.php', "<?php\nfunction rota( {\n");
Manifest::write($broken, 'v3-rota', Manifest::scan($broken));
try {
    (new Updater($site, $broken, $backups, $private))->apply();
    check(false, 'Una versión con errores de sintaxis no debe quedar instalada.');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'deshecho') && str_contains($error->getMessage(), 'inc/helpers.php'), 'El error debe decir que se deshizo y dónde está el fallo: ' . $error->getMessage());
}
check(snapshot($site) === $v1, 'Tras una actualización fallida el sitio debe quedar como estaba.');

// -----------------------------------------------------------------------------------------------------------------
// 6. Un paquete que no es del núcleo se rechaza
// -----------------------------------------------------------------------------------------------------------------
try {
    (new Updater($site, $tmp . '/private', $backups, $private))->plan();
    check(false, 'Una carpeta sin core-manifest.json no es un paquete.');
} catch (InvalidArgumentException) {
}

remove_tree($tmp);
echo "Release tests passed.\n";
