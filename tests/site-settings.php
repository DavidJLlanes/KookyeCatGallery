<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/site-settings.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function rejects(array $input): void
{
    try { site_settings_validate($input); }
    catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException('Invalid settings accepted.');
}

$defaults = site_settings_defaults();
check(site_text_catalog()['social_threads_user']['default'] === '@kookyecatgallery', 'Threads display name must have a catalog default.');
check(site_text('social_threads_user') !== '', 'Threads profile must not render an empty label.');
$blankThreads = array_replace($defaults, ['texts' => ['social_threads_user' => '']]);
check(site_settings_validate($blankThreads)['texts'] === [], 'A blank Threads user must use the default.');
check(!array_key_exists('premium', $defaults), 'Premium designs must not be part of settings.');
check(count(site_editable_page_keys()) === 4, 'Expected four editable text pages.');
check(count(site_design_choices()['gallery_mobile']) === 10, 'Expected ten mobile gallery designs.');
check(count(site_design_choices()['gallery_desktop']) === 10, 'Expected ten desktop gallery designs.');
foreach (['gallery_mobile', 'gallery_desktop'] as $field) {
    check(site_design_choices()[$field]['grid'] === 'Cuadrícula', 'Grid must use its short label.');
    check(!isset(site_design_choices()[$field]['art-walk']), 'Retired gallery is still selectable.');
    rejects(array_replace($defaults, [$field => 'art-walk']));
}
check(site_settings_validate($defaults) === $defaults, 'Defaults should round-trip.');
$unsafePage = array_replace($defaults, ['pages' => ['project' => '<h2 onclick="alert(1)">Texto</h2><script>alert(2)</script><a href="javascript:alert(3)">enlace</a>']]);
$safePage = site_settings_validate($unsafePage)['pages']['project'];
check(str_contains($safePage, '<h2>Texto</h2>') && !str_contains($safePage, 'onclick') && !str_contains($safePage, '<script'), 'Page editor did not remove unsafe markup.');
check(!str_contains($safePage, 'javascript:') && str_contains($safePage, '>enlace</a>'), 'Page editor accepted an unsafe link.');
check(str_contains(site_page_editor_form('csrf', 'legal_notice'), 'contenteditable="true"'), 'Page editor is missing its text surface.');
foreach (site_design_choices() as $field => $choices) {
    foreach ($choices as $choice => $label) {
        $input = $defaults;
        $input[$field] = $choice;
        check(site_settings_validate($input)[$field] === $choice, 'Choice rejected.');
    }
    rejects(array_replace($defaults, [$field => '<script>']));
    rejects(array_replace($defaults, [$field => []]));
}
foreach (['columns_mobile' => 4, 'columns_desktop' => 10] as $field => $max) {
    for ($i = 1; $i <= $max; $i++) {
        check(site_settings_validate(array_replace($defaults, [$field => (string) $i]))[$field] === $i, 'Column rejected.');
    }
    foreach ([0, -1, $max + 1, '1.5', [], 'bad'] as $invalid) rejects(array_replace($defaults, [$field => $invalid]));
}

foreach (['photos_mobile', 'photos_desktop'] as $field) {
    foreach (site_photos_per_view_choices() as $n) {
        check(site_settings_validate(array_replace($defaults, [$field => (string) $n]))[$field] === $n, 'Photos per view rejected.');
    }
    foreach ([-1, 7, 101, '1.5', [], 'bad'] as $invalid) rejects(array_replace($defaults, [$field => $invalid]));
}

