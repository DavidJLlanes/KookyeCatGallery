<?php
declare(strict_types=1);

/**
 * Prueba de estructura: falla si algo está fuera de sitio.
 *   1. Los .php de la raíz son puntos de entrada conocidos (tests/structure-rules.json).
 *   2. Nombres: inc/ y assets/ en minúsculas con guiones; clases de core/ modules/ themes/ en PascalCase.
 *   3. Cada archivo de inc/ está cargado por alguien (sin huérfanos).
 *   4. Cada CSS y JS de assets/ está referenciado por algún PHP, JS, el service worker o un manifiesto (sin huérfanos).
 *   5. Cada clase con espacio de nombres está en la ruta que le toca (y se carga con el autocargador).
 *   6. Trinquete: las rutas del VPS escritas a mano no pueden aumentar (solo bajar, hasta llegar a 0 en la Fase 8).
 *
 * Con --update-ratchet guarda el nuevo máximo cuando ha bajado.
 */

require_once __DIR__ . '/../core/bootstrap.php';

$root = dirname(__DIR__);
$rulesPath = __DIR__ . '/structure-rules.json';
$rules = json_decode((string) file_get_contents($rulesPath), true, 512, JSON_THROW_ON_ERROR);
$problems = [];

function structure_files(string $dir, string $root, array $skip, array &$out = []): array
{
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $full = $dir . '/' . $name;
        $rel = ltrim(substr($full, strlen($root)), '/');
        foreach ($skip as $prefix) {
            if ($rel === $prefix || str_starts_with($rel, $prefix . '/')) continue 2;
        }
        if (is_dir($full)) structure_files($full, $root, $skip, $out);
        else $out[] = $rel;
    }
    return $out;
}

$all = structure_files($root, $root, $rules['ignorar']);
$php = array_values(array_filter($all, fn ($f) => str_ends_with($f, '.php')));
$text = [];
foreach ($all as $file) {
    if (preg_match('/\.(php|js|cjs|css|json|html|webmanifest|md|txt|xml)$/', $file)) $text[$file] = (string) file_get_contents($root . '/' . $file);
}
$referencedBy = static function (string $needle, array $exceptFiles) use ($text): bool {
    foreach ($text as $file => $content) {
        if (in_array($file, $exceptFiles, true) || str_starts_with($file, 'tests/') || str_starts_with($file, 'docs/')) continue;
        if (str_contains($content, $needle)) return true;
    }
    return false;
};

// 1. PHP de la raíz.
foreach ($php as $file) {
    if (str_contains($file, '/')) continue;
    if (!in_array($file, $rules['entradas'], true)) $problems[] = "PHP en la raíz que no es un punto de entrada conocido: $file (muévelo a inc/ o añádelo a entradas en tests/structure-rules.json)";
}
foreach ($rules['entradas'] as $entry) {
    if (!is_file($root . '/' . $entry)) $problems[] = "Punto de entrada declarado pero inexistente: $entry (quítalo de tests/structure-rules.json)";
}

// 2. Nombres.
foreach ($all as $file) {
    $name = basename($file);
    if (preg_match('#^(inc|assets/css|assets/js)/#', $file) && preg_match('/\.(php|css|js)$/', $name)) {
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9.]+)*\.(php|css|js)$/', $name)) $problems[] = "Nombre fuera de convención (minúsculas con guiones): $file";
    }
    // themes/<tipo>/<id>.php son definiciones de opciones (devuelven un array), no clases.
    if (preg_match('#^themes/[a-z0-9-]+/[^/]+\.php$#', $file)) {
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*\.php$/', $name)) $problems[] = "Definición fuera de convención (minúsculas con guiones): $file";
    } elseif (preg_match('#^core/Settings/migrations/[^/]+\.php$#', $file)) {
        // Migraciones de ajustes: NNNN-nombre.php (también lo comprueba Migrations al cargarlas).
        if (!preg_match('/^\d{4}-[a-z0-9]+(-[a-z0-9]+)*\.php$/', $name)) $problems[] = "Migración fuera de convención (NNNN-nombre.php): $file";
    } elseif (preg_match('#^(core|modules|themes)/#', $file) && str_ends_with($name, '.php') && $name !== 'bootstrap.php') {
        if (!preg_match('/^[A-Z][A-Za-z0-9]*\.php$/', $name)) $problems[] = "Clase fuera de convención (PascalCase.php): $file";
    }
}

