<?php
declare(strict_types=1);

// El nombre de la web es uno solo: el título grande de la portada, el pie de página y el panel de administración lo muestran
// igual y se cambia desde un único sitio (Ajustes → Textos o la edición en línea). Las migraciones conservan el nombre de las
// webs que lo tenían repartido en varios textos. Cada caso se ejecuta en un proceso aparte (los textos se guardan en memoria).
// Uso: php tests/site-name.php

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'site-name-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

/** Ejecuta código PHP con la web cargada y unos ajustes guardados; devuelve lo que imprime como JSON. */
function probe(array $settings, string $code): array
{
    global $root, $tmp;
    $config = $tmp . '/config-' . bin2hex(random_bytes(3));
    mkdir($config, 0775, true);
    file_put_contents($config . '/site-settings.json', json_encode($settings, JSON_UNESCAPED_UNICODE));
    $script = "require_once '" . str_replace('\\', '/', $root) . "/inc/helpers.php'; require_once '" . str_replace('\\', '/', $root) . "/inc/site-settings.php'; "
        . "require_once '" . str_replace('\\', '/', $root) . "/inc/page-blocks.php'; $code";
    $env = ['SITE_CONFIG_DIR' => $config, 'KCG_INSTALLATION_FILE' => $tmp . '/sin-instalacion.php', 'PATH' => (string) getenv('PATH'), 'PHPRC' => (string) getenv('PHPRC'), 'SystemRoot' => (string) getenv('SystemRoot')];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
    $json = json_decode((string) end($lines), true);
    check(is_array($json), "La sonda no devolvió JSON. Salida: $out $err");
    return $json;
}

$show = <<<'PHP'
$settings = site_settings_load();
$context = ['siteSettings' => $settings, 'totalFotos' => 0, 'yearLabel' => '', 'heroItem' => null, 'rootDir' => dirname(__DIR__)];
ob_start();
try { $heroHtml = page_block_render('header-desktop', $context); } finally { ob_end_clean(); }
preg_match('~<h1 class="hero__title">(.*?)</h1>~s', $heroHtml, $h1);
$lines = array_map(static fn($t) => trim(strip_tags($t)), array_values(array_filter(preg_split('~</span>~', $h1[1] ?? ''), static fn($p) => trim(strip_tags($p)) !== '')));
$GLOBALS['siteInlineEditorEnabled'] = true;
$editable = site_name_part_html('Parte');
echo json_encode(['title' => site_title(), 'text' => site_text('text_52179dc42df7efe5'), 'lines' => $lines, 'footer' => site_name_part_html(site_title()),
    'h1Count' => substr_count($h1[1] ?? '', '<span class="hero__title-line'), 'editableKey' => str_contains($editable, 'data-inline-edit-key="text_52179dc42df7efe5"'),
    'editableFull' => str_contains($editable, 'data-inline-edit-value="' . htmlspecialchars(site_text('text_52179dc42df7efe5')) . '"'),
    'texts' => site_settings_load()['texts'] ?? [], 'version' => site_settings_load()['settings_version'] ?? null,
    'editableKeys' => site_editable_text_keys()], JSON_UNESCAPED_UNICODE);
PHP;

// 1. Un nombre de varias palabras: primera palabra arriba y el resto debajo, igual en pie y panel.
$r = probe(['texts' => ['text_52179dc42df7efe5' => 'Foto Mundo Azul']], $show);
check($r['title'] === 'Foto Mundo Azul' && $r['text'] === 'Foto Mundo Azul', 'El nombre de la web es el que se guardó.');
check($r['lines'] === ['Foto', 'Mundo Azul'] && $r['h1Count'] === 2, 'El título de la portada parte el nombre: ' . json_encode($r['lines']));
check($r['editableKey'] && $r['editableFull'], 'Con la edición en línea, cada trozo edita el nombre completo.');

// 2. Una sola palabra: una sola línea en la portada.
$r = probe(['texts' => ['text_52179dc42df7efe5' => 'Neurowalls']], $show);
check($r['lines'] === ['Neurowalls'] && $r['h1Count'] === 1, 'Un nombre de una palabra ocupa solo una línea.');

