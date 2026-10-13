<?php
declare(strict_types=1);

/**
 * Pruebas de caracterización: congelan lo que la web hace HOY para poder reestructurar el código sin cambiar
 * su comportamiento por accidente. Cada resultado se compara con un archivo «dorado» de tests/golden/.
 *
 *   php tests/characterization.php            compara con los archivos dorados (falla si algo cambia)
 *   php tests/characterization.php --update   reescribe los archivos dorados (solo si el cambio es intencionado)
 *
 * Qué congela
 *   - Catálogos: opciones de diseño, galerías premium, capacidades de galería, secciones, parejas de fuentes.
 *   - Validación de ajustes: qué opciones se aceptan, qué se rechaza y con qué mensaje, y cómo se normalizan.
 *   - Marcado HTML: bloques de la portada y formularios del panel (con espacios normalizados).
 *   - Atributos que se ponen en <body> según los ajustes.
 */

$root = dirname(__DIR__);
$goldenDir = __DIR__ . '/golden';
$update = in_array('--update', $argv ?? [], true);

// Datos propios de esta prueba: nunca los reales.
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'characterization-' . bin2hex(random_bytes(4));
mkdir($tmp . '/config/microblog', 0775, true);
putenv('SITE_CONFIG_DIR=' . $tmp . '/config');
putenv('MICROBLOG_DATA_DIR=' . $tmp . '/config/microblog');

require_once $root . '/inc/helpers.php';
require_once $root . '/inc/site-settings.php';
require_once $root . '/inc/page-blocks.php';
require_once $root . '/inc/microblog.php';

if (!is_dir($goldenDir)) mkdir($goldenDir, 0775, true);

/** Convierte un valor en texto estable (JSON legible) para compararlo. */
function golden_json(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}

/** HTML con los espacios normalizados y una etiqueta por línea, para que el archivo dorado sea revisable. */
function golden_html(string $html): string
{
    $html = preg_replace('/\?v=\d+/', '?v=X', $html) ?? $html; // versión por fecha del archivo (cambia con cada copia)
    $html = preg_replace('/\s+/', ' ', $html) ?? $html;
    $html = preg_replace('/>\s*</', ">\n<", $html) ?? $html;
    return trim($html) . "\n";
}

$failures = [];
function golden_check(string $name, string $actual): void
{
    global $goldenDir, $update, $failures;
    $path = $goldenDir . '/' . $name;
    if ($update) { file_put_contents($path, $actual); echo "  actualizado: $name\n"; return; }
    if (!is_file($path)) { $failures[] = "$name: falta el archivo dorado (ejecuta con --update)"; return; }
    $expected = str_replace("\r\n", "\n", (string) file_get_contents($path));
    if ($expected === $actual) return;
    $a = explode("\n", $expected); $b = explode("\n", $actual);
    for ($i = 0, $n = max(count($a), count($b)); $i < $n; $i++) {
        if (($a[$i] ?? null) !== ($b[$i] ?? null)) {
            $failures[] = "$name: difiere en la línea " . ($i + 1) . "\n      esperado: " . substr((string) ($a[$i] ?? '(fin)'), 0, 160) . "\n      actual:   " . substr((string) ($b[$i] ?? '(fin)'), 0, 160);
            return;
        }
    }
}

// ---------------------------------------------------------------------------------------------------------------
// 1. Catálogos
// ---------------------------------------------------------------------------------------------------------------
golden_check('design-choices.json', golden_json(site_design_choices()));
golden_check('settings-defaults.json', golden_json(site_settings_defaults()));
golden_check('font-pairs.json', golden_json(site_font_pairs()));
golden_check('premium-galleries.json', golden_json(['definitions' => site_premium_gallery_definitions(), 'choices' => site_premium_gallery_choices(), 'standard_settings' => site_standard_gallery_settings()]));
golden_check('gallery-capabilities.json', golden_json(site_gallery_capabilities()));
golden_check('sections.json', golden_json(['definitions' => site_section_definitions(), 'toggles' => site_toggle_keys()]));
golden_check('social-networks.json', golden_json(site_social_networks()));
golden_check('editable-texts.json', golden_json(['keys' => site_editable_text_keys(), 'labels' => site_admin_text_labels(), 'catalog' => array_map(static fn(array $e): array => $e, site_text_catalog())]));

