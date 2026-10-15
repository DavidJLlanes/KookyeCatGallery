<?php
declare(strict_types=1);

// Lógica de instalación compartida (core/Installer.php): validación de los datos, comprobaciones del servidor con su explicación y
// aviso cuando una carpeta no se puede usar. La instalación completa (web y línea de comandos) la prueba tests/install.cjs.
// Uso: php tests/installer.php

require_once dirname(__DIR__) . '/core/bootstrap.php';

use Kcg\Core\Installation;
use Kcg\Core\Installer;

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function remove_tree(string $path): void
{
    if (!is_dir($path)) { @unlink($path); return; }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') remove_tree($path . '/' . $entry);
    }
    @rmdir($path);
}

$tmp = sys_get_temp_dir() . '/installer-' . bin2hex(random_bytes(4));
mkdir($tmp . '/site', 0755, true);
putenv('GALLERY_PRIVATE_DIR=' . $tmp . '/private');
Installation::reset();

$installer = new Installer($tmp . '/site');

// Validación: dirección HTTPS sin ruta, usuario y contraseña.
check($installer->validate(' https://ejemplo.com/ ', 'david', 'una-contrasena-larga') === 'https://ejemplo.com', 'La dirección se normaliza sin barra final.');
foreach ([['http://ejemplo.com', 'david', 'una-contrasena-larga', 'HTTPS'], ['https://ejemplo.com/ruta', 'david', 'una-contrasena-larga', 'HTTPS'],
    ['https://ejemplo.com', 'a b', 'una-contrasena-larga', 'usuario'], ['https://ejemplo.com', 'david', 'corta', 'contraseña']] as [$url, $user, $password, $word]) {
    try {
        $installer->validate($url, $user, $password);
        check(false, "Debe rechazar: $url / $user / $password");
    } catch (InvalidArgumentException $error) {
        check(str_contains($error->getMessage(), $word), "El mensaje nombra «{$word}»: " . $error->getMessage());
    }
}

// Comprobaciones: cada una con estado y texto; con permisos todo está bien y se puede instalar.
$checks = $installer->checks();
foreach ($checks as $item) {
    check(in_array($item['status'], ['ok', 'warn', 'fail'], true) && $item['label'] !== '', 'Cada comprobación trae estado y texto.');
    if ($item['status'] !== 'ok') check($item['hint'] !== '', 'Lo que no está bien explica qué hacer: ' . $item['label']);
}
check(!$installer->blocked($checks), 'Con permisos y extensiones, nada impide instalar: ' . json_encode(array_filter($checks, static fn(array $c): bool => $c['status'] === 'fail')));
foreach (Installation::WRITABLE_DIRECTORIES as $relative) check(is_dir($tmp . '/site/' . $relative), "Se prepara $relative/.");
check(is_dir($tmp . '/private'), 'Se prepara la carpeta privada.');

// Varias carpetas imposibles a la vez: todas salen en la misma lista, con cómo arreglarlo.
remove_tree($tmp . '/site');
mkdir($tmp . '/site', 0755, true);
file_put_contents($tmp . '/site/img', 'ocupa el sitio de la carpeta');
file_put_contents($tmp . '/site/uploads', 'otro');
$checks = $installer->checks();
$failed = array_column(array_filter($checks, static fn(array $c): bool => $c['status'] === 'fail'), 'label');
check($installer->blocked($checks), 'Con carpetas imposibles se bloquea la instalación.');
check(in_array('img/', $failed, true) && in_array('uploads/', $failed, true), 'Salen todas las carpetas que fallan: ' . implode(', ', $failed));
check(in_array('Cómo arreglarlo', $failed, true), 'Se explica cómo arreglarlo.');
$hint = $installer->fixHint();
check(str_contains($hint, 'preparar-servidor.sh') && str_contains($hint, 'Hosting compartido'), 'La ayuda cubre servidor propio y hosting compartido.');

// Sin administrador, y la instalación no empieza si el servidor no cumple (no deja nada a medias).
check(!$installer->hasAdministrator(), 'Todavía no hay administrador.');
try {
    $installer->install('https://ejemplo.com', 'david', 'una-contrasena-larga');
    check(false, 'No debe instalar con carpetas imposibles.');
} catch (RuntimeException $error) {
    check(str_contains($error->getMessage(), 'No se ha cambiado nada'), 'Avisa de que no se ha cambiado nada.');
}
check(!$installer->hasAdministrator() && !is_file($tmp . '/private/upload-auth.php'), 'No queda ningún administrador a medias.');

// El script de línea de comandos y el instalador web existen y no repiten la lógica.
$root = dirname(__DIR__);
foreach (['instalar.sh', 'scripts/install-cli.php', 'scripts/preparar-servidor.sh'] as $file) check(is_file($root . '/' . $file), "Falta $file.");
check(!str_contains((string) file_get_contents($root . '/instalar.sh'), "\r"), 'instalar.sh usa saltos de línea LF.');
foreach (['scripts/defaults/install.php', 'scripts/install-cli.php'] as $entry) {
    $source = (string) file_get_contents($root . '/' . $entry);
    check(str_contains($source, 'Installer') && !str_contains($source, 'password_hash('), "$entry usa core/Installer.php en vez de repetir la lógica.");
}

putenv('GALLERY_PRIVATE_DIR');
remove_tree($tmp);
echo "Installer tests passed.\n";
