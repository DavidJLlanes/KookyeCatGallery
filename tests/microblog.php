<?php
declare(strict_types=1);

// Pruebas del microblog: almacén, validación, hilos, niveles, pintado seguro y bloque de portada.
// Uso: php tests/microblog.php  (usa carpetas temporales; no toca los datos reales)

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'microblog-test-' . bin2hex(random_bytes(4));
mkdir($tmp . '/data', 0775, true);
mkdir($tmp . '/img', 0775, true);
putenv('MICROBLOG_DATA_DIR=' . $tmp . '/data');
putenv('MICROBLOG_IMAGES_DIR=' . $tmp . '/img');
putenv('MICROBLOG_ALLOW_LOCAL_FILES=1');

require __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/site-settings.php';
require __DIR__ . '/../inc/page-blocks.php';
require __DIR__ . '/../inc/microblog.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function fails(callable $callback, string $message): void
{
    try { $callback(); } catch (InvalidArgumentException | RuntimeException) { return; }
    throw new RuntimeException($message);
}

// Creación y escapado.
$root = microblog_create(['text' => "Hola <script>alert(1)</script> mundo\n\nVisita https://example.com/a?b=1&c=2, ¿vale?", 'level' => '1'], []);
check(microblog_valid_id($root['id']), 'Post id is not valid.');
check($root['level'] === 1 && $root['parent'] === null, 'Root level/parent were not stored.');
$html = microblog_render_thread($root, []);
check(!str_contains($html, '<script>'), 'Post text was not escaped.');
check(str_contains($html, '<a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener noreferrer nofollow">example.com/a?b=1&amp;c=2</a>,'), 'URL was not linked without its trailing comma.');
check(str_contains($html, 'mb-thread--l1') && str_contains($html, 'mb-badge--1'), 'Level 1 decoration missing.');
check(str_contains($html, 'data-mb-heart="mb-' . $root['id'] . '"'), 'Heart slug missing.');
check(preg_match('/^[a-z0-9][a-z0-9-]*$/', 'mb-' . $root['id']) === 1, 'Heart slug is not valid for hearts.php.');

// Enlaces con texto: [texto](url).
$linked = microblog_format_text('Mira [mi web](https://example.com/a?b=1&c=2) y también https://example.org/x. Fin');
check(str_contains($linked, '<a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener noreferrer nofollow">mi web</a>'), 'Markdown-style link was not rendered.');
check(str_contains($linked, '>example.org/x</a>. Fin'), 'Bare URL next to a labelled link was not auto-linked.');
check(substr_count($linked, '<a ') === 2, 'The URL of a labelled link must not be linked twice.');
check(!str_contains(microblog_format_text('[clic](javascript:alert(1))'), '<a '), 'javascript: links must not be rendered.');
check(str_contains(microblog_format_text('[<b>x</b>](https://a.com)'), '&lt;b&gt;x&lt;/b&gt;</a>'), 'Link labels must be escaped.');
check(!str_contains(microblog_format_text('[a](https://a.com"onmouseover="x)'), 'onmouseover="x'), 'Quotes in URLs must stay escaped.');

// Validación.
fails(fn() => microblog_create(['text' => ''], []), 'Empty posts must be rejected.');
fails(fn() => microblog_create(['text' => 'x', 'level' => '5'], []), 'Invalid level must be rejected.');
fails(fn() => microblog_create(['text' => 'x', 'link_url' => ['javascript:alert(1)']], []), 'Non-http links must be rejected.');
fails(fn() => microblog_create(['text' => str_repeat('a', MICROBLOG_MAX_TEXT + 1)], []), 'Overlong text must be rejected.');
fails(fn() => microblog_create(['text' => 'x', 'quote_text' => 'q', 'quote_url' => 'ftp://x'], []), 'Bad quote URL must be rejected.');
fails(fn() => microblog_create(['text' => 'x', 'link_url' => ['https://a.com', 'https://b.com', 'https://c.com', 'https://d.com', 'https://e.com']], []), 'Too many links must be rejected.');

// Citas y enlaces.
$rich = microblog_create(['text' => '', 'level' => '2', 'quote_text' => "Línea 1\nLínea 2", 'quote_source' => 'Autor & Co', 'quote_url' => 'https://example.org/x',
    'link_url' => ['https://www.example.net/page', ''], 'link_title' => ['Un enlace', '']], []);
