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
check(isset(site_social_networks()['instagram'], site_social_networks()['threads']), 'Instagram and Threads must be available networks.');
check(!in_array('social_threads_user', site_editable_text_keys(), true) && !in_array('text_3b20084d3d94b4ee', site_editable_text_keys(), true), 'Network handles must not be editable text fields.');
$inlineKeys = site_inline_editable_text_keys();
check(in_array('text_cbbba360eb60dd04', $inlineKeys, true), 'Visible template copy must be available to inline editing.');
check(in_array('text_bdd8f61b94c52379', $inlineKeys, true) && in_array('text_49a006d539216e16', $inlineKeys, true) && in_array('text_ab3ceef210237f9d', $inlineKeys, true), 'Social section copy must be available in inline editing.');
$GLOBALS['siteInlineEditorEnabled'] = false;
check(!str_contains(site_inline_text_html('text_cbbba360eb60dd04'), 'data-inline-edit-key'), 'Inline controls must stay hidden for visitors.');
$GLOBALS['siteInlineEditorEnabled'] = true;
check(str_contains(site_inline_text_html('text_cbbba360eb60dd04'), 'data-inline-edit-key'), 'Inline controls must be available for an authenticated editor.');
check(str_contains(site_inline_text_html('text_bdd8f61b94c52379'), 'data-inline-edit-key'), 'Social copy must be editable in inline mode.');

unset($GLOBALS['siteInlineEditorEnabled']);
$inlineSaved = site_settings_validate(array_replace($defaults, ['texts' => ['text_cbbba360eb60dd04' => 'Un título editado']]));
check(($inlineSaved['texts']['text_cbbba360eb60dd04'] ?? '') === 'Un título editado', 'Inline text changes must pass through normal settings validation.');
$profileLinks = array_replace($defaults, ['social_links' => [
    ['network'=>'instagram', 'url'=>'https://instagram.com/example', 'handle'=>'@example'],
    ['network'=>'threads', 'url'=>'https://threads.net/@example', 'handle'=>'@example'],
]]);
check(count(site_settings_validate($profileLinks)['social_links']) === 2, 'Instagram and Threads must validate in the shared network list.');
$legacyProfiles = array_replace($defaults, [
    'instagram_url'=>'https://instagram.com/legacy',
    'threads_url'=>'https://threads.net/@legacy',
    'texts'=>['text_3b20084d3d94b4ee'=>'@legacy_ig', 'social_threads_user'=>'@legacy_threads'],
]);
$migratedProfiles = site_settings_validate($legacyProfiles)['social_links'];
check(array_column($migratedProfiles, 'network') === ['instagram', 'threads'], 'Legacy Instagram and Threads profiles must migrate.');
check($migratedProfiles[0]['handle'] === '@legacy_ig' && $migratedProfiles[1]['handle'] === '@legacy_threads', 'Legacy network handles must be preserved.');
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
$gridLayouts = site_grid_setting_layouts();
check($gridLayouts === ['standard', 'grid', 'contact-sheet', 'triptych'], 'Grid aspect compatibility list changed.');
check(site_grid_setting_is_used(array_replace($defaults, ['gallery_mobile' => 'mosaic', 'gallery_desktop' => 'asymmetric'])) === false, 'Grid setting should be unused when both layouts ignore it.');
check(site_grid_setting_is_used(array_replace($defaults, ['gallery_mobile' => 'grid', 'gallery_desktop' => 'mosaic'])) === true, 'Mobile grid should keep proportions editable.');
check(site_grid_setting_is_used(array_replace($defaults, ['gallery_mobile' => 'mosaic', 'gallery_desktop' => 'contact-sheet'])) === true, 'Desktop contact sheet should keep proportions editable.');
check(site_grid_setting_is_used(array_replace($defaults, ['gallery_mobile' => 'narrative', 'gallery_desktop' => 'triptych'])) === true, 'Triptych should use proportions.');

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
$designForm = site_settings_form('t', $defaults, 'design');
check(str_contains($designForm, '<h3>Redes Sociales</h3>') && str_contains($designForm, '<option value="instagram">Instagram</option>') && str_contains($designForm, '<option value="threads">Threads</option>'), 'The page structure social section must include Instagram and Threads.');
check(!str_contains($designForm, 'name="instagram_url"') && !str_contains($designForm, 'name="threads_url"') && !str_contains($designForm, 'Nombre de usuario de Instagram') && !str_contains($designForm, 'Nombre de usuario de Threads'), 'Legacy profile fields must be removed.');
check(str_contains($designForm, '<strong>Redes Sociales</strong>'), 'Page structure must label the social block Redes Sociales.');

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
check(!str_contains(site_settings_form('t', $defaults, 'texts'), 'name="site_title"'), 'The title setting must be removed from the administration text fields.');
$GLOBALS['siteInlineEditorEnabled'] = true;
check(str_contains(site_inline_setting_html('site_title'), 'data-inline-edit-key="site_title"'), 'The site title must be available to inline editing.');
unset($GLOBALS['siteInlineEditorEnabled']);

