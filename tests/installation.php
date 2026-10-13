<?php
declare(strict_types=1);

// Pruebas de la capa de instalación (Fase 8): el núcleo no lleva datos de ninguna web, y la web de este repositorio
// recibe los suyos desde installation.php. Uso: php tests/installation.php
// Los casos «sin instalación» y «con otra instalación» se ejecutan en procesos aparte (el núcleo guarda lo leído).

$root = dirname(__DIR__);
require_once $root . '/inc/helpers.php';
require_once $root . '/inc/site-settings.php';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'installation-test-' . bin2hex(random_bytes(4));
mkdir($tmp . '/private', 0775, true);

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

/** Ejecuta un fragmento de PHP con el núcleo cargado y devuelve lo que imprime como JSON. */
function probe(string $code, array $env = []): array
{
    global $root;
    $script = "require_once '" . str_replace('\\', '/', $root) . "/inc/helpers.php'; require_once '" . str_replace('\\', '/', $root) . "/inc/site-settings.php'; $code";
    $base = ['PATH' => (string) getenv('PATH'), 'PHPRC' => (string) getenv('PHPRC'), 'SystemRoot' => (string) getenv('SystemRoot')];
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + $base);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($process);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
    $json = json_decode((string) end($lines), true);
    check(is_array($json), "La sonda no devolvió JSON. Salida: $out $err");
    return $json;
}

