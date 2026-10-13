<?php
declare(strict_types=1);

/*
 * Instalador de un solo uso. Deja la web lista para usar: guarda la URL y el administrador, prepara la carpeta privada, los
 * ajustes iniciales, las carpetas de fotos y la base del microblog, y se elimina al terminar.
 * Si ya hay un administrador registrado no deja registrar otro. ADVERTENCIA: mientras exista y no haya administrador,
 * cualquiera puede intentar crear el primer acceso. Aunque se borre solo, comprueba que ha desaparecido: es mejor borrarlo.
 */
require_once __DIR__ . '/core/bootstrap.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name('kookye_install_session');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$installWarnings = [];
$privateDirectory = \Kcg\Core\Installation::privateDir();
$authPath = $privateDirectory . DIRECTORY_SEPARATOR . 'upload-auth.php';
function install_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/** @param list<string> $notes líneas de detalle (qué se ha preparado o qué conviene revisar) */
function install_page(string $message = '', bool $success = false, int $status = 200, array $notes = []): never
{
    http_response_code($status);
    $messageHtml = $message === '' ? '' : '<p class="message ' . ($success ? 'success' : 'error') . '">' . install_escape($message) . '</p>'
        . ($notes ? '<ul>' . implode('', array_map(static fn(string $note): string => '<li>' . install_escape($note) . '</li>', $notes)) . '</ul>' : '');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Instalar KookyeCatGallery</title><style>body{margin:0;background:#101010;color:#f4f1ed;font:16px system-ui;min-height:100vh;display:grid;place-items:center}.card{box-sizing:border-box;width:min(560px,calc(100% - 32px));padding:28px;background:#1b1b1b;border:1px solid #383838;border-radius:18px}h1{font-size:1.5rem}.warning{padding:15px;border:2px solid #ff4b4b;border-radius:10px;background:#401919;color:#fff;font-weight:700;line-height:1.5}.warning strong{display:block;margin-bottom:5px}label{display:block;margin:18px 0 7px;font-weight:600}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #555;border-radius:9px;background:#111;color:#fff;font:inherit}button{margin-top:24px;width:100%;padding:13px;border:0;border-radius:999px;background:#f4f1ed;color:#111;font:700 1rem system-ui}.message{padding:13px;border-radius:9px}.error{background:#482323;color:#ffd7d7}.success{background:#1e3d2b;color:#d6ffe3}small{color:#bbb}</style><main class="card"><div class="warning"><strong>⚠ INSTALADOR TEMPORAL: ELIMÍNALO</strong>Mientras este archivo permanezca publicado, cualquiera puede acceder a este formulario. Úsalo una sola vez y, aunque se borre solo, confirma que install.php ha desaparecido del servidor: es mejor borrarlo.</div><h1>Instalar KookyeCatGallery</h1>' . $messageHtml;
    if (!$success) {
        $csrf = (string) ($_SESSION['install_csrf'] ?? '');
        echo '<p>El instalador preparará la carpeta privada <code>config</code> junto a <code>public_html</code> cuando el hosting lo permita. Prepara también los ajustes iniciales, las carpetas de fotos y la base del microblog, y deja la web lista para usar.</p><form method="post"><input type="hidden" name="csrf" value="' . install_escape($csrf) . '"><label for="url">Dirección pública (HTTPS)</label><input id="url" name="url" type="url" placeholder="https://tudominio.com" required><label for="username">Usuario administrador</label><input id="username" name="username" minlength="3" maxlength="64" autocomplete="username" required><label for="password">Contraseña (mínimo 12 caracteres)</label><input id="password" name="password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required><small>El instalador se borrará al completar la configuración. Si no lo consigue, bórralo tú desde el gestor de archivos.</small><button type="submit">Configurar sitio y acceso</button></form>';
    }
    echo '</main></html>';
    exit;
}

// Ya hay un administrador registrado: no se puede registrar otro (ni se muestra el formulario).
if (is_file($authPath)) {
    $removed = @unlink(__FILE__);
    install_page($removed
        ? 'Esta web ya tiene un administrador registrado, así que no se puede registrar otro. El instalador se ha eliminado; usa la recuperación documentada solo si necesitas restablecer la contraseña.'
        : 'Esta web ya tiene un administrador registrado, así que no se puede registrar otro. Elimina install.php ahora desde el hosting (es mejor borrarlo). Para restablecer la contraseña, sigue la guía de recuperación.', false, 409);
}
if (PHP_VERSION_ID < 80100) install_page('Este sitio requiere PHP 8.1 o posterior. Cambia la versión PHP desde el panel del hosting.', false, 500);
if (!extension_loaded('gd') || !function_exists('imagewebp')) install_page('Falta GD con soporte para WebP. Pide al hosting que active GD con JPEG, PNG y WebP.', false, 500);
if (!extension_loaded('fileinfo') || !class_exists('finfo')) install_page('Falta la extensión PHP Fileinfo. Pide al hosting que la active.', false, 500);


if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') install_page();
$csrf = $_POST['csrf'] ?? null;
if (!is_string($csrf) || !hash_equals((string) $_SESSION['install_csrf'], $csrf)) install_page('La sesión caducó. Recarga e inténtalo otra vez.', false, 403);

$url = rtrim(trim((string) ($_POST['url'] ?? '')), '/');
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$urlLooksValid = (bool) filter_var($url, FILTER_VALIDATE_URL);
$urlParts = $urlLooksValid ? parse_url($url) : false;
if (!$urlLooksValid || !is_array($urlParts)
    || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
    || empty($urlParts['host']) || isset($urlParts['user']) || isset($urlParts['pass'])
    || isset($urlParts['query']) || isset($urlParts['fragment'])
    || !in_array($urlParts['path'] ?? '', ['', '/'], true)) {
    install_page('Escribe una URL HTTPS válida, sin ruta ni parámetros.', false, 422);
}
if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/D', $username)) install_page('El usuario debe tener entre 3 y 64 caracteres: letras, números, punto, guion o guion bajo.', false, 422);
if (strlen($password) < 12 || strlen($password) > 1024) install_page('La contraseña debe tener entre 12 y 1024 caracteres.', false, 422);

$privateDirectory = \Kcg\Core\Installation::privateDir(true);
if (!is_dir($privateDirectory) || !is_writable($privateDirectory)) install_page('PHP no puede crear o escribir en la carpeta privada. Crea manualmente config fuera de public_html, dale permisos de escritura al usuario PHP y configura GALLERY_PRIVATE_DIR con su ruta completa.', false, 500);
// Defensa adicional si el proveedor apunta la carpeta privada dentro del document root.
if (!is_file($privateDirectory . DIRECTORY_SEPARATOR . '.htaccess')) @file_put_contents($privateDirectory . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied" . PHP_EOL);
if (!is_file($privateDirectory . DIRECTORY_SEPARATOR . 'index.php')) @file_put_contents($privateDirectory . DIRECTORY_SEPARATOR . 'index.php', "<?php" . PHP_EOL . "http_response_code(404);" . PHP_EOL . "exit;" . PHP_EOL);
if (!is_file($privateDirectory . DIRECTORY_SEPARATOR . 'web.config')) @file_put_contents($privateDirectory . DIRECTORY_SEPARATOR . 'web.config', '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
foreach (['img', 'data', 'imagenes/desktop', 'imagenes/mobile', 'microblog'] as $relativeDirectory) {
    $directory = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        install_page('PHP no puede crear ' . $relativeDirectory . '/. Comprueba los permisos del hosting.', false, 500);
    }
    if (!is_writable($directory)) install_page('PHP no puede escribir en ' . $relativeDirectory . '/. Pide al hosting que asigne esa carpeta al usuario PHP.', false, 500);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
if (!is_string($hash) || $hash === '') install_page('No se pudo generar el hash de la contraseña.', false, 500);
$auth = "<?php\nreturn " . var_export(['username' => $username, 'password_hash' => $hash], true) . ";\n";
$urlPath = $privateDirectory . DIRECTORY_SEPARATOR . 'public-url.txt';
$tmpAuth = tempnam($privateDirectory, '.install-auth-');
$tmpUrl = tempnam($privateDirectory, '.install-url-');
if ($tmpAuth === false || $tmpUrl === false) install_page('No se pudieron preparar los archivos privados. Comprueba permisos y espacio.', false, 500);
try {
    // El acceso se crea con «x» (falla si ya existe): dos envíos a la vez no pueden registrar dos administradores.
    $authHandle = @fopen($authPath, 'x');
    if ($authHandle === false) {
        @unlink($tmpAuth); @unlink($tmpUrl);
        install_page('Esta web ya tiene un administrador registrado, así que no se puede registrar otro. Elimina install.php.', false, 409);
    }
    $written = fwrite($authHandle, $auth);
    fclose($authHandle);
    if ($written !== strlen($auth) || !@chmod($authPath, 0640)) {
        @unlink($authPath);
        throw new RuntimeException('No se pudo guardar el acceso. Comprueba permisos y espacio.');
    }
    if (file_put_contents($tmpUrl, $url . "\n", LOCK_EX) === false || !@chmod($tmpUrl, 0640) || !@rename($tmpUrl, $urlPath)) {
        @unlink($authPath);
        throw new RuntimeException('No se pudo guardar la dirección pública. Comprueba permisos y espacio.');
    }
} catch (RuntimeException $error) {
    @unlink($tmpAuth); @unlink($tmpUrl);
    install_page($error->getMessage(), false, 500);
}
@unlink($tmpAuth);

// Dejar la web lista: ajustes iniciales y base del microblog (así cualquier problema de permisos sale ahora y no más tarde).
$prepared = ['Acceso del administrador «' . $username . '» y dirección pública guardados en la carpeta privada.'];
try {
    putenv('GALLERY_PUBLIC_URL=' . $url);
    require_once __DIR__ . '/inc/helpers.php';
    require_once __DIR__ . '/inc/site-settings.php';
    require_once __DIR__ . '/inc/microblog.php';
    $settingsPath = \Kcg\Core\Settings\Store::path();
    if (!is_file($settingsPath)) \Kcg\Core\Settings\Store::save(\Kcg\Core\Settings\Schema::defaults());
    $prepared[] = 'Ajustes iniciales guardados (' . basename($settingsPath) . ').';
    $repository = microblog_repository();
    $prepared[] = $repository instanceof \Kcg\Modules\Microblog\SqlitePostRepository
        ? 'Base del microblog creada (' . basename($repository->databaseFile()) . ').'
        : 'Microblog preparado con archivo JSON (el servidor no tiene pdo_sqlite; la web funciona igual, pero con muchos posts irá más lenta).';
    if (!extension_loaded('pdo_sqlite')) $installWarnings[] = 'Pide al hosting la extensión pdo_sqlite para que el microblog use su base de datos.';
} catch (Throwable $error) {
    $installWarnings[] = 'El acceso se guardó, pero no se pudieron preparar los ajustes o el microblog: ' . $error->getMessage();
}
$prepared[] = 'Carpetas de fotos listas: img/, imagenes/desktop/, imagenes/mobile/ y microblog/.';
if (!is_file(__DIR__ . DIRECTORY_SEPARATOR . '.htaccess')) $installWarnings[] = 'No hay .htaccess en la raíz: si el hosting es Apache, cópialo (las fichas /foto/… lo necesitan); con Nginx, configura el fallback a index.php.';

$removed = @unlink(__FILE__);
$installWarnings[] = $removed
    ? 'El instalador se ha eliminado. Compruébalo en el gestor de archivos del hosting.'
    : 'No se pudo borrar install.php: elimínalo ahora desde el gestor del hosting antes de continuar. Es mejor borrarlo siempre.';
install_page('Instalación completada. Ya puedes iniciar sesión en ' . $url . '/admin.php.', true, 200, array_merge($prepared, $installWarnings));
