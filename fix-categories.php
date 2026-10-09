<?php
/**
 * EJECUTAR UNA SOLA VEZ en el navegador: /fix-categories.php
 * Actualiza todos los .txt con categorías y coordenadas automáticamente
 * Luego BORRA este archivo
 */

if ($_GET['confirm'] !== '1') {
    die('<h1>⚠️ Actualizar categorías</h1>
    <p>Este script actualizará TODOS los .txt con categorías y coordenadas.</p>
    <p><strong>Es seguro, solo añade metadatos.</strong></p>
    <p><a href="?confirm=1" style="padding: 10px 20px; background: #d4a574; color: black; text-decoration: none; border-radius: 4px; display: inline-block;">✓ Confirmar y ejecutar</a></p>
    ');
}

$imgDir = __DIR__ . '/img';
$files = glob($imgDir . '/*.txt');

$categories = [
    'Blanco y Negro' => ['blanco y negro', 'bn', 'monocromo'],
    'Nocturna' => ['noche', 'nocturna', 'anocheciendo', 'atardecer', 'iluminad', 'luna', 'estrella'],
    'Animales' => ['animal', 'pájaro', 'pajaro', 'ave', 'gato', 'perro', 'palomar'],
    'Agua' => ['embalse', 'lago', 'rio', 'río', 'reflejo', 'agua', 'cascada'],
    'Catedral' => ['catedral', 'obispo', 'colegiata', 'vidrieras', 'nave', 'techo central', 'absice'],
    'Arquitectura' => ['palacio', 'castillo', 'botines', 'gaudi', 'parador', 'iglesia', 'diputacion', 'san marcos'],
    'Paisajes' => ['montaña', 'picos', 'europa', 'sajambre', 'arbas', 'mampodre', 'valle', 'sierra'],
    'Naturaleza' => ['bosque', 'otoñal', 'cascada', 'pinos', 'árbol', 'flora', 'verde'],
    'Calles' => ['calle', 'plaza', 'avenida', 'paseo', 'parque', 'rincón'],
];

$locations = [
    'catedral' => [40.6270, -5.5898],
    'plaza mayor' => [40.6265, -5.5895],
    'plaza santo domingo' => [40.6298, -5.5917],
    'parque' => [40.6238, -5.5855],
    'calle' => [40.6280, -5.5900],
    'botines' => [40.6265, -5.5892],
    'astorga' => [42.4549, -6.0635],
    'parador' => [40.5947, -5.5727],
    'picos' => [43.1994, -4.8664],
    'sajambre' => [43.1850, -4.9200],
    'arbas' => [42.8500, -5.6500],
    'mampodre' => [43.0200, -5.1500],
    'embalse' => [42.7500, -5.5000],
    'porma' => [42.7080, -5.4450],
    'villameca' => [42.8500, -5.5500],
    'luna' => [42.8700, -5.3200],
    'oteros' => [42.5900, -5.2700],
    'caín' => [43.1600, -4.8500],
    'bosque' => [42.7500, -5.5000],
];

$updated = 0;
echo '<h2>Procesando...</h2><ul>';

foreach ($files as $txtPath) {
    $basename = basename($txtPath);
    $content = file_get_contents($txtPath);
    if ($content === false) continue;

    // Si ya tiene # Categoría:, salta
    if (preg_match('/# Categoría:/', $content)) {
        echo "<li>✓ {$basename} (ya tiene metadatos)</li>";
        continue;
    }

    // Lee el contenido
    $combined = strtolower($content);

    // Categoría
    $foundCategory = 'Otras';
    foreach ($categories as $cat => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($combined, strtolower($kw)) !== false) {
                $foundCategory = $cat;
                break 2;
            }
        }
    }

    // Ubicación
    $foundLat = 42.5985;
    $foundLng = -5.5672;
    foreach ($locations as $place => $coords) {
        if (strpos($combined, strtolower($place)) !== false) {
            [$foundLat, $foundLng] = $coords;
            break;
        }
    }

    // Reconstruye el .txt
    $parts = preg_split('/^\s*#.*$/m', $content);
    $cleanContent = trim($parts[0] ?? $content);

    $newContent = $cleanContent . "\n\n# Categoría: {$foundCategory}\n";
    $newContent .= "# Coordenadas: {$foundLat}, {$foundLng}\n";

    // Preserva líneas de IA
    if (preg_match('/# auto-[\w\/-\.]+/', $content, $m)) {
        $newContent .= $m[0] . "\n";
    }

    if (file_put_contents($txtPath, $newContent)) {
        echo "<li>✓ {$basename}: <strong>{$foundCategory}</strong></li>";
        $updated++;
    }
}

echo '</ul>';
echo "<h2 style='color: #d4a574;'>✓ Actualizado: {$updated} archivos</h2>";
echo '<p style="margin-top: 2rem; padding: 15px; background: #0a0a0a; border: 1px solid #d4a574; border-radius: 4px;">';
echo '<strong>⚠️ Ahora:</strong><br>';
echo '1. Recarga la web (F5)<br>';
echo '2. Si ves los chips de categorías y el mapa, <strong>borra este archivo (fix-categories.php)</strong> del VPS<br>';
echo '3. ✓ Listo';
echo '</p>';
?>
