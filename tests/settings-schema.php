<?php
declare(strict_types=1);

/**
 * Prueba del esquema de ajustes (core/Settings/) y de sus migraciones:
 *   · cada ajuste está bien declarado (tipo, valor por defecto válido, sección, opciones…);
 *   · validar los valores por defecto no cambia nada, y validar dos veces da lo mismo que una;
 *   · las migraciones están numeradas sin huecos, son idempotentes y actualizan los formatos antiguos;
 *   · lo que se guarda lleva la versión actual de ajustes.
 */

$tmp = sys_get_temp_dir() . '/settings-schema-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);
putenv('SITE_CONFIG_DIR=' . $tmp);

require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/site-settings.php';

use Kcg\Core\Settings\Migrations;
use Kcg\Core\Settings\Schema;
use Kcg\Core\Settings\Store;
use Kcg\Core\Settings\Validator;

$problems = [];
$check = static function (bool $ok, string $message) use (&$problems): void { if (!$ok) $problems[] = $message; };

// 1. Declaración de cada ajuste.
$types = ['choice', 'bool', 'int-range', 'int-choice', 'order', 'title', 'text-map', 'html-map', 'path', 'https-url', 'social-list', 'version'];
$sections = ['design', 'content', 'social', 'texts', 'pages', 'system'];
$defaults = Schema::defaults();
foreach (Schema::definitions() as $key => $definition) {
    $check(in_array($definition['type'], $types, true), "$key: tipo desconocido «{$definition['type']}».");
    $check(array_key_exists('default', $definition), "$key: falta «default».");
    $check(in_array($definition['section'] ?? null, $sections, true), "$key: «section» debe ser una de " . implode(', ', $sections) . '.');
    switch ($definition['type']) {
        case 'choice':
            $choices = ($definition['choices'])();
            $check($choices !== [], "$key: no tiene ninguna opción.");
            $check(isset($choices[$defaults[$key]]), "$key: el valor por defecto «{$defaults[$key]}» no está entre sus opciones.");
            $check(isset($definition['label']), "$key: falta «label» (nombre en el panel).");
            break;
        case 'int-range':
            $check($defaults[$key] >= $definition['min'] && $defaults[$key] <= $definition['max'], "$key: el valor por defecto está fuera de rango.");
            $check(!empty($definition['error']), "$key: falta «error».");
            break;
        case 'int-choice':
            $check(in_array($defaults[$key], ($definition['options'])(), true), "$key: el valor por defecto no está entre sus opciones.");
            $check(!empty($definition['error']), "$key: falta «error».");
            break;
        case 'bool':
            $check(is_bool($defaults[$key]), "$key: el valor por defecto debe ser true o false.");
            break;
    }
}
// Cada clave del <body> y cada interruptor existen en el esquema.
foreach (array_merge(Schema::bodyChoiceKeys(), Schema::toggleKeys()) as $key) $check(Schema::has($key), "$key llega al <body> pero no está en el esquema.");
foreach (Schema::galleryKeys() as $key) $check(Schema::has($key), "$key figura como ajuste de galería pero no está en el esquema.");

// 2. Validación idempotente.
$once = Validator::validate($defaults);
$check($once === $defaults, 'Validar los valores por defecto debería devolverlos sin cambios.');
$legacy = ['grid' => 'masonry', 'instagram_url' => 'https://www.instagram.com/vieja', 'social_links' => [], 'palette' => 'ocean'];
$first = Validator::validate(array_replace($defaults, $legacy));
$check(Validator::validate($first) === $first, 'Validar dos veces debería dar lo mismo que una.');
$check($first['grid'] === 'adaptive' && $first['palette'] === 'ocean' && $first['social_links'][0]['network'] === 'instagram', 'La entrada heredada no se normalizó como se esperaba.');
$check(!array_key_exists('instagram_url', $first), 'Los campos heredados no deben pasar a los ajustes.');

// 3. Migraciones.
$migrations = Migrations::all();
$check($migrations !== [], 'No hay ninguna migración.');
$check(Schema::currentVersion() === count($migrations), 'La versión actual debe ser el número de migraciones.');
foreach ($migrations as $migration) {
    foreach ([[], $legacy, ['social_links' => 'no', 'texts' => 'no'], ['gallery_desktop' => 'immersive', 'gallery_mobile' => 'art-walk']] as $sample) {
        foreach ([false, true] as $fromFile) {
            $apply = $migration['apply'];
            $once = $apply($sample);
            $check($apply($once) === $once, 'La migración ' . sprintf('%04d', $migration['version']) . ' ' . $migration['name'] . ' no es idempotente.');
            $check(is_array($once), 'La migración ' . $migration['name'] . ' debe devolver un array.');
        }
    }
}
$check(Migrations::apply(['gallery_desktop' => 'immersive'])['gallery_desktop'] === 'immersive', 'Una migración «load» no debe aplicarse al validar un formulario.');
$check(Migrations::apply(['gallery_desktop' => 'immersive'], true)['gallery_desktop'] === 'standard', 'Una migración «load» debe aplicarse al cargar un archivo.');

// 4. Lo guardado lleva la versión y un archivo sin versión se actualiza.
$file = $tmp . '/site-settings.json';
Store::save($defaults, $file);
$saved = json_decode((string) file_get_contents($file), true);
$check(($saved['settings_version'] ?? null) === Schema::currentVersion(), 'El archivo guardado debe llevar la versión actual de ajustes.');
file_put_contents($file, json_encode(['palette' => 'forest', 'gallery_desktop' => 'art-walk']));
$loaded = Store::load($file);
$check($loaded['palette'] === 'forest' && $loaded['gallery_desktop'] === 'standard' && $loaded['settings_version'] === Schema::currentVersion(), 'Un archivo sin versión debe actualizarse al cargarlo.');

foreach (glob($tmp . '/*') ?: [] as $leftover) @unlink($leftover);
@rmdir($tmp);
if ($problems) {
    fwrite(STDERR, 'FALLO del esquema (' . count($problems) . "):\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}
printf("OK: esquema de ajustes (%d ajustes, %d migraciones, versión %d).\n", count(Schema::definitions()), count($migrations), Schema::currentVersion());