// ---------------------------------------------------------------------------------------------------------------
// 2. Validación de ajustes
// ---------------------------------------------------------------------------------------------------------------
$defaults = site_settings_defaults();
$validation = ['accepted_options' => [], 'rejected_options' => [], 'cases' => []];
foreach (site_design_choices() as $key => $options) {
    foreach (array_keys($options) as $option) {
        try { site_settings_validate(array_replace($defaults, [$key => (string) $option])); $validation['accepted_options'][$key][] = (string) $option; }
        catch (InvalidArgumentException $error) { $validation['rejected_options'][$key][(string) $option] = $error->getMessage(); }
    }
    try { site_settings_validate(array_replace($defaults, [$key => 'valor-que-no-existe'])); $validation['rejected_options'][$key]['valor-que-no-existe'] = 'ACEPTADO (debería rechazarse)'; }
    catch (InvalidArgumentException $error) { $validation['rejected_options'][$key]['valor-que-no-existe'] = $error->getMessage(); }
}
$cases = [
    'defaults' => [],
    'premium_deck' => ['gallery_premium' => 'deck'],
    'premium_bubbles_sin_columnas' => ['gallery_premium' => 'bubbles', 'photos_desktop' => 20],
    'orden_normalizado' => ['section_order' => ['social', 'gallery', 'social', 'desconocido', 5]],
    'orden_invalido' => ['section_order' => 'gallery'],
    'interruptores_apagados' => ['show_categories' => false, 'show_map' => false, 'show_project' => false, 'show_microblog' => false, 'show_social' => false, 'show_header_mobile' => false, 'show_header_desktop' => false],
    'columnas_fuera_de_rango' => ['columns_mobile' => 9],
    'fotos_visibles_invalidas' => ['photos_desktop' => 7],
    'titulo_largo' => ['site_title' => str_repeat('a', 81)],
    'titulo_con_salto' => ['site_title' => "uno\ndos"],
    'texto_demasiado_largo' => ['texts' => ['admin_area_name' => str_repeat('x', 121)]],
    'texto_personalizado' => ['texts' => ['text_bdd8f61b94c52379' => 'Título personalizado']],
    'imagen_perfil_ruta_invalida' => ['profile_image' => '../../etc/passwd'],
];
foreach ($cases as $name => $override) {
    try {
        $out = site_settings_validate(array_replace($defaults, $override));
        $validation['cases'][$name] = ['result' => 'ok', 'settings' => $out];
    } catch (InvalidArgumentException $error) {
        $validation['cases'][$name] = ['result' => 'rechazado', 'message' => $error->getMessage()];
    }
}
golden_check('settings-validation.json', golden_json($validation));