$richHtml = microblog_render_thread($rich, []);
check(str_contains($richHtml, '<blockquote class="mb-quote">') && str_contains($richHtml, 'Autor &amp; Co'), 'Quote not rendered.');
check(str_contains($richHtml, 'mb-link') && str_contains($richHtml, 'Un enlace') && str_contains($richHtml, 'example.net'), 'Link card not rendered.');
check(str_contains($richHtml, 'mb-thread--l2') && str_contains($richHtml, 'mb-thread__shine') && str_contains($richHtml, 'mb-badge--2'), 'Level 2 decoration missing.');

// Respuestas: solo un nivel, siempre de nivel 0.
$reply = microblog_create(['text' => 'Segunda parte', 'level' => '2'], [], $root['id']);
check($reply['parent'] === $root['id'] && $reply['level'] === 0, 'Reply must keep level 0 and its parent.');
fails(fn() => microblog_create(['text' => 'x'], [], $reply['id']), 'Replies to replies must be rejected.');
fails(fn() => microblog_create(['text' => 'x'], [], 'aaaaaaaaaa'), 'Replies to missing posts must be rejected.');
$posts = microblog_load();
check(count(microblog_roots($posts)) === 2 && count(microblog_replies($posts, $root['id'])) === 1, 'Roots/replies split wrong.');
$thread = microblog_render_thread($root, microblog_replies($posts, $root['id']));
check(str_contains($thread, 'mb-entry--reply') && str_contains($thread, 'Segunda parte'), 'Reply missing in thread.');
check(microblog_permalink($reply) === '/microblog.php?id=' . $root['id'] . '#mb-' . $reply['id'], 'Reply permalink should point to its thread.');
check(microblog_embed_url($root, 'https://x.es') === 'https://x.es/microblog.php?id=' . $root['id'] . '&embed=1', 'Embed URL changed.');

// Edición.
$updated = microblog_update($root['id'], ['text' => 'Editado', 'level' => '0'], []);
check($updated['text'] === 'Editado' && $updated['level'] === 0 && $updated['updated'] >= $updated['created'], 'Update failed.');
fails(fn() => microblog_update($root['id'], ['text' => ''], []), 'Update to an empty post must be rejected.');
fails(fn() => microblog_update('bbbbbbbbbb', ['text' => 'x'], []), 'Update of a missing post must be rejected.');

// Paginación del feed: 8 por página.
for ($i = 0; $i < 9; $i++) microblog_create(['text' => 'Nota ' . $i], []);
$page = microblog_render_feed_page(microblog_load(), 0);
check($page['next'] === MICROBLOG_PAGE_SIZE && $page['total'] === 11, 'First feed page should link to the next one.');
check(substr_count($page['html'], '<article') === MICROBLOG_PAGE_SIZE, 'First page should hold 8 threads.');
$last = microblog_render_feed_page(microblog_load(), MICROBLOG_PAGE_SIZE);
check($last['next'] === null && substr_count($last['html'], '<article') === 3, 'Last feed page wrong.');
$longThread = microblog_roots(microblog_load())[10]; // el más antiguo es $root
check($longThread['id'] === $root['id'], 'Roots should be ordered newest first.');

// Bloque de portada.
$settings = site_settings_load();
$visible = page_block_render('microblog', ['siteSettings' => array_replace($settings, ['show_microblog' => true])]);
check(str_contains($visible, 'id="microblog"') && str_contains($visible, 'data-mb-loadmore'), 'Block did not render.');
$hidden = page_block_render('microblog', ['siteSettings' => array_replace($settings, ['show_microblog' => false])]);
check(!str_contains($hidden, 'id="microblog"'), 'Hidden block must not render.');
check(isset(site_section_definitions()['microblog']) && in_array('show_microblog', site_toggle_keys(), true), 'Microblog section is not registered.');