check(site_settings_validate($defaults)['section_order'] === ['categories', 'gallery', 'map', 'project', 'social'], 'Default section order changed.');
check(site_settings_validate(array_replace($defaults, ['section_order' => ['social', 'gallery', 'social', 'bad', 5]]))['section_order'] === ['social', 'gallery', 'categories', 'map', 'project'], 'Section order was not normalized.');
check(site_settings_validate(array_replace($defaults, ['section_order' => 'gallery']))['section_order'] === ['categories', 'gallery', 'map', 'project', 'social'], 'Invalid section order must fall back to the default.');
foreach (['show_header_mobile', 'show_header_desktop'] as $field) {
    check($defaults[$field] === true, 'Headers must be visible by default.');
    check(site_settings_validate(array_replace($defaults, [$field => '0']))[$field] === false, 'Header toggle did not turn off.');
    check(site_settings_validate(array_replace($defaults, [$field => '1']))[$field] === true, 'Header toggle did not turn on.');
}
check(str_contains(site_design_attributes(), 'data-show-header-mobile='), 'Header visibility must reach the page.');
check(str_contains(site_settings_form('t', array_replace($defaults, ['section_order' => ['map', 'gallery', 'categories', 'project', 'social']]), 'design'), 'data-section-key="map"'), 'Section order list missing from the design form.');

check($defaults['pagination_shape'] === 'circle', 'Pagination must be circular by default.');
check(site_settings_validate(array_replace($defaults, ['pagination_shape' => 'square']))['pagination_shape'] === 'square', 'Square pagination was rejected.');
rejects(array_replace($defaults, ['pagination_shape' => 'oval']));
check(str_contains(site_design_attributes(), 'data-pagination-shape="circle"'), 'Pagination shape must reach the page.');
check(str_contains(site_settings_form('t', $defaults, 'design'), 'name="pagination_shape"'), 'Pagination shape selector missing from the form.');

// ── Título de la web ──────────────────────────────────────────────────────────
check($defaults['site_title'] === '', 'The site title must default to the catalog title.');
check(site_title_default() === site_text_catalog()['text_52179dc42df7efe5']['default'], 'Default site title mismatch.');
$titled = site_settings_validate(array_replace($defaults, ['site_title' => '  Mi Galería  ']));
check($titled['site_title'] === 'Mi Galería', 'Site title must be trimmed.');
check(site_settings_validate(array_replace($defaults, ['site_title' => site_title_default()]))['site_title'] === '', 'The default title is stored as empty.');
check(site_settings_validate(array_replace($defaults, ['site_title' => '']))['site_title'] === '', 'An empty title falls back to the default.');
rejects(array_replace($defaults, ['site_title' => str_repeat('a', 81)]));
rejects(array_replace($defaults, ['site_title' => "Dos\nlíneas"]));
check(str_contains(site_settings_form('t', array_replace($defaults, ['site_title' => 'Mi Galería']), 'design'), 'value="Mi Galería"'), 'The title field must show the saved title.');
check(str_contains(site_settings_form('t', $defaults, 'design'), 'value="' . htmlspecialchars(site_title_default(), ENT_QUOTES) . '"'), 'The title field must show the default title.');