// Migraciones heredadas, redes sociales, URLs, interruptores, carga de archivos antiguos y guardado.
ini_set('error_log', $tmp . '/php-errors.log');
$edge = [
    'grid_masonry_retirado' => ['grid' => 'masonry'],
    'perfiles_antiguos' => ['instagram_url' => 'https://www.instagram.com/alguien', 'threads_url' => 'https://www.threads.net/@alguien', 'social_links' => []],
    'perfiles_antiguos_ejemplo' => ['instagram_url' => 'https://example.com', 'social_links' => []],
    'red_desconocida' => ['social_links' => [['network' => 'friendster', 'url' => 'https://example.com/x', 'handle' => '@x']]],
    'red_url_http' => ['social_links' => [['network' => 'x', 'url' => 'http://example.com/x', 'handle' => '@x']]],
    'red_usuario_largo' => ['social_links' => [['network' => 'x', 'url' => 'https://example.com/x', 'handle' => str_repeat('a', 121)]]],
    'redes_mas_de_doce' => ['social_links' => array_map(static fn(int $n): array => ['network' => 'x', 'url' => "https://example.com/$n", 'handle' => "@u$n"], range(1, 14))],
    'redes_no_lista' => ['social_links' => 'instagram'],
    'redes_fila_vacia' => ['social_links' => [['network' => '', 'url' => '', 'handle' => ''], ['network' => 'bluesky', 'url' => 'https://bsky.app/profile/a', 'handle' => 'a']]],
    'web_personal_http' => ['profile_website_url' => 'http://example.com'],
    'web_personal_vacia' => ['profile_website_url' => ''],
    'texts_no_lista' => ['texts' => 'no'],
    'paginas_no_lista' => ['pages' => 'no'],
    'pagina_con_html_peligroso' => ['pages' => ['legal' => '<p>Hola</p><script>alert(1)</script><a href="javascript:alert(1)" onclick="x()">enlace</a>']],
    'pagina_no_texto' => ['pages' => ['legal' => ['no']]],
    'interruptores_como_texto' => ['show_map' => 'no', 'show_social' => '0', 'show_project' => 'true', 'show_categories' => 'off', 'show_microblog' => 'yes'],
    'logo_ruta_invalida' => ['logo_image' => 'logo.png'],
    'columnas_cero' => ['columns_desktop' => 0],
    'columnas_texto' => ['columns_desktop' => 'tres'],
    'fotos_todas' => ['photos_mobile' => 0, 'photos_desktop' => 0],
    'titulo_igual_al_defecto' => ['site_title' => site_title_default()],
];
$validation = ['cases' => [], 'load' => [], 'save' => []];
foreach ($edge as $name => $override) {
    try { $validation['cases'][$name] = ['result' => 'ok', 'settings' => site_settings_validate(array_replace($defaults, $override))]; }
    catch (InvalidArgumentException $error) { $validation['cases'][$name] = ['result' => 'rechazado', 'message' => $error->getMessage()]; }
}
$loadFiles = [
    'galeria_inmersiva_antigua' => json_encode(['gallery_desktop' => 'immersive', 'gallery_mobile' => 'art-walk']),
    'grid_masonry_antiguo' => json_encode(['grid' => 'masonry']),
    'json_dañado' => '{no es json',
    'json_no_lista' => '"texto"',
    'claves_desconocidas' => json_encode(['palette' => 'ocean', 'clave_nueva_del_futuro' => 1]),
    'paleta_invalida' => json_encode(['palette' => 'no-existe']),
    'perfiles_antiguos' => json_encode(['instagram_url' => 'https://www.instagram.com/vieja', 'social_links' => []]),
    'vacio' => '{}',
];
foreach ($loadFiles as $name => $content) {
    $file = $tmp . '/load-' . md5($name) . '.json';
    file_put_contents($file, $content);
    $validation['load'][$name] = site_settings_load($file);
}
$validation['load']['archivo_inexistente'] = site_settings_load($tmp . '/no-existe.json');
// Guardado: escribe el JSON validado y conserva los valores dormidos de una galería premium.
$saveFile = $tmp . '/save-test.json';
site_settings_save(array_replace($defaults, ['palette' => 'forest', 'columns_desktop' => 6]), $saveFile);
$validation['save']['primer_guardado'] = json_decode((string) file_get_contents($saveFile), true);
site_settings_save(array_replace(json_decode((string) file_get_contents($saveFile), true), ['gallery_premium' => 'bubbles', 'columns_desktop' => 2]), $saveFile);
$validation['save']['premium_conserva_columnas'] = json_decode((string) file_get_contents($saveFile), true);
try { site_settings_save(array_replace($defaults, ['palette' => 'nada']), $saveFile); $validation['save']['invalido'] = 'ACEPTADO'; }
catch (InvalidArgumentException $error) { $validation['save']['invalido'] = $error->getMessage(); }
golden_check('settings-edge-cases.json', golden_json($validation));

// ---------------------------------------------------------------------------------------------------------------
// 3. Marcado HTML: formularios del panel y bloques de portada
// ---------------------------------------------------------------------------------------------------------------
foreach (['content', 'design', 'texts', 'social', 'publication'] as $section) {
    golden_check("form-$section.html", golden_html(site_settings_form('token-de-prueba', $defaults, $section)));
}