// ── Galerías premium ──────────────────────────────────────────────────────────
check($defaults['gallery_premium'] === 'none', 'No premium gallery by default.');
check(isset(site_premium_gallery_choices()['none'], site_premium_gallery_choices()['deck'], site_premium_gallery_choices()['bubbles'], site_premium_gallery_choices()['squares'], site_premium_gallery_choices()['drum'], site_premium_gallery_choices()['cylinder'], site_premium_gallery_choices()['polaroid'], site_premium_gallery_choices()['swipe'], site_premium_gallery_choices()['coverflow']), 'Premium choices must include none, deck, bubbles, squares, drum, cylinder, polaroid and swipe.');
check(site_premium_gallery_active($defaults) === null, 'No premium gallery must be active by default.');
$coverflow = array_replace($defaults, ['gallery_premium' => 'coverflow']);
check(site_settings_validate($coverflow)['gallery_premium'] === 'coverflow', 'Cover Flow must be a valid premium gallery.');
check(site_premium_gallery_active($coverflow) === 'coverflow', 'Cover Flow must be active when selected.');
check(site_premium_ignored_settings('coverflow') === site_standard_gallery_settings(), 'Cover Flow must disable standard-only layout controls.');
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
check(substr_count($formBubbles, ' disabled') === 6 && preg_match('/name="photos_desktop" data-premium-off="[a-z,]+">/', $formBubbles) === 1, 'Bubbles: six standard fields disabled, photos per view enabled.');
// «Estilo Tambor»: misma interfaz que la baraja; ignora toda la galería estándar y carga dos hojas de estilos (deck.css + drum.css).
check(site_premium_gallery_active(array_replace($defaults, ['gallery_premium' => 'drum'])) === 'drum', 'The drum gallery must be active.');
check(site_premium_ignored_settings('drum') === site_premium_ignored_settings('deck'), 'Drum must ignore the same settings as deck.');
check(site_settings_validate(array_replace($defaults, ['gallery_premium' => 'drum']))['gallery_premium'] === 'drum', 'The drum gallery was rejected.');
check(is_array(site_premium_gallery_definitions()['drum']['css']) && count(site_premium_gallery_definitions()['drum']['css']) === 2, 'Drum loads two stylesheets.');
// «Estilo Cilindro»: misma interfaz que la baraja (carga deck.css y cylinder.css) y mismos ajustes ignorados.
check(site_premium_gallery_active(array_replace($defaults, ['gallery_premium' => 'cylinder'])) === 'cylinder', 'The cylinder gallery must be active.');
check(site_premium_ignored_settings('cylinder') === site_premium_ignored_settings('deck'), 'Cylinder must ignore the same settings as deck.');
check(site_settings_validate(array_replace($defaults, ['gallery_premium' => 'cylinder']))['gallery_premium'] === 'cylinder', 'The cylinder gallery was rejected.');
check(site_premium_gallery_definitions()['cylinder']['css'] === ['assets/premium/deck/deck.css', 'assets/premium/cylinder/cylinder.css'], 'Cylinder loads the deck interface styles plus its own.');
check(is_file(dirname(__DIR__) . '/inc/premium/cylinder.php') && is_file(dirname(__DIR__) . '/assets/premium/cylinder/cylinder.js'), 'Cylinder files must exist.');
// «Estilo Polaroids» (interfaz de la baraja + polaroid.css) y «Estilo Tinder» (swipe.php, swipe.css y swipe.js): ignoran la galería estándar como la baraja.
foreach (['polaroid', 'swipe'] as $extra) {
    check(site_premium_gallery_active(array_replace($defaults, ['gallery_premium' => $extra])) === $extra, "The $extra gallery must be active.");
    check(site_premium_ignored_settings($extra) === site_premium_ignored_settings('deck'), "$extra must ignore the same settings as deck.");
    check(site_settings_validate(array_replace($defaults, ['gallery_premium' => $extra]))['gallery_premium'] === $extra, "The $extra gallery was rejected.");
}
check(site_premium_gallery_definitions()['polaroid']['css'] === ['assets/premium/deck/deck.css', 'assets/premium/polaroid/polaroid.css'], 'Polaroid loads deck.css plus its own.');
check(site_premium_gallery_definitions()['swipe']['js'] === 'assets/premium/swipe/swipe.js' && is_file(dirname(__DIR__) . '/inc/premium/swipe.php'), 'Swipe has its own script and markup.');
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
    check(!str_contains($form, '<script>alert') && !str_contains($form, '&lt;script&gt;'), 'Legacy text fields must not reappear in the administration form.');
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