// 1. Sin installation.php el núcleo es genérico.
$generic = ['KCG_INSTALLATION_FILE' => $tmp . '/no-existe.php', 'SITE_CONFIG_DIR' => $tmp . '/private'];
$core = probe('$d = \Kcg\Core\Settings\Schema::defaults();
    echo json_encode([
        "url" => \Kcg\Core\Installation::siteUrl(),
        "private" => \Kcg\Core\Installation::privateDir(),
        "session" => \Kcg\Core\Installation::sessionName(),
        "cookie" => \Kcg\Core\Installation::heartsCookie(),
        "place" => \Kcg\Core\Installation::place(),
        "coords" => \Kcg\Core\Installation::defaultCoordinates(),
        "categories" => array_keys(\Kcg\Core\Installation::categoryKeywords()),
        "profile" => $d["profile_image"], "logo" => $d["logo_image"], "web" => $d["profile_website_url"],
        "social" => $d["social_links"], "map" => $d["show_map"], "microblog" => $d["show_microblog"], "gallery" => $d["show_gallery"] ?? null,
        "name" => site_text("text_52179dc42df7efe5"), "author" => site_text("text_30170303c506cf6c"),
        "microblogDirs" => \Kcg\Core\Installation::microblogDirs(),
    ]);', $generic);
check($core['url'] === 'https://example.com', 'Sin instalación la dirección debe ser https://example.com.');
check($core['private'] === str_replace('\\', '/', $tmp) . '/private' || $core['private'] === $tmp . '/private' || rtrim($core['private'], '/\\') === $tmp . DIRECTORY_SEPARATOR . 'private', 'SITE_CONFIG_DIR debe mandar sobre todo lo demás.');
check($core['session'] === 'kookye_gallery_admin' && $core['cookie'] === 'kookye_gallery_hearts', 'Nombres de sesión y cookie genéricos.');
check($core['place'] === [] && $core['coords'] === null, 'Sin instalación no hay lugar ni coordenadas automáticas.');
check($core['categories'] === ['Paisajes', 'Retratos', 'Naturaleza', 'Arquitectura', 'Calle', 'Viajes', 'Blanco y negro'], 'Categorías genéricas.');
check($core['profile'] === '/profile-placeholder.svg' && $core['logo'] === '/favicon.svg' && $core['web'] === 'https://example.com' && $core['social'] === [], 'Imágenes y enlaces genéricos.');
check($core['map'] === false && $core['microblog'] === false, 'Mapa y microblog desactivados por defecto en el núcleo.');
check($core['name'] === 'Kookye Cat Gallery' && $core['author'] === 'Kookye Cat Gallery', 'Textos genéricos.');
$content = $core; unset($content['private'], $content['microblogDirs']); // las rutas dependen de dónde esté la copia
check(!preg_match('/davidjimenezllanes/i', json_encode($content)), 'El núcleo no debe mencionar ninguna web concreta: ' . json_encode($content));
check(is_file($root . '/profile-placeholder.svg') && is_file($root . '/favicon.svg'), 'Faltan las imágenes genéricas del núcleo.');

// 2. Otra instalación: sus claves mandan, y las variables de entorno mandan sobre el archivo.
file_put_contents($tmp . '/otra.php', "<?php return ['site_url' => 'https://otra.example/', 'private_dir' => '/x/privada', 'session_name' => 'otra_sesion', 'hearts_cookie' => 'otra_cookie',"
    . " 'place' => ['name' => 'Sitio, País'], 'default_coordinates' => [1.5, 2.5], 'category_keywords' => ['Mar' => ['mar']],"
    . " 'setting_defaults' => ['show_map' => true, 'profile_image' => '/yo.webp'], 'text_defaults' => ['text_52179dc42df7efe5' => 'Mi web'],"
    . " 'microblog_dir' => '/x/microblog', 'hearts_file' => '/x/hearts.json', 'ai_config_file' => '/x/ai.php'];");
$other = probe('$d = \Kcg\Core\Settings\Schema::defaults();
    echo json_encode(["url" => \Kcg\Core\Installation::siteUrl(), "private" => \Kcg\Core\Installation::privateDir(), "session" => \Kcg\Core\Installation::sessionName(),
        "cookie" => \Kcg\Core\Installation::heartsCookie(), "place" => \Kcg\Core\Installation::place(), "coords" => \Kcg\Core\Installation::defaultCoordinates(),
        "categories" => array_keys(\Kcg\Core\Installation::categoryKeywords()), "map" => $d["show_map"], "profile" => $d["profile_image"], "name" => site_text("text_52179dc42df7efe5"),
        "mediaUrl" => site_media_url("profile_image"), "dirs" => \Kcg\Core\Installation::microblogDirs(), "hearts" => \Kcg\Core\Installation::heartsFile(), "ai" => \Kcg\Core\Installation::aiConfigFile()]);',
    ['KCG_INSTALLATION_FILE' => $tmp . '/otra.php', 'SITE_CONFIG_DIR' => '', 'GALLERY_PRIVATE_DIR' => '', 'GALLERY_PUBLIC_URL' => '', 'MICROBLOG_DATA_DIR' => '']);
check($other['url'] === 'https://otra.example' && $other['private'] === '/x/privada', 'Las claves de installation.php deben usarse (y la dirección sin barra final).');
check($other['session'] === 'otra_sesion' && $other['cookie'] === 'otra_cookie' && $other['place'] === ['name' => 'Sitio, País'] && $other['coords'] === [1.5, 2.5], 'Sesión, cookie, lugar y coordenadas de la instalación.');
check($other['categories'] === ['Mar'] && $other['map'] === true && $other['profile'] === '/yo.webp' && $other['name'] === 'Mi web' && $other['mediaUrl'] === '/yo.webp', 'Categorías, ajustes y textos iniciales de la instalación.');
check($other['dirs'][0] === '/x/microblog' && $other['hearts'] === '/x/hearts.json' && $other['ai'] === '/x/ai.php', 'Carpetas y archivos de la instalación.');
$envWins = probe('echo json_encode(["url" => \Kcg\Core\Installation::siteUrl(), "private" => \Kcg\Core\Installation::privateDir(), "dirs" => \Kcg\Core\Installation::microblogDirs()]);',
    ['KCG_INSTALLATION_FILE' => $tmp . '/otra.php', 'GALLERY_PUBLIC_URL' => 'https://entorno.example', 'GALLERY_PRIVATE_DIR' => '/y/privada', 'MICROBLOG_DATA_DIR' => '/y/mb']);
check($envWins['url'] === 'https://entorno.example' && $envWins['private'] === '/y/privada' && $envWins['dirs'][0] === '/y/mb', 'Las variables de entorno deben mandar sobre installation.php.');

// 3. Un installation.php roto no tumba la web.
file_put_contents($tmp . '/rota.php', '<?php return "no soy un array";');
$broken = probe('echo json_encode(["url" => \Kcg\Core\Installation::siteUrl()]);', ['KCG_INSTALLATION_FILE' => $tmp . '/rota.php', 'SITE_CONFIG_DIR' => $tmp . '/private', 'GALLERY_PUBLIC_URL' => '']);
check($broken['url'] === 'https://example.com', 'Un installation.php que no devuelve un array se ignora.');

// 4. La web de este repositorio: sus datos salen de installation.php y producen lo de siempre.
$mine = require $root . '/installation.php';
check(is_array($mine) && ($mine['site_url'] ?? '') === 'https://davidjimenezllanes.es', 'installation.php de esta web.');
$site = probe('$d = \Kcg\Core\Settings\Schema::defaults();
    echo json_encode(["name" => site_text("text_52179dc42df7efe5"), "author" => site_text("text_30170303c506cf6c"), "profile" => $d["profile_image"], "logo" => $d["logo_image"],
        "map" => $d["show_map"], "social" => count($d["social_links"]), "place" => \Kcg\Core\Installation::place()["name"] ?? null, "session" => \Kcg\Core\Installation::sessionName()]);',
    ['SITE_CONFIG_DIR' => $tmp . '/private']);
check($site['name'] === 'Fotos de León' && $site['author'] === 'David Jiménez Llanes' && $site['profile'] === '/david.webp' && $site['logo'] === '/escudoleon.webp', 'Esta web conserva sus textos e imágenes iniciales.');
check($site['map'] === true && $site['social'] === 2 && $site['place'] === 'León, España' && $site['session'] === 'djl_upload_session', 'Esta web conserva sus ajustes iniciales, lugar y sesión.');
foreach (['text_defaults', 'setting_defaults'] as $key) {
    foreach (array_keys($mine[$key]) as $name) {
        check($key === 'setting_defaults' ? \Kcg\Core\Settings\Schema::has($name) : isset((require $root . '/inc/site-texts.php')[$name]), "installation.php: «{$name}» ({$key}) no existe en el núcleo.");
    }
}

// Limpieza.
foreach (glob($tmp . '/{,private/}*', GLOB_BRACE) ?: [] as $file) is_file($file) && @unlink($file);
@rmdir($tmp . '/private');
@rmdir($tmp);
echo "Installation tests passed.\n";