// 3. Sin huérfanos en inc/ (los parciales cargados con include dinámico se declaran en «dinamicos»).
foreach ($php as $file) {
    if (!str_starts_with($file, 'inc/')) continue;
    $dir = dirname($file);
    if (in_array($dir, $rules['dinamicos'], true)) continue;
    if (!$referencedBy(basename($file), [$file])) $problems[] = "Archivo de inc/ que nadie carga: $file";
}

// 4. Sin CSS/JS huérfanos.
foreach ($all as $file) {
    if (!preg_match('#^assets/(css|js)/[^/]+\.(css|js)$#', $file)) continue;
    if (!$referencedBy(basename($file), [$file])) $problems[] = "CSS/JS que nadie referencia: $file";
}

// 5. Clases con espacio de nombres en su sitio.
foreach ($php as $file) {
    if (!preg_match('#^(core|modules|themes)/#', $file) || basename($file) === 'bootstrap.php') continue;
    if (preg_match('#^themes/[a-z0-9-]+/[^/]+\.php$#', $file)) continue; // definiciones: las valida la prueba de catálogos
    if (preg_match('#^core/Settings/migrations/[^/]+\.php$#', $file)) continue; // migraciones: no son clases
    $source = $text[$file] ?? '';
    if (!preg_match('/^namespace\s+([A-Za-z0-9_\\\\]+);/m', $source, $namespace)) { $problems[] = "Falta «namespace» en $file"; continue; }
    if (!preg_match('/^(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+([A-Za-z0-9_]+)/m', $source, $declared)) { $problems[] = "No declara ninguna clase: $file"; continue; }
    $expected = \Kcg\Core\Autoloader::pathFor($namespace[1] . '\\' . $declared[1]);
    if ($expected === null || realpath($expected) !== realpath($root . '/' . $file)) $problems[] = "La clase {$namespace[1]}\\{$declared[1]} debería estar en " . ($expected === null ? '(sin raíz)' : substr($expected, strlen($root) + 1)) . ", no en $file";
}

// 6. Trinquete de rutas del VPS escritas a mano.
$vps = 0;
$vpsWhere = [];
foreach ($php as $file) {
    // installation.php es el único sitio donde viven las rutas y la dirección de esta web.
    if (str_starts_with($file, 'tests/') || $file === 'installation.php') continue;
    $count = preg_match_all('/davidjimenezllanes/i', $text[$file] ?? '');
    if ($count) { $vps += $count; $vpsWhere[] = "$file ($count)"; }
}
$max = (int) ($rules['rutasVps']['maximo'] ?? 0);
if ($vps > $max) $problems[] = "Hay $vps rutas del VPS escritas a mano y el máximo permitido es $max. Usa site_config_dir() o la futura función de rutas. Aparecen en: " . implode(', ', $vpsWhere);
if ($vps < $max) {
    if (in_array('--update-ratchet', $argv ?? [], true)) {
        $rules['rutasVps']['maximo'] = $vps;
        file_put_contents($rulesPath, json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        echo "Trinquete actualizado: $max → $vps\n";
    } else {
        echo "Aviso: ahora hay $vps rutas del VPS (máximo registrado $max). Ejecuta con --update-ratchet para bajar el máximo.\n";
    }
}

if ($problems) {
    fwrite(STDERR, "FALLO de estructura (" . count($problems) . "):\n - " . implode("\n - ", $problems) . "\n");
    exit(1);
}
echo 'OK: estructura (' . count($php) . ' PHP, ' . count($all) . " archivos; rutas del VPS: $vps/$max).\n";