// ── Galerías premium ──────────────────────────────────────────────────────────
check($defaults['gallery_premium'] === 'none', 'No premium gallery by default.');
check(isset(site_premium_gallery_choices()['none'], site_premium_gallery_choices()['deck'], site_premium_gallery_choices()['bubbles'], site_premium_gallery_choices()['squares'], site_premium_gallery_choices()['drum']), 'Premium choices must include none, deck, bubbles, squares and drum.');
check(site_premium_gallery_active($defaults) === null, 'No premium gallery must be active by default.');
$deck = array_replace($defaults, ['gallery_premium' => 'deck']);
check(site_settings_validate($deck)['gallery_premium'] === 'deck', 'The deck gallery was rejected.');
check(site_premium_gallery_active($deck) === 'deck', 'The deck gallery must be active.');
rejects(array_replace($defaults, ['gallery_premium' => 'inexistente']));
// Con una galería premium los campos que ignora pueden faltar (el navegador no envía los desactivados).
$partial = $deck;
foreach (site_premium_ignored_settings('deck') as $ignored) unset($partial[$ignored]);
$kept = site_settings_validate($partial);
check($kept['gallery_premium'] === 'deck' && $kept['columns_desktop'] === $defaults['columns_desktop'], 'Ignored fields must be optional with a premium gallery.');
$noPremiumPartial = $defaults; unset($noPremiumPartial['columns_desktop']);
rejects($noPremiumPartial);   // Sin galería premium siguen siendo obligatorios.
check(!in_array('gallery_premium', site_premium_ignored_settings(), true), 'The premium selector itself is never ignored.');
check(site_premium_ignored_settings('deck') === ['grid', 'gallery_mobile', 'gallery_desktop', 'pagination_shape', 'columns_mobile', 'columns_desktop', 'photos_mobile', 'photos_desktop'], 'Ignored settings list changed.');
// Atributos del <body> y formulario.
$formStandard = site_settings_form('t', $defaults, 'design');
$formDeck = site_settings_form('t', $deck, 'design');
check(substr_count($formStandard, 'data-premium-off=') === 8 && substr_count($formStandard, ' disabled') === 0, 'Standard fields must be enabled without a premium gallery.');
check(substr_count($formDeck, ' disabled') === 8, 'All 8 standard gallery fields must be disabled with a premium gallery.');
// «Estilo Burbujas»: ignora la galería estándar salvo «Fotos visibles a la vez» (móvil y escritorio).
$bubbles = array_replace($defaults, ['gallery_premium' => 'bubbles']);
check(site_premium_gallery_active($bubbles) === 'bubbles', 'The bubbles gallery must be active.');
check(site_premium_ignored_settings('bubbles') === ['grid', 'gallery_mobile', 'gallery_desktop', 'pagination_shape', 'columns_mobile', 'columns_desktop'], 'Bubbles must honor the photos-per-view settings only.');
$bubblesPartial = $bubbles;
foreach (site_premium_ignored_settings('bubbles') as $ignored) unset($bubblesPartial[$ignored]);
check(site_settings_validate($bubblesPartial)['photos_desktop'] === $defaults['photos_desktop'], 'Bubbles: ignored fields must be optional.');
rejects(array_replace($bubbles, ['photos_desktop' => 7]));   // «Fotos visibles a la vez» se sigue validando.
$formBubbles = site_settings_form('t', $bubbles, 'design');
check(substr_count($formBubbles, ' disabled') === 6 && str_contains($formBubbles, 'name="photos_desktop" data-premium-off="deck,drum">'), 'Bubbles: six standard fields disabled, photos per view enabled.');
// «Estilo Tambor»: misma interfaz que la baraja; ignora toda la galería estándar y carga dos hojas de estilos (deck.css + drum.css).
check(site_premium_gallery_active(array_replace($defaults, ['gallery_premium' => 'drum'])) === 'drum', 'The drum gallery must be active.');
check(site_premium_ignored_settings('drum') === site_premium_ignored_settings('deck'), 'Drum must ignore the same settings as deck.');
check(site_settings_validate(array_replace($defaults, ['gallery_premium' => 'drum']))['gallery_premium'] === 'drum', 'The drum gallery was rejected.');
check(is_array(site_premium_gallery_definitions()['drum']['css']) && count(site_premium_gallery_definitions()['drum']['css']) === 2, 'Drum loads two stylesheets.');
// «Estilo Cuadrados» respeta lo mismo que «Estilo Burbujas».
check(site_premium_gallery_active(array_replace($defaults, ['gallery_premium' => 'squares'])) === 'squares', 'The squares gallery must be active.');
check(site_premium_ignored_settings('squares') === site_premium_ignored_settings('bubbles'), 'Squares must honor the same settings as bubbles.');
check(site_settings_validate(array_replace($defaults, ['gallery_premium' => 'squares']))['gallery_premium'] === 'squares', 'The squares gallery was rejected.');
check(str_contains($formDeck, 'name="gallery_premium"') && str_contains($formDeck, 'Estilo Baraja'), 'Premium selector missing from the form.');
check(premium_gallery_head_tags() === '' || str_contains(premium_gallery_head_tags(), 'deck.css'), 'Premium head tags.');

