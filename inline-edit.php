<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
session_name('kookye_gallery_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (!session_start()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Inicia sesión de nuevo.']);
    exit;
}
require __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/site-settings.php';

$fail = static function (int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
};
if (empty($_SESSION['upload_authenticated'])) $fail(401, 'Tu sesión ya no está activa. Inicia sesión de nuevo.');
$csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], $csrf)) {
    $fail(403, 'La sesión caducó. Recarga la página e inténtalo otra vez.');
}
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload) || !is_string($payload['key'] ?? null) || !is_string($payload['value'] ?? null)) {
    $fail(400, 'La solicitud no contiene un texto válido.');
}
$key = $payload['key'];
$value = $payload['value'];
if (!in_array($key, site_inline_editable_text_keys(), true)) $fail(403, 'Este texto no se puede editar desde aquí.');
if (strlen($value) > 20000) $fail(422, 'El texto supera el máximo permitido.');

session_write_close();
try {
    $settings = site_settings_load();
    $settings['texts'][$key] = $value;
    site_settings_save($settings);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Inline text save failed: ' . $error->getMessage());
    $fail(500, 'No se pudo guardar el texto. Vuelve a intentarlo.');
}
