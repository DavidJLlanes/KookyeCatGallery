<?php
declare(strict_types=1);

/*
 * Instalador web de un solo uso (para hosting compartido o cualquier servidor donde PHP ya pueda escribir en las carpetas de la web).
 * Comprueba el servidor y te dice, con la solución, lo que falte; después guarda la URL y el administrador, prepara la carpeta
 * privada, los ajustes iniciales, las carpetas de fotos y la base del microblog, y se elimina al terminar. Toda la lógica está en
 * core/Installer.php, la misma que usa el instalador de línea de comandos (instalar.sh) de los servidores propios.
 * Si ya hay un administrador registrado no deja registrar otro. ADVERTENCIA: mientras exista y no haya administrador,
 * cualquiera puede intentar crear el primer acceso. Aunque se borre solo, comprueba que ha desaparecido: es mejor borrarlo.
 */
require_once __DIR__ . '/core/bootstrap.php';

use Kcg\Core\Installer;

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

$installer = new Installer(__DIR__);
function install_escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Lista de comprobaciones del servidor (✔ bien, ! aviso, ✖ impide instalar), cada una con su explicación.
 *
 * @param list<array{status: string, label: string, hint: string}> $checks
 */
function install_checks_html(array $checks): string
{
    $icons = ['ok' => '✔', 'warn' => '!', 'fail' => '✖'];
    $html = '<ul class="checks">';
    foreach ($checks as $check) {
        $html .= '<li class="' . install_escape($check['status']) . '"><span aria-hidden="true">' . $icons[$check['status']] . '</span><div>' . install_escape($check['label'])
            . ($check['hint'] !== '' ? '<small>' . install_escape($check['hint']) . '</small>' : '') . '</div></li>';
    }
    return $html . '</ul>';
}

/**
 * @param list<string> $notes líneas de detalle (qué se ha preparado o qué conviene revisar)
 * @param list<array{status: string, label: string, hint: string}>|null $checks comprobaciones a mostrar encima del formulario
 */
function install_page(string $message = '', bool $success = false, int $status = 200, array $notes = [], ?array $checks = null): never
{
    http_response_code($status);
    $messageHtml = $message === '' ? '' : '<p class="message ' . ($success ? 'success' : 'error') . '">' . install_escape($message) . '</p>'
        . ($notes ? '<ul>' . implode('', array_map(static fn(string $note): string => '<li>' . nl2br(install_escape($note)) . '</li>', $notes)) . '</ul>' : '');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Instalar KookyeCatGallery</title><style>body{margin:0;background:#101010;color:#f4f1ed;font:16px system-ui;min-height:100vh;display:grid;place-items:center}.card{box-sizing:border-box;width:min(560px,calc(100% - 32px));padding:28px;margin:24px 0;background:#1b1b1b;border:1px solid #383838;border-radius:18px}h1{font-size:1.5rem}.warning{padding:15px;border:2px solid #ff4b4b;border-radius:10px;background:#401919;color:#fff;font-weight:700;line-height:1.5}.warning strong{display:block;margin-bottom:5px}label{display:block;margin:18px 0 7px;font-weight:600}input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #555;border-radius:9px;background:#111;color:#fff;font:inherit}button{margin-top:24px;width:100%;padding:13px;border:0;border-radius:999px;background:#f4f1ed;color:#111;font:700 1rem system-ui}.message{padding:13px;border-radius:9px}.error{background:#482323;color:#ffd7d7}.success{background:#1e3d2b;color:#d6ffe3}small{color:#bbb}.checks{list-style:none;margin:16px 0;padding:0}.checks li{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #2c2c2c;line-height:1.4}.checks li>span{flex:none;width:1.4em;text-align:center;font-weight:700}.checks .ok>span{color:#6ee7a0}.checks .warn>span{color:#ffd166}.checks .fail>span{color:#ff7b7b}.checks small{display:block;margin-top:3px}</style><main class="card"><div class="warning"><strong>⚠ INSTALADOR TEMPORAL: ELIMÍNALO</strong>Mientras este archivo permanezca publicado, cualquiera puede acceder a este formulario. Úsalo una sola vez y, aunque se borre solo, confirma que install.php ha desaparecido del servidor: es mejor borrarlo.</div><h1>Instalar KookyeCatGallery</h1>' . $messageHtml;
    if (!$success) {
        $csrf = (string) ($_SESSION['install_csrf'] ?? '');
        if ($checks !== null) echo '<h2>Comprobación del servidor</h2>' . install_checks_html($checks);
        echo '<p>El instalador usará la carpeta privada <code>config</code> junto a <code>public_html</code> cuando el hosting lo permita. Prepara también los ajustes iniciales, las carpetas de fotos y la base del microblog, y deja la web lista para usar.</p><form method="post"><input type="hidden" name="csrf" value="' . install_escape($csrf) . '"><label for="url">Dirección pública (HTTPS)</label><input id="url" name="url" type="url" placeholder="https://tudominio.com" required><label for="username">Usuario administrador</label><input id="username" name="username" minlength="3" maxlength="64" autocomplete="username" required><label for="password">Contraseña (mínimo 12 caracteres)</label><input id="password" name="password" type="password" minlength="12" maxlength="1024" autocomplete="new-password" required><small>El instalador se borrará al completar la configuración. Si no lo consigue, bórralo tú desde el gestor de archivos.</small><button type="submit">Configurar sitio y acceso</button></form>';
    }
    echo '</main></html>';
    exit;
}

// Ya hay un administrador registrado: no se puede registrar otro (ni se muestra el formulario).
if ($installer->hasAdministrator()) {
    $removed = @unlink(__FILE__);
    install_page($removed
        ? 'Esta web ya tiene un administrador registrado, así que no se puede registrar otro. El instalador se ha eliminado; usa la recuperación documentada solo si necesitas restablecer la contraseña.'
        : 'Esta web ya tiene un administrador registrado, así que no se puede registrar otro. Elimina install.php ahora desde el hosting (es mejor borrarlo). Para restablecer la contraseña, sigue la guía de recuperación.', false, 409);
}

if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') install_page('', false, 200, [], $installer->checks());
$csrf = $_POST['csrf'] ?? null;
if (!is_string($csrf) || !hash_equals((string) $_SESSION['install_csrf'], $csrf)) install_page('La sesión caducó. Recarga e inténtalo otra vez.', false, 403);

try {
    [$prepared, $warnings] = $installer->install((string) ($_POST['url'] ?? ''), (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
} catch (InvalidArgumentException $error) {
    install_page($error->getMessage(), false, 422);
} catch (DomainException $error) {
    install_page($error->getMessage() . ' Elimina install.php.', false, 409);
} catch (RuntimeException $error) {
    install_page(strtok($error->getMessage(), "\n") ?: 'No se pudo instalar.', false, 500, array_slice(explode("\n", $error->getMessage()), 1));
}

$removed = @unlink(__FILE__);
$warnings[] = $removed
    ? 'El instalador se ha eliminado. Compruébalo en el gestor de archivos del hosting.'
    : 'No se pudo borrar install.php: elimínalo ahora desde el gestor del hosting antes de continuar. Es mejor borrarlo siempre.';
install_page('Instalación completada. Ya puedes iniciar sesión en ' . rtrim(trim((string) $_POST['url']), '/') . '/admin.php.', true, 200, array_merge($prepared, $warnings));
