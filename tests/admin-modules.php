<?php
declare(strict_types=1);

/**
 * Prueba del enrutador del panel (core/Admin/): cada módulo está bien declarado y no hay choques.
 *   · los módulos se descubren solos, con identificadores y posiciones únicos;
 *   · cada acción tiene un único dueño y un método que existe;
 *   · las acciones públicas son solo las del acceso;
 *   · el menú tiene entradas únicas, ordenadas y con icono;
 *   · ningún módulo reclama la misma pantalla que otro.
 */

require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/site-settings.php';
require_once __DIR__ . '/../inc/admin-shell.php';

use Kcg\Core\Admin\Router;

$problems = [];
$check = static function (bool $ok, string $message) use (&$problems): void { if (!$ok) $problems[] = $message; };

$modules = Router::modules();
$check($modules !== [], 'No se ha descubierto ningún módulo.');
$ids = array_map(static fn($m) => $m->id(), $modules);
$check(count($ids) === count(array_unique($ids)), 'Hay identificadores de módulo repetidos: ' . implode(', ', $ids));
foreach ($modules as $module) {
    $check(preg_match('/^[a-z]+$/', $module->id()) === 1, 'Identificador de módulo no válido: ' . $module->id());
    $check(basename(str_replace('\\', '/', $module::class), 'Module') === ucfirst($module->id()), 'El módulo «' . $module->id() . '» debería llamarse ' . ucfirst($module->id()) . 'Module.');
}

// Acciones: un solo dueño y un método existente.
$owners = [];
foreach ($modules as $module) {
    foreach ($module->actions() as $action => $method) {
        $check(!isset($owners[$action]), "La acción «{$action}» la declaran dos módulos (" . ($owners[$action] ?? '') . ' y ' . $module->id() . ').');
        $owners[$action] = $module->id();
        $check(preg_match('/^[a-z_]+$/', $action) === 1, "Nombre de acción no válido: $action");
        if ($module->id() !== 'microblog') $check(method_exists($module, $method), $module->id() . ": la acción «{$action}» apunta a un método que no existe ($method).");
    }
    foreach ($module->publicActions() as $action) {
        $check(isset($module->actions()[$action]), $module->id() . ": la acción pública «{$action}» no está entre sus acciones.");
        $check($module->id() === 'auth', $module->id() . ': solo el módulo de acceso puede tener acciones públicas.');
    }
}
$check(isset($owners['login'], $owners['logout']), 'Faltan las acciones de acceso (login y logout).');

// Menú: ids únicos, posiciones ordenadas y un icono para cada entrada.
$menu = Router::menu();
$check($menu !== [], 'El menú está vacío.');
foreach (array_keys($menu) as $id) {
    $check(adminIcon($id) !== adminIcon('no-existe-este-icono'), "La entrada de menú «{$id}» no tiene icono en adminIcon().");
}
$positions = [];
foreach ($modules as $module) foreach ($module->menu() as $entry) {
    $check(!isset($positions[$entry['id']]), "La entrada de menú «{$entry['id']}» está repetida.");
    $positions[$entry['id']] = $entry['order'];
    foreach (['href', 'label', 'short'] as $key) $check($entry[$key] !== '', "La entrada «{$entry['id']}» no tiene $key.");
}
$check(array_keys($menu) === array_keys($sorted = (static function (array $p): array { asort($p); return $p; })($positions)), 'El menú no sale ordenado por «order».');

// Pantallas: ningún módulo reclama la misma petición que otro (probado con cada parámetro conocido).
$probe = ['microblog', 'settings', 'mobile_preview', 'batch', 'categories', 'library', 'edit'];
foreach ($probe as $parameter) {
    $_GET = [$parameter => '1'];
    $claimants = array_values(array_filter($modules, static fn($m) => $m->wantsPage()));
    $check(count($claimants) <= 1, "La pantalla ?$parameter la reclaman varios módulos: " . implode(', ', array_map(static fn($m) => $m->id(), $claimants)));
}
$_GET = [];
$check(array_filter($modules, static fn($m) => $m->wantsPage()) === [], 'Sin parámetros ningún módulo debe reclamar la petición (es la pantalla de subir foto).');
$check(Router::activeMenu() === 'upload', 'Sin parámetros se resalta «Subir foto».');
foreach (['library' => 'library', 'categories' => 'categories', 'microblog' => 'microblog', 'edit' => 'library'] as $parameter => $expected) {
    $_GET = [$parameter => '1'];
    $check(Router::activeMenu() === $expected, "Con ?$parameter se debe resaltar «{$expected}».");
}
$_GET = ['settings' => '1', 'section' => 'design'];
$check(Router::activeMenu() === 'design', 'Con ?settings&section=design se debe resaltar «design».');

if ($problems) {
    fwrite(STDERR, 'FALLO del panel (' . count($problems) . "):\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}
printf("OK: módulos del panel (%d módulos, %d acciones, %d entradas de menú).\n", count($modules), count($owners), count($menu));