$items = [];
foreach ([1 => 'Catedral', 2 => 'Paisajes', 3 => 'Catedral'] as $n => $category) {
    $items[] = ['title' => "Foto $n", 'description' => "Descripción $n", 'slug' => "foto-$n", 'category' => $category, 'original' => "foto-$n.jpg",
        'desktop' => "imagenes/desktop/foto-$n.webp", 'mobile' => "imagenes/mobile/foto-$n.webp", 'desktop_w' => 1600, 'desktop_h' => 1067, 'mobile_w' => 800, 'mobile_h' => 533,
        'aspect' => 1.5, 'latitude' => 42.6, 'longitude' => -5.57, 'mtime' => 1760000000 + $n, 'featured' => $n === 1, 'draft' => false, 'order' => null, 'sidecar' => ''];
}
$context = [
    'author' => 'Autor de prueba', 'categories' => ['Catedral', 'Paisajes'], 'galleryItems' => $items, 'collectionCovers' => [], 'heroItem' => $items[0],
    'rootDir' => $root, 'siteSettings' => array_replace($defaults, ['show_map' => true, 'show_microblog' => true, 'show_social' => true, 'show_project' => true]),
    'totalFotos' => 3, 'yearLabel' => '2025',
];
foreach (['header-desktop', 'header-mobile', 'categories', 'gallery', 'map', 'project', 'social'] as $block) {
    ob_start();
    try { $html = page_block_render($block, $context); } finally { ob_end_clean(); }
    golden_check("block-$block.html", golden_html($html));
}
// Microblog: con posts fijos (fechas fijas) para que el marcado sea estable.
$mbPosts = [
    ['id' => 'aaaaaaaa01', 'parent' => null, 'created' => 1760000100, 'updated' => 1760000100, 'level' => 2, 'pinned' => true, 'text' => "Hola [mundo](https://example.com) y https://example.org", 'quote' => ['text' => 'Cita', 'source' => 'Fuente', 'url' => 'https://example.net'], 'links' => [['url' => 'https://example.com/x', 'title' => 'Enlace']], 'images' => []],
    ['id' => 'aaaaaaaa02', 'parent' => 'aaaaaaaa01', 'created' => 1760000200, 'updated' => 1760000200, 'level' => 0, 'pinned' => false, 'text' => 'Respuesta', 'quote' => null, 'links' => [], 'images' => []],
];
file_put_contents($tmp . '/config/microblog/posts.json', json_encode(['posts' => $mbPosts]));
golden_check('block-microblog.html', golden_html(page_block_render('microblog', $context)));
$thread = microblog_render_thread($mbPosts[0], [$mbPosts[1]]);
golden_check('microblog-thread.html', golden_html($thread));
require_once $root . '/inc/microblog-composer.php';
golden_check('microblog-composer.html', golden_html(microblog_composer_html('token-de-prueba')));

// ---------------------------------------------------------------------------------------------------------------
// 4. Atributos del <body> según los ajustes
// ---------------------------------------------------------------------------------------------------------------
$attributes = [];
foreach ([['palette' => 'ocean', 'header_desktop' => 'museum'], ['font_pair' => 'night', 'gallery_premium' => 'deck'], ['show_map' => false, 'hover' => 'tilt']] as $i => $override) {
    file_put_contents($tmp . '/config/site-settings.json', json_encode($override));
    $attributes["caso_$i"] = $override + ['body' => site_design_attributes()];
}
@unlink($tmp . '/config/site-settings.json');
golden_check('body-attributes.json', golden_json($attributes));

// Limpieza de la carpeta temporal.
foreach (glob($tmp . '/config/microblog/*') ?: [] as $file) @unlink($file);
@unlink($tmp . '/config/microblog/posts.lock');
@rmdir($tmp . '/config/microblog'); @rmdir($tmp . '/config'); @rmdir($tmp);

if ($failures) {
    fwrite(STDERR, "Caracterización: " . count($failures) . " diferencias\n  - " . implode("\n  - ", $failures) . "\n");
    exit(1);
}
echo $update ? "Archivos dorados actualizados.\n" : "Caracterización verificada: el comportamiento actual no ha cambiado.\n";