$key = site_editable_text_keys()[0];
$custom = array_replace($defaults, [
    'palette' => 'japanese', 'grid' => 'masonry', 'columns_mobile' => 4, 'columns_desktop' => 10,
    'header_mobile' => 'compact', 'header_desktop' => 'editorial',
    'gallery_mobile' => 'category-rails', 'gallery_desktop' => 'mosaic',
    'show_categories' => false, 'show_map' => true, 'show_project' => false, 'show_social' => true,
    'texts' => [$key => '<script>alert("test")</script> 日本語 Kookye'],
]);
check(site_settings_validate($custom)['show_categories'] === false, 'Section visibility toggle did not persist.');
check(site_settings_validate($custom)['gallery_desktop'] === 'mosaic', 'Desktop gallery template was not preserved.');
$directory = sys_get_temp_dir() . '/site-settings-test-' . bin2hex(random_bytes(8));
$path = $directory . '/settings.json';
try {
    check(site_settings_load($path) === $defaults, 'Missing file should use defaults.');
    file_put_contents($path, json_encode(array_replace($defaults, ['premium' => 'hub'])));
    check(site_settings_load($path) === $defaults, 'Legacy premium choice must be ignored.');
    site_settings_save($custom, $path);
    check(site_settings_load($path) === $custom, 'Settings should persist independently.');
    file_put_contents($path, json_encode(array_replace($custom, ['gallery_desktop' => 'immersive'])));
    check(site_settings_load($path) === array_replace($custom, ['gallery_desktop' => 'standard']), 'Retired gallery migration must preserve all other settings.');
    file_put_contents($path, json_encode(array_replace($custom, ['gallery_mobile' => 'art-walk', 'gallery_desktop' => 'art-walk'])));
    check(site_settings_load($path) === array_replace($custom, ['gallery_mobile' => 'standard', 'gallery_desktop' => 'standard']), 'Retired art walk settings must load as standard on both screens.');
    site_settings_save($custom, $path);
    $form = site_settings_form('token"<>&', $custom, 'design');
    check(!str_contains($form, 'Paseo de arte') && !str_contains($form, 'proporción seleccionada'), 'Retired gallery labels remain in the form.');
    check(!str_contains($form, '<script>alert'), 'Stored text injected HTML.');
    check(str_contains($form, '&lt;script&gt;'), 'Stored text not shown as plain text.');
    check(str_contains($form, 'token&quot;&lt;&gt;&amp;'), 'CSRF not escaped.');
    check(str_contains($form, 'name="section" value="design"'), 'Settings section not preserved.');
    check(!str_contains($form, 'setting-premium') && !str_contains($form, 'Diseños Premium'), 'Premium selector must be removed.');
    $custom['texts'][$key] = '';
    site_settings_save($custom, $path);
    check(site_settings_load($path)['texts'][$key] === '', 'Empty overrides must persist.');
    $before = file_get_contents($path);
    try { site_settings_save(array_replace($custom, ['columns_mobile' => 5]), $path); }
    catch (InvalidArgumentException $error) {}
    check(file_get_contents($path) === $before, 'Invalid save damaged previous settings.');
    $tooLong = $defaults;
    $tooLong['texts'][$key] = str_repeat('a', 20001);
    rejects($tooLong);
    $invalidText = $defaults;
    $invalidText['texts'][$key] = [];
    rejects($invalidText);
    file_put_contents($path, '{broken');
    check(site_settings_load($path) === $defaults, 'Corrupt file should use defaults.');
    site_settings_save($defaults, $path);
    check(site_settings_load($path) === $defaults, 'Restoring defaults failed.');
    check(count(glob($directory . '/.site-settings-*') ?: []) === 0, 'Temporary file leaked.');
} finally {
    if (is_file($path)) unlink($path);
    if (is_dir($directory)) rmdir($directory);
}

// Every server-side catalog reference must exist.
foreach (['index.php', 'aviso-legal.php', 'politica-cookies.php', 'politica-privacidad.php', 'inc/helpers.php'] as $file) {
    preg_match_all('/text_[a-f0-9]{16}/', (string) file_get_contents(dirname(__DIR__) . '/' . $file), $matches);
    foreach ($matches[0] as $reference) check(isset(site_text_catalog()[$reference]), 'Missing catalog text: ' . $reference);
}
check(count(site_design_choices()['palette']) === 7, 'Expected seven palettes.');
check(count(site_design_choices()['header_mobile']) === 9, 'Expected nine mobile interfaces.');
check(count(site_design_choices()['header_desktop']) === 9, 'Expected nine desktop interfaces.');
echo "Site settings tests passed.\n";