// 3. Sin nombre propio: el del núcleo, en los mismos sitios.
$r = probe([], $show);
check($r['title'] === 'Kookye Cat Gallery' && $r['lines'] === ['Kookye', 'Cat Gallery'], 'Sin nombre propio se usa el predeterminado.');

// 4. El nombre ya no depende de los antiguos textos sueltos del título ni del crédito del pie.
$r = probe(['texts' => ['text_52179dc42df7efe5' => 'Nombre nuevo', 'text_cbbba360eb60dd04' => 'Viejo', 'text_2c1ac3057629f681' => 'título', 'text_318bb1fc64a6e036' => 'Crédito viejo']], $show);
check($r['title'] === 'Nombre nuevo' && $r['lines'] === ['Nombre', 'nuevo'], 'Los textos antiguos no cambian el nombre.');
check(!in_array('text_318bb1fc64a6e036', $r['editableKeys'], true) && $r['editableKeys'][0] === 'text_52179dc42df7efe5', 'El nombre es editable y el crédito del pie ya no es un texto aparte.');

// 5. Migración: webs con el título en dos líneas o con el «Título de la web» antiguo conservan su nombre.
$r = probe(['texts' => ['text_cbbba360eb60dd04' => 'Mi', 'text_2c1ac3057629f681' => 'galería azul']], $show);
check($r['title'] === 'Mi galería azul' && $r['texts']['text_52179dc42df7efe5'] === 'Mi galería azul' && !isset($r['texts']['text_cbbba360eb60dd04']) && !isset($r['texts']['text_2c1ac3057629f681']),
    'Un título en dos líneas pasa a ser el nombre: ' . json_encode($r['texts'], JSON_UNESCAPED_UNICODE));
check($r['lines'] === ['Mi', 'galería azul'] && $r['version'] >= 4, 'La portada sigue viéndose igual tras migrar.');
$r = probe(['texts' => ['text_cbbba360eb60dd04' => 'Solo'], 'site_title' => 'Ignorado si hay título'], $show);
check($r['title'] === 'Solo Cat Gallery' || str_starts_with($r['title'], 'Solo '), 'Si solo se cambió una línea, la otra es la de siempre.');
$r = probe(['site_title' => 'Título de la web antiguo'], $show);
check($r['title'] === 'Título de la web antiguo' && $r['texts']['text_52179dc42df7efe5'] === 'Título de la web antiguo', 'El «Título de la web» antiguo pasa a ser el nombre.');
$r = probe(['texts' => ['text_52179dc42df7efe5' => 'Ya tenía nombre', 'text_cbbba360eb60dd04' => 'Otro']], $show);
check($r['title'] === 'Ya tenía nombre', 'Un nombre ya guardado no se pisa al migrar.');

// 6. Migración del título de «El proyecto»: las dos líneas antiguas pasan a ser un solo título.
$project = <<<'PHP'
echo json_encode(['texts' => site_settings_load()['texts'] ?? [], 'title' => site_text('text_6636c29ab23b6ec8')], JSON_UNESCAPED_UNICODE);
PHP;
$r = probe(['texts' => ['text_6636c29ab23b6ec8' => 'Mirar León', 'text_3d9d76375e115725' => 'despacio']], $project);
check($r['title'] === 'Mirar León despacio' && !isset($r['texts']['text_3d9d76375e115725']), 'Las dos líneas del título se unen: ' . json_encode($r['texts'], JSON_UNESCAPED_UNICODE));
$r = probe(['texts' => ['text_3d9d76375e115725' => 'lento']], $project);
check($r['title'] === 'Mirar el mundo despacio lento', 'Si solo se cambió la segunda línea, la primera es la de siempre.');
$r = probe([], $project);
check($r['title'] === 'Mirar el mundo despacio', 'Sin personalizar, el título es el predeterminado.');

foreach (glob($tmp . '/config-*/*') ?: [] as $file) @unlink($file);
foreach (glob($tmp . '/config-*') ?: [] as $dir) @rmdir($dir);
@rmdir($tmp);
echo "Site name tests passed.\n";
