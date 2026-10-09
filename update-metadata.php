<?php
/**
 * Analiza todos los .txt y añade categoría + coordenadas automáticamente
 * Ejecutar una sola vez y luego borrar este archivo
 */

$imgDir = __DIR__ . '/img';
$files = glob($imgDir . '/*.txt');

// Palabras clave para categorías (en orden de prioridad - primero coincidente gana)
$categories = [
    'Blanco y Negro' => ['blanco y negro', 'bn', 'monocromo'],  // Primero, porque es un atributo
    'Nocturna' => ['noche', 'nocturna', 'anocheciendo', 'atardecer', 'iluminad', 'luna', 'estrella'],
    'Animales' => ['animal', 'pájaro', 'pajaro', 'ave', 'gato', 'perro', 'mariposa', 'insecto', 'bestia', 'fauna', 'pollo', 'caballo', 'oveja', 'vaca'],
    'Agua' => ['embalse', 'lago', 'rio', 'río', 'reflejo', 'agua', 'cascada', 'arroyo', 'corriente', 'acuático', 'marino'],
    'Catedral' => ['catedral', 'obispo', 'colegiata', 'vidrieras', 'nave', 'techo central', 'absice'],
    'Arquitectura' => ['palacio', 'castillo', 'botines', 'gaudi', 'parador', 'iglesia', 'diputacion', 'san marcos', 'isidoro', 'edificio', 'construcción', 'fachada', 'puente'],
    'Paisajes' => ['montaña', 'picos', 'europa', 'sajambre', 'arbas', 'mampodre', 'valle', 'sierra', 'oteros', 'caín', 'ladera', 'cumbre'],
    'Naturaleza' => ['bosque', 'otoñal', 'cascada', 'pinos', 'árbol', 'flora', 'verde', 'vegetación', 'arboles', 'árboles', 'campo'],
    'Calles' => ['calle', 'plaza', 'avenida', 'paseo', 'carrera', 'parque', 'rincón', 'casco antiguo', 'urbano'],
];

// Coordenadas precisas de lugares leoneses (lat, lng)
$locations = [
    'catedral' => [40.6270, -5.5898],
    'absice' => [40.6270, -5.5898],
    'vidrieras' => [40.6270, -5.5898],
    'plaza mayor' => [40.6265, -5.5895],
    'plaza santo domingo' => [40.6298, -5.5917],
    'parque del cid' => [40.6238, -5.5855],
    'parque' => [40.6238, -5.5855],
    'calle' => [40.6280, -5.5900],
    'palacio de botines' => [40.6265, -5.5892],
    'botines' => [40.6265, -5.5892],
    'gaudi' => [42.4549, -6.0635],             // Astorga
    'astorga' => [42.4549, -6.0635],
    'parador' => [40.5947, -5.5727],
    'san marcos' => [40.5947, -5.5727],
    'colegiata' => [40.5947, -5.5727],
    'isidoro' => [40.6290, -5.5920],
    'picos' => [43.1994, -4.8664],
    'europa' => [43.1994, -4.8664],
    'sajambre' => [43.1850, -4.9200],
    'arbas' => [42.8500, -5.6500],
    'mampodre' => [43.0200, -5.1500],
    'embalse' => [42.7500, -5.5000],
    'porma' => [42.7080, -5.4450],
    'villameca' => [42.8500, -5.5500],
    'luna' => [42.8700, -5.3200],
    'oteros' => [42.5900, -5.2700],
    'caín' => [43.1600, -4.8500],
    'valle' => [42.8000, -5.4000],
    'bosque' => [42.7500, -5.5000],
    'cascada' => [42.7500, -5.5000],
    'rio' => [42.6500, -5.4000],
];

$updated = 0;
$errors = [];

foreach ($files as $txtPath) {
    $basename = basename($txtPath);
    $content = file_get_contents($txtPath);
    if ($content === false) continue;

    // Si ya tiene metadatos, salta
    if (preg_match('/# Categoría:/', $content)) {
        echo "✓ {$basename} ya tiene metadatos\n";
        continue;
    }

    // Extrae título y descripción actuales
    $parts = preg_split('/^\s*---\s*$/m', $content, 2);
    $title = trim($parts[0] ?? '');
    $desc = trim($parts[1] ?? '');
    $combined = strtolower($title . ' ' . $desc);

    // Busca categoría por palabras clave
    $foundCategory = 'Otras';
    foreach ($categories as $cat => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($combined, strtolower($kw)) !== false) {
                $foundCategory = $cat;
                break 2;
            }
        }
    }

    // Busca ubicación por palabras clave
    $foundLat = 42.5985;  // Centro de León (defecto)
    $foundLng = -5.5672;
    foreach ($locations as $place => $coords) {
        if (strpos($combined, strtolower($place)) !== false) {
            [$foundLat, $foundLng] = $coords;
            break;
        }
    }

    // Reconstruye el .txt con metadatos
    $newContent = $title;
    if (!empty($desc)) {
        $newContent .= "\n---\n" . $desc;
    }
    $newContent .= "\n\n# Categoría: {$foundCategory}\n";
    $newContent .= "# Coordenadas: {$foundLat}, {$foundLng}\n";

    // Preserva líneas de IA si existen
    if (preg_match('/# auto-\w+\/[\w\-\.]+/', $content, $m)) {
        $newContent .= $m[0] . "\n";
    }

    if (file_put_contents($txtPath, $newContent)) {
        echo "✓ {$basename}: {$foundCategory} ({$foundLat}, {$foundLng})\n";
        $updated++;
    } else {
        $errors[] = $basename;
    }
}

echo "\n═══════════════════════════════════════\n";
echo "Actualizados: {$updated}\n";
if (!empty($errors)) {
    echo "Errores: " . implode(', ', $errors) . "\n";
}
echo "═══════════════════════════════════════\n";
?>