// Programación: un post con fecha futura no es público hasta esa hora.
$future = microblog_format_time('Y-m-d\TH:i', time() + 3 * 86400);
$scheduledPost = microblog_create(['text' => 'Para dentro de tres días', 'publish_at' => $future], []);
check(microblog_is_scheduled($scheduledPost) && $scheduledPost['created'] > time() + 2 * 86400, 'Scheduled post was not stored with a future date.');
check(isset(microblog_load()[$scheduledPost['id']]) && !isset(microblog_load_public()[$scheduledPost['id']]), 'Scheduled post must be hidden from the public.');
$scheduledReply = microblog_create(['text' => 'Respuesta oculta'], [], $scheduledPost['id']);
check(!isset(microblog_load_public()[$scheduledReply['id']]), 'Replies to a scheduled post must stay hidden.');
check(!str_contains(microblog_render_feed_page(microblog_load_public(), 0)['html'], 'Para dentro de tres días'), 'Scheduled post leaked into the feed.');
check(microblog_visible(microblog_load(), time() + 4 * 86400)[$scheduledPost['id']]['text'] === 'Para dentro de tres días', 'Scheduled post should appear once its time has come.');
fails(fn() => microblog_create(['text' => 'x', 'publish_at' => 'mañana'], []), 'Invalid dates must be rejected.');
fails(fn() => microblog_create(['text' => 'x', 'publish_at' => microblog_format_time('Y-m-d\TH:i', time() + 800 * 86400)], []), 'Dates beyond a year must be rejected.');
$now = microblog_create(['text' => 'Hora pasada', 'publish_at' => '2020-01-01T10:00'], []);
check(!microblog_is_scheduled($now) && $now['created'] >= time() - 5, 'A past date must publish now.');
$moved = microblog_update($scheduledPost['id'], ['text' => 'Para dentro de tres días', 'publish_at' => ''], []);
check(!microblog_is_scheduled($moved), 'Clearing the date must publish a scheduled post now.');
$kept = microblog_update($moved['id'], ['text' => 'Ya publicado', 'publish_at' => $future], []);
check($kept['created'] === $moved['created'], 'A published post must keep its date.');

// Fijado: solo uno a la vez, siempre el primero y solo en posts raíz.
$pinA = microblog_create(['text' => 'Fijado A', 'pinned' => '1'], []);
$pinB = microblog_create(['text' => 'Fijado B', 'pinned' => '1'], []);
$loaded = microblog_load();
check(!$loaded[$pinA['id']]['pinned'] && $loaded[$pinB['id']]['pinned'], 'Pinning a post must unpin the previous one.');
check(microblog_roots(microblog_load_public())[0]['id'] === $pinB['id'], 'The pinned post must come first.');
check(str_contains(microblog_render_thread($loaded[$pinB['id']], []), 'mb-pin'), 'Pinned label missing.');
$pinnedReply = microblog_create(['text' => 'Respuesta', 'pinned' => '1'], [], $pinB['id']);
check(!$pinnedReply['pinned'], 'Replies cannot be pinned.');
microblog_update($pinB['id'], ['text' => 'Fijado B'], []);
check(!microblog_load()[$pinB['id']]['pinned'], 'Saving without the pin option must unpin.');

// Sitemap: hilos públicos como páginas con su fecha; los programados no aparecen.
$entries = microblog_sitemap_entries('https://x.es');
$locs = array_column($entries, 'loc');
check(in_array('https://x.es/microblog.php?id=' . $pinA['id'], $locs, true), 'Sitemap entries missing a public thread.');
$hiddenOne = microblog_create(['text' => 'Oculto', 'publish_at' => $future], []);
check(!in_array('https://x.es/microblog.php?id=' . $hiddenOne['id'], array_column(microblog_sitemap_entries('https://x.es'), 'loc'), true), 'Scheduled post leaked into the sitemap.');
require_once __DIR__ . '/../inc/sitemap.php';
mkdir($tmp . '/site/data', 0775, true);
generate_sitemap($tmp . '/site', 'https://x.es', [], $entries);
$xml = (string) file_get_contents($tmp . '/site/sitemap.xml');
check(str_contains($xml, '<loc>https://x.es/microblog.php?id=' . $pinA['id'] . '</loc>') && str_contains($xml, '<priority>0.6</priority>'), 'Sitemap file lacks the microblog URLs.');
check(simplexml_load_string($xml) !== false, 'Sitemap is not valid XML.');
generate_sitemap($tmp . '/site', 'https://x.es', [], []);
check(!str_contains((string) file_get_contents($tmp . '/site/sitemap.xml'), 'microblog.php'), 'Sitemap must regenerate when the microblog list changes.');

