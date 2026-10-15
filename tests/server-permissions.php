<?php
declare(strict_types=1);

// Permisos del servidor: una sola lista de carpetas escribibles (Installation::WRITABLE_DIRECTORIES), que usan el instalador y
// scripts/preparar-servidor.sh. El instalador las comprueba todas de una vez y el script las prepara todas de una vez.
// Uso: php tests/server-permissions.php

require_once dirname(__DIR__) . '/core/bootstrap.php';

use Kcg\Core\Installation;

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

// 1. El script del servidor y la clase dicen lo mismo.
$script = (string) file_get_contents(dirname(__DIR__) . '/scripts/preparar-servidor.sh');
check(preg_match('/^WRITABLE_DIRECTORIES="([^"]+)"/m', $script, $match) === 1, 'preparar-servidor.sh declara WRITABLE_DIRECTORIES.');
check(explode(' ', $match[1]) === Installation::WRITABLE_DIRECTORIES, 'preparar-servidor.sh y Installation::WRITABLE_DIRECTORIES tienen que listar las mismas carpetas.');
check(!str_contains($script, "\r"), 'preparar-servidor.sh usa saltos de línea LF (si no, «sh» no lo ejecuta).');

// 2. Una raíz vacía: se crean todas las carpetas y no hay problemas.
$root = sys_get_temp_dir() . '/permissions-' . bin2hex(random_bytes(4));
mkdir($root, 0755, true);
check(Installation::unwritableDirectories($root) === [], 'Con permisos, no hay carpetas con problemas.');
foreach (Installation::WRITABLE_DIRECTORIES as $relative) check(is_dir($root . '/' . $relative), "Se crea $relative/.");

// 3. Varias carpetas imposibles a la vez: salen todas en una única lista (no una por intento).
remove_tree($root);
mkdir($root, 0755, true);
file_put_contents($root . '/img', 'archivo que ocupa el sitio de la carpeta');
file_put_contents($root . '/uploads', 'otro');
$problems = Installation::unwritableDirectories($root);
check(isset($problems['img'], $problems['uploads']), 'Se listan todas las carpetas que fallan a la vez: ' . implode(', ', array_keys($problems)));
check(!isset($problems['data']), 'Las que sí se pueden crear no se listan.');

// 4. El instalador y los mensajes del panel apuntan al mismo arreglo.
$installer = (string) file_get_contents(dirname(__DIR__) . '/core/Installer.php');
check(str_contains($installer, 'unwritableDirectories') && str_contains($installer, 'preparar-servidor.sh'), 'El instalador comprueba los permisos y señala el script.');
check(str_contains((string) file_get_contents(dirname(__DIR__) . '/scripts/core-files.json'), '"uploads"'), 'El paquete lleva la carpeta uploads/.');

remove_tree($root);
echo "Server permissions tests passed.\n";
