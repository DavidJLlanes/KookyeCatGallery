<?php

declare(strict_types=1);

/**
 * Panel de administración (área privada): punto de entrada.
 *
 * Aquí solo se prepara el entorno (rutas privadas, constantes, sesión) y se cede la petición al enrutador
 * (core/Admin/Router.php), que reparte las acciones (POST) y las pantallas (GET) entre los módulos de
 * core/Admin/Modules/: acceso, subida, biblioteca, categorías, microblog y ajustes. Para añadir una sección al panel
 * basta crear un módulo ahí (ver docs/CONTRIBUTING.md).
 *
 * Utilidades: inc/admin-util.php · armazón visual (menú y barras): inc/admin-shell.php · formularios de ajustes:
 * inc/site-settings-form.php.
 */

require_once __DIR__ . '/core/bootstrap.php';

// Carpeta privada de la instalación (credenciales y ajustes): ver core/Installation.php.
$galleryPrivateDir = \Kcg\Core\Installation::privateDir();
define('UPLOAD_AUTH_CONFIG', $galleryPrivateDir . DIRECTORY_SEPARATOR . 'upload-auth.php');
define('UPLOAD_DIRECTORY', __DIR__ . DIRECTORY_SEPARATOR . 'img');
const MAX_IMAGE_BYTES = 15728640; // 15 MiB

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name(\Kcg\Core\Installation::sessionName());
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

require __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/site-settings.php';
require __DIR__ . '/inc/processor.php';
require __DIR__ . '/inc/ai-text.php';
require __DIR__ . '/inc/admin-util.php';
require __DIR__ . '/inc/admin-photos.php';
require __DIR__ . '/inc/admin-shell.php';
require __DIR__ . '/inc/microblog.php';
require __DIR__ . '/inc/admin-microblog.php';

\Kcg\Core\Admin\Router::run();
