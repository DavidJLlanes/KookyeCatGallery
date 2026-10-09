<?php
declare(strict_types=1);

/*
 * Instalador de un solo uso. Configura URL y administrador; se elimina automáticamente al guardar.
 * ADVERTENCIA: mientras exista, cualquiera puede intentar crear el primer acceso.
 */
require_once __DIR__ . '/inc/private-storage.php';

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

$privateDirectory = gallery_private_directory();
$authPath = $privateDirectory . DIRECTORY_SEPARATOR . 'upload-auth.php';
function install_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function install_page(string $message = '', bool $success = false, int $status = 200): never
{
    http_response_code($status);
    $messageHtml = $message === '' ? '' : '<p class="message ' . ($success ? 'success' : 'error') . '">' . install_escape($message) . '</p>';
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Instalar KookyeCatGallery</title><style>body{margin:0;background:#101010;color:#f4f1ed;font:16px system-ui;min-height:100vh;display:grid;place-items:center}.card{box-sizing:border-box;width:min(560px,calc(100% - 32px));padding:28px;background:#1b1b1b;border:1px solid #383838;border-radius:18px}h1{font-size:1.5rem}.warning{padding:15px;border:2px solid #ff4b4b;border-radius:10px;background:#401919;color:#fff;font-weight:700;line-height:1.5}.warning strong{display:block;margin-bottom:5px}label{display:block;margin:18px 0 7px;font-weight:600}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #555;border-radius:9px;background:#111;color:#fff;font:inherit}button{margin-top:24px;width:100%;padding:13px;border:0;border-radius:999px;background:#f4f1ed;color:#111;font:700 1rem system-ui}.message{padding:13px;border-radius:9px}.error{background:#482323;color:#ffd7d7}.success{background:#1e3d2b;color:#d6ffe3}small{color:#bbb}</style><main class="card"><div class="warning"><strong>⚠ INSTALADOR TEMPORAL: ELIMÍNALO</strong>Mientras este archivo permanezca publicado, cualquiera puede acceder a este formulario. Úsalo una sola vez y confirma que install.php ha desaparecido del servidor.</div><h1>Instalar KookyeCatGallery</h1>' . $messageHtml;
    if (!$success) {
        $csrf = (string) ($_SESSION['install_csrf'] ?? '');
        echo '<p>El instalador preparará la carpeta privada <code>config</code> junto a <code>public_html</code> cuando el hosting lo permita. No se necesita base de datos.</p><form method="post"><input type="hidden" name="csrf" value="' . install_escape($csrf) . '"><label for="url">Dirección pública (HTTPS)</label><input id="url" name="url" type="url" placeholder="https://tudominio.com" required><label for="username">Usuario administrador</label><input id="username" name="username" minlength="3" maxlength="64" autocomplete="username" required><label for="password">Contraseña (mínimo 12 caracteres)</label><input id="password" name="password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required><small>El instalador se borrará automáticamente al completar la configuración.</small><button type="submit">Configurar sitio y acceso</button></form>';
    }
    echo '</main></html>';
}

if (is_file($authPath)) install_page('Ya hay un acceso configurado. El instalador no modificará usuarios existentes. Para recuperar el acceso, sigue la guía de recuperación y elimina este archivo del servidor.', false, 409);
if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') install_page();
$csrf = $_POST['csrf'] ?? null;
if (!is_string($csrf) || !hash_equals((string) $_SESSION['install_csrf'], $csrf)) install_page('La sesión caducó. Recarga e inténtalo otra vez.', false, 403);

$url = rtrim(trim((string) ($_POST['url'] ?? '')), '/');
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https://~i', $url) || !preg_match('~^https://[a-z0-9.-]+(?::[0-9]{1,5})?$~i', $url)) install_page('Escribe una URL HTTPS válida, sin ruta ni parámetros.', false, 422);
if (!preg_match('/^[a-zA-Z0-9._-]{3,64}$/D', $username)) install_page('El usuario debe tener entre 3 y 64 caracteres: letras, números, punto, guion o guion bajo.', false, 422);
if (strlen($password) < 12 || strlen($password) > 1024) install_page('La contraseña debe tener entre 12 y 1024 caracteres.', false, 422);

$privateDirectory = gallery_private_directory(true);
if (!is_dir($privateDirectory) || !is_writable($privateDirectory)) install_page('PHP no puede crear o escribir en la carpeta privada. Crea manualmente config fuera de public_html, dale permisos de escritura al usuario PHP y configura GALLERY_PRIVATE_DIR con su ruta completa.', false, 500);
// Defensa adicional si el proveedor apunta la carpeta privada dentro del document root.
if (!is_file($privateDirectory . DIRECTORY_SEPARATOR . '.htaccess')) @file_put_contents($privateDirectory . DIRECTORY_SEPARATOR . '.htaccess', "Require all denied\\n");
if (!is_file($privateDirectory . DIRECTORY_SEPARATOR . 'index.php')) @file_put_contents($privateDirectory . DIRECTORY_SEPARATOR . 'index.php', "<?php\\nhttp_response_code(404);\\nexit;\\n");
if (!is_file($privateDirectory . DIRECTORY_SEPARATOR . 'web.config')) @file_put_contents($privateDirectory . DIRECTORY_SEPARATOR . 'web.config', '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>');
$hash = password_hash($password, PASSWORD_DEFAULT);
if (!is_string($hash) || $hash === '') install_page('No se pudo generar el hash de la contraseña.', false, 500);
$auth = "<?php\nreturn " . var_export(['username' => $username, 'password_hash' => $hash], true) . ";\n";
$urlPath = $privateDirectory . DIRECTORY_SEPARATOR . 'public-url.txt';
$tmpAuth = tempnam($privateDirectory, '.install-auth-');
$tmpUrl = tempnam($privateDirectory, '.install-url-');
if ($tmpAuth === false || $tmpUrl === false) install_page('No se pudieron preparar los archivos privados. Comprueba permisos y espacio.', false, 500);
try {
    if (file_put_contents($tmpUrl, $url . "\n", LOCK_EX) === false || !@chmod($tmpUrl, 0640) || !@rename($tmpUrl, $urlPath)
        || file_put_contents($tmpAuth, $auth, LOCK_EX) === false || !@chmod($tmpAuth, 0640) || !@rename($tmpAuth, $authPath)) {
        throw new RuntimeException('No se pudo guardar la configuración. Comprueba permisos y espacio.');
    }
} catch (RuntimeException $error) {
    @unlink($tmpAuth); @unlink($tmpUrl);
    install_page($error->getMessage(), false, 500);
}
$removed = @unlink(__FILE__);
install_page($removed
    ? 'Instalación completada. La carpeta privada está preparada y el instalador se ha eliminado. Ya puedes iniciar sesión en /admin.php.'
    : 'La configuración se guardó, pero no se pudo borrar install.php. Elimínalo ahora desde el gestor del hosting antes de continuar.', true);