// Formulario flotante de la web (solo propietario).
require_once __DIR__ . '/../inc/microblog-composer.php';
$composer = microblog_composer_html('token-de-prueba');
check(str_contains($composer, 'data-mbc-form') && str_contains($composer, 'name="csrf" value="token-de-prueba"') && str_contains($composer, 'name="ajax"'), 'Composer form is incomplete.');
foreach (['name="text"', 'name="level"', 'name="pinned"', 'name="publish_at"', 'name="quote_text"', 'name="link_url[]"', 'name="images[]"', 'data-mb-link-tool'] as $needle) {
    check(str_contains($composer, $needle), 'Composer is missing ' . $needle);
}
// Textos editables del bloque.
check(in_array('text_6d1f0b7e2a4c9853', site_inline_editable_text_keys(), true) && in_array('text_b3e82f5a7c10d946', site_inline_editable_text_keys(), true), 'Microblog titles must be editable.');

// Borrado: quita el hilo completo.
check(microblog_delete($root['id']) === 2, 'Deleting a root must delete its replies.');
check(microblog_find($root['id']) === null && microblog_find($reply['id']) === null, 'Deleted posts still exist.');

// Imágenes (solo con GD).
if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
    $source = imagecreatetruecolor(2000, 1000);
    imagefill($source, 0, 0, imagecolorallocate($source, 200, 80, 40));
    $png = $tmp . '/photo.png';
    imagepng($source, $png);
    $withImage = microblog_create(['text' => 'Con foto', 'image_alt' => ['Una foto']], [['name' => 'photo.png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => filesize($png)]]);
    $image = $withImage['images'][0];
    check($image['w'] === 1500 && $image['h'] === 750 && is_file($tmp . '/img/' . $image['file']), 'Landscape image was not scaled to 1500 px on its longest side.');
    check(str_ends_with($image['file'], '.webp') && (getimagesize($tmp . '/img/' . $image['file'])[2] ?? 0) === IMAGETYPE_WEBP, 'Stored image is not WebP.');
    // Foto vertical: el lado mayor es el alto; las pequeñas no se amplían.
    $tall = imagecreatetruecolor(1000, 2000); imagepng($tall, $tmp . '/tall.png');
    $small = imagecreatetruecolor(400, 300); imagepng($small, $tmp . '/small.png');
    $sizes = microblog_create(['text' => 'Tamaños'], [
        ['name' => 'tall.png', 'tmp_name' => $tmp . '/tall.png', 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp . '/tall.png')],
        ['name' => 'small.png', 'tmp_name' => $tmp . '/small.png', 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp . '/small.png')]]);
    check($sizes['images'][0]['w'] === 750 && $sizes['images'][0]['h'] === 1500, 'Portrait image was not scaled to 1500 px high.');
    check($sizes['images'][1]['w'] === 400 && $sizes['images'][1]['h'] === 300, 'Small images must not be enlarged.');
    check(MICROBLOG_IMAGE_QUALITY === 80 && MICROBLOG_IMAGE_MAX_SIDE === 1500, 'Image limits changed.');
    check(str_contains(microblog_render_thread($withImage, []), '/microblog/' . $image['file']), 'Image URL missing from the post.');
    fails(fn() => microblog_update($withImage['id'], ['text' => ''], [], [$image['file']]), 'Removing the only content must be rejected.');
    microblog_update($withImage['id'], ['text' => 'Sin foto'], [], [$image['file']]);
    check(!is_file($tmp . '/img/' . $image['file']), 'Removed image file still exists.');
    $notImage = $tmp . '/fake.png';
    file_put_contents($notImage, '<?php echo 1;');
    fails(fn() => microblog_create(['text' => 'x'], [['name' => 'fake.png', 'tmp_name' => $notImage, 'error' => UPLOAD_ERR_OK, 'size' => 13]]), 'Fake images must be rejected.');
    echo "Image checks ran.\n";
} else {
    echo "GD not available: image checks skipped.\n";
}

echo "Microblog tests passed.\n";
