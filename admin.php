<?php

declare(strict_types=1);

/**
 * Panel de administración (área privada).
 *
 * Estructura de este archivo:
 *   1. Utilidades
 *   2. Acceso privado
 *   3. Acciones (POST)
 *   4. Páginas (GET)
 *   5. Formulario de acceso
 * El armazón visual (menú y barras) está en inc/admin-shell.php y los formularios de ajustes en
 * inc/site-settings-form.php.
 */

require_once __DIR__ . '/inc/private-storage.php';
define('UPLOAD_AUTH_CONFIG', rtrim(gallery_private_directory(), '/\\') . DIRECTORY_SEPARATOR . 'upload-auth.php');
define('UPLOAD_DIRECTORY', __DIR__ . '/img');
const MAX_IMAGE_BYTES = 15728640; // 15 MiB

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
session_start();

require __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/site-settings.php';
require __DIR__ . '/inc/processor.php';
require __DIR__ . '/inc/ai-text.php';
require __DIR__ . '/inc/admin-photos.php';
require __DIR__ . '/inc/admin-shell.php';

// =============================================================================
// 1. UTILIDADES (JSON, EXIF, configuración de acceso, CSRF)
// =============================================================================

function uploadJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function gpsRationalToFloat(mixed $value): float
{
    if (is_int($value) || is_float($value)) return (float) $value;
    if (!is_string($value)) return 0.0;
    if (str_contains($value, '/')) {
        [$numerator, $denominator] = array_pad(explode('/', $value, 2), 2, '1');
        $denominator = (float) $denominator;
        return $denominator == 0.0 ? 0.0 : (float) $numerator / $denominator;
    }
    return is_numeric($value) ? (float) $value : 0.0;
}

function exifGpsCoordinates(string $path): ?array
{
    if (!function_exists('exif_read_data')) return null;
    $exif = @exif_read_data($path, null, true);
    $gps = is_array($exif) ? ($exif['GPS'] ?? $exif) : [];
    if (empty($gps['GPSLatitude']) || empty($gps['GPSLongitude'])) return null;

    $toDegrees = static function (array $parts): float {
        if (count($parts) < 3) return 0.0;
        $degrees = gpsRationalToFloat($parts[0]);
        $minutes = gpsRationalToFloat($parts[1]);
        $seconds = gpsRationalToFloat($parts[2]);
        return $degrees + ($minutes / 60) + ($seconds / 3600);
    };

    $lat = $toDegrees((array) $gps['GPSLatitude']);
    $lng = $toDegrees((array) $gps['GPSLongitude']);
    if (strtoupper((string) ($gps['GPSLatitudeRef'] ?? 'N')) === 'S') $lat *= -1;
    if (strtoupper((string) ($gps['GPSLongitudeRef'] ?? 'E')) === 'W') $lng *= -1;
    if (abs($lat) > 90 || abs($lng) > 180) return null;
    return ['latitude' => round($lat, 6), 'longitude' => round($lng, 6)];
}

function readUploadConfig(): array
{
    if (!is_file(UPLOAD_AUTH_CONFIG) || !is_readable(UPLOAD_AUTH_CONFIG)) return [];
    $config = require UPLOAD_AUTH_CONFIG;
    return is_array($config) ? $config : [];
}

function uploadIniErrorMessage(int $error): string
{
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return 'El servidor PHP ha rechazado la imagen por tamaño. Configura upload_max_filesize=16M y post_max_size=20M.';
    }
    if ($error === UPLOAD_ERR_NO_TMP_DIR) return 'PHP no tiene un directorio temporal disponible para recibir imágenes.';
    if ($error === UPLOAD_ERR_CANT_WRITE) return 'PHP no pudo escribir el archivo temporal. Revisa espacio y permisos del servidor.';
    if ($error === UPLOAD_ERR_PARTIAL) return 'La subida llegó incompleta. Comprueba la conexión e inténtalo otra vez.';
    return 'No se pudo recibir la imagen. Vuelve a seleccionarla e inténtalo otra vez.';
}

function verifyCsrf(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals((string) $_SESSION['csrf'], $_POST['csrf']);
}

function safeUploadStem(string $title): string
{
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    $stem = strtolower((string) ($transliterated !== false ? $transliterated : $title));
    $stem = trim(preg_replace('/[^a-z0-9]+/', '-', $stem) ?? '', '-');
    if ($stem === '') $stem = 'fotografia';
    return substr($stem, 0, 70);
}

// =============================================================================
// 2. ACCESO PRIVADO
// Sin credenciales configuradas en el servidor, el panel no funciona (503).
// =============================================================================

$auth = readUploadConfig();
if (empty($auth['username']) || empty($auth['password_hash'])) {
    uploadPage('Preparar acceso', '<p class="upload-message upload-message--error">El acceso privado aún no está configurado en el servidor. Configura el archivo externo de autenticación y vuelve a intentarlo.</p>', 503);
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['source'])) {
    if (empty($_SESSION['upload_authenticated'])) uploadJson(401, ['ok' => false, 'error' => 'Inicia sesión para ver la fotografía.']);
    managedPhotoSource((string) $_GET['source'], (string) ($_GET['variant'] ?? 'published'));
}

// =============================================================================
// 3. ACCIONES (POST)
// Todas exigen token CSRF. Salvo `login`, también exigen sesión iniciada. Cada una termina con
// una redirección, una página de error o una respuesta JSON.
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES)) {
        uploadJson(413, ['ok' => false, 'error' => 'El servidor PHP rechazó la subida completa. Configura post_max_size=20M y el límite de Nginx en 20 MB.']);
    }
    if (!verifyCsrf()) uploadJson(403, ['ok' => false, 'error' => 'La sesión ha caducado. Recarga la página y vuelve a intentarlo.']);

    $action = (string) ($_POST['action'] ?? '');
    // Inicio de sesión, con límite de 5 intentos cada 15 minutos.
    if ($action === 'login') {
        $now = time();
        $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'since' => $now];
        if ($now - (int) $attempts['since'] > 900) $attempts = ['count' => 0, 'since' => $now];
        if ((int) $attempts['count'] >= 5) {
            uploadPage('Acceso temporalmente bloqueado', '<p class="upload-message upload-message--error">Demasiados intentos. Espera 15 minutos antes de volver a probar.</p>', 429);
        }
        $username = (string) ($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        if (hash_equals((string) $auth['username'], $username) && password_verify($password, (string) $auth['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['upload_authenticated'] = true;
            $_SESSION['login_attempts'] = ['count' => 0, 'since' => $now];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: /admin.php', true, 303);
            exit;
        }
        $attempts['count'] = (int) $attempts['count'] + 1;
        $_SESSION['login_attempts'] = $attempts;
        uploadPage('Acceso privado', '<p class="upload-message upload-message--error">Usuario o contraseña incorrectos.</p>' . loginForm((string) $_SESSION['csrf']), 401);
    }

    if (empty($_SESSION['upload_authenticated'])) uploadJson(401, ['ok' => false, 'error' => 'Inicia sesión para continuar.']);

    // Ajustes → Diseño: guarda el contenido de una página de texto.
    if ($action === 'save_site_page') {
        try {
            $page = (string) ($_POST['page'] ?? '');
            if (!in_array($page, site_editable_page_keys(), true)) throw new InvalidArgumentException('Selecciona una página válida.');
            $settings = site_settings_load();
            $pages = $settings['pages'] ?? [];
            $pages[$page] = site_sanitize_page_html((string) ($_POST['content'] ?? ''));
            site_settings_save(array_replace($settings, ['pages' => $pages]));
            header('Location: /admin.php?settings=1&section=page&doc=' . rawurlencode($page) . '&saved=1', true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            $page = (string) ($_POST['page'] ?? '');
            if (!in_array($page, site_editable_page_keys(), true)) $page = 'legal_notice';
            uploadPage(site_page_labels()[$page], adminNavigation((string) $_SESSION['csrf'])
                . '<p class="upload-message upload-message--error" role="alert">' . uploadEscape($error->getMessage()) . '</p>'
                . site_page_editor_form((string) $_SESSION['csrf'], $page), 422, true);
        }
    }

    // Categorías: renombra, mueve o elimina una categoría con todas sus fotos.
    if ($action === 'manage_category') {
        try {
            $photos = managedPhotos();
            $categories = [];
            foreach ($photos as $photo) {
                $category = trim((string) $photo['category']);
                $categories[] = $category !== '' ? $category : 'Sin categoría';
            }
            $categories = array_values(array_unique($categories));
            $changed = managedApplyCategoryAction(
                (string) ($_POST['source'] ?? ''), (string) ($_POST['operation'] ?? ''),
                (string) ($_POST['target'] ?? ''), $categories, (string) ($_POST['confirm'] ?? '')
            );
            $message = ($_POST['operation'] ?? '') === 'delete_photos'
                ? $changed . ' fotografías eliminadas.'
                : $changed . ' fotografías movidas de categoría.';
            header('Location: /admin.php?categories=1&status=' . rawurlencode($message), true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            uploadPage('Gestionar categorías', adminNavigation((string) $_SESSION['csrf'])
                . '<p class="upload-message upload-message--error" role="alert">' . uploadEscape($error->getMessage()) . '</p>'
                . '<a class="upload-logout" href="/admin.php?categories=1">Volver a categorías</a>', 422, true);
        }
    }

    // Ajustes → Perfil o Diseño: guarda ajustes, orden de bloques y subida de foto de perfil y logo.
    if ($action === 'save_site_settings') {
        try {
            $settingsInput = $_POST;
            $currentSettings = site_settings_load();
            // Los textos generales se editan en la propia web; conserva su contenido al guardar el panel.
            foreach (['site_title', 'profile_website_url'] as $preservedKey) {
                if (!array_key_exists($preservedKey, $settingsInput)) $settingsInput[$preservedKey] = $currentSettings[$preservedKey] ?? '';
            }
            if (is_array($settingsInput['texts'] ?? null)) {
                $settingsInput['texts'] = array_replace($currentSettings['texts'] ?? [], $settingsInput['texts']);
            } elseif (!array_key_exists('texts', $settingsInput)) {
                $settingsInput['texts'] = $currentSettings['texts'] ?? [];
            }
            // Si los layouts elegidos no usan el tipo de cuadrícula, el control del panel llega desactivado y no se envía.
            // Conservamos su valor para cuando se seleccione más adelante un diseño compatible.
            if (!isset($settingsInput['grid'])) $settingsInput['grid'] = $currentSettings['grid'] ?? 'adaptive';
            // Con una galería premium activa, sus campos ignorados llegan desactivados (el navegador no los envía):
            // se conservan los valores guardados para cuando se vuelva a la galería estándar.
            if (($settingsInput['gallery_premium'] ?? 'none') !== 'none') {
                foreach (site_premium_ignored_settings((string) $settingsInput['gallery_premium']) as $ignoredKey) {
                    if (!isset($settingsInput[$ignoredKey])) $settingsInput[$ignoredKey] = $currentSettings[$ignoredKey];
                }
            }
            foreach (['profile_image_file' => 'profile_image', 'logo_image_file' => 'logo_image'] as $fileKey => $settingKey) {
                $settingsInput[$settingKey] = $currentSettings[$settingKey] ?? ($settingKey === 'logo_image' ? '/favicon.svg' : '/profile-placeholder.svg');
                $file = $_FILES[$fileKey] ?? null;
                if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($error !== UPLOAD_ERR_OK) throw new RuntimeException(uploadIniErrorMessage($error));
                if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > MAX_IMAGE_BYTES) {
                    throw new RuntimeException('La imagen debe pesar como máximo 15 MB.');
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file((string) $file['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime]) || !@getimagesize((string) $file['tmp_name'])) {
                    throw new RuntimeException('Formato de imagen no admitido. Usa JPG, PNG o WebP.');
                }
                $stem = $settingKey === 'logo_image' ? 'site-logo-custom' : 'profile-photo-custom';
                foreach (['jpg', 'png', 'webp'] as $ext) {
                    $oldPath = __DIR__ . '/' . $stem . '.' . $ext;
                    if (is_file($oldPath)) @unlink($oldPath);
                }
                $filename = $stem . '.' . $extensions[$mime];
                $destination = __DIR__ . '/' . $filename;
                if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                    throw new RuntimeException('No se pudo guardar la imagen. Revisa los permisos del servidor.');
                }
                @chmod($destination, 0644);
                $settingsInput[$settingKey] = '/' . $filename;
            }
            site_brand_assets_process($_FILES);
            site_settings_save($settingsInput);
            $section = (string) ($_POST['section'] ?? 'profile');
            if (!in_array($section, ['profile', 'design', 'texts'], true)) $section = 'profile';
            header('Location: /admin.php?settings=1&section=' . rawurlencode($section) . '&saved=1', true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            $values = site_settings_load();
            try { $values = site_settings_validate($_POST); } catch (InvalidArgumentException $ignored) {}
            $section = (string) ($_POST['section'] ?? 'profile');
            if (!in_array($section, ['profile', 'design', 'texts'], true)) $section = 'profile';
            uploadPage($section === 'profile' ? 'Perfil' : ($section === 'texts' ? 'Textos' : 'Diseño'), adminNavigation((string) $_SESSION['csrf'])
                . '<p class="upload-message upload-message--error" role="alert">' . uploadEscape($error->getMessage()) . '</p>'
                . site_settings_form((string) $_SESSION['csrf'], $values, $section), 422, true);
        }
    }

    // Cierre de sesión (descarta también la subida pendiente).
    if ($action === 'logout') {
        if (!empty($_SESSION['pending_upload']['path'])) @unlink((string) $_SESSION['pending_upload']['path']);
        if (!empty($_SESSION['pending_upload']['edit_path'])) @unlink((string) $_SESSION['pending_upload']['edit_path']);
        $_SESSION = [];
        session_destroy();
        header('Location: /admin.php', true, 303);
        exit;
    }

    // Subida: descarta la foto pendiente de publicar.
    if ($action === 'cancel_edit') {
        if (!empty($_SESSION['pending_upload']['path'])) @unlink((string) $_SESSION['pending_upload']['path']);
        if (!empty($_SESSION['pending_upload']['edit_path'])) @unlink((string) $_SESSION['pending_upload']['edit_path']);
        unset($_SESSION['pending_upload']);
        uploadJson(200, ['ok' => true]);
    }

    // Biblioteca: guarda los cambios de una foto ya publicada (respuesta JSON).
    if ($action === 'update_existing') {
        try {
            managedSavePhoto($_POST, $_FILES['edited_photo'] ?? null);
            uploadJson(200, ['ok' => true, 'message' => 'Fotografía actualizada correctamente.']);
        } catch (RuntimeException $error) {
            uploadJson(422, ['ok' => false, 'error' => $error->getMessage()]);
        }
    }

    // Biblioteca: guarda el orden manual de las fotos (respuesta JSON).
    if ($action === 'reorder_photos') {
        try {
            $order = json_decode((string) ($_POST['order'] ?? '[]'), true);
            if (!is_array($order)) throw new RuntimeException('Orden no válido.');
            managedReorderPhotos($order);
            uploadJson(200, ['ok' => true, 'message' => 'Orden guardado.']);
        } catch (RuntimeException $error) {
            uploadJson(422, ['ok' => false, 'error' => $error->getMessage()]);
        }
    }

    // Biblioteca: elimina una foto tras escribir ELIMINAR (respuesta JSON).
    if ($action === 'delete_existing') {
        if (($_POST['confirm'] ?? '') !== 'ELIMINAR') {
            uploadJson(422, ['ok' => false, 'error' => 'Escribe ELIMINAR para confirmar.']);
        }
        try {
            managedDeletePhoto((string) ($_POST['file'] ?? ''));
            uploadJson(200, ['ok' => true, 'message' => 'Fotografía eliminada.']);
        } catch (RuntimeException $error) {
            uploadJson(422, ['ok' => false, 'error' => $error->getMessage()]);
        }
    }

    // Subida, paso 1: valida la imagen recibida y detecta si trae coordenadas GPS.
    if ($action === 'inspect') {
        $file = $_FILES['photo'] ?? null;
        if (!is_array($file)) uploadJson(400, ['ok' => false, 'error' => 'Selecciona una imagen válida.']);
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $tooLarge = in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            uploadJson($tooLarge ? 413 : 400, ['ok' => false, 'error' => uploadIniErrorMessage($uploadError)]);
        }
        if ((int) $file['size'] < 1 || (int) $file['size'] > MAX_IMAGE_BYTES) {
            uploadJson(413, ['ok' => false, 'error' => 'La imagen debe pesar como máximo 15 MB.']);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($allowed[$mime]) || !@getimagesize($file['tmp_name'])) {
            uploadJson(415, ['ok' => false, 'error' => 'Formato no admitido. Usa JPG, JPEG o PNG.']);
        }
        $gps = $mime === 'image/jpeg' ? exifGpsCoordinates($file['tmp_name']) : null;
        if (!empty($_SESSION['pending_upload']['path'])) @unlink((string) $_SESSION['pending_upload']['path']);
        if (!empty($_SESSION['pending_upload']['edit_path'])) @unlink((string) $_SESSION['pending_upload']['edit_path']);
        $tmpPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'djl-upload-' . bin2hex(random_bytes(20)) . '.tmp';
        if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
            uploadJson(500, ['ok' => false, 'error' => 'No se pudo preparar la imagen. Revisa el espacio temporal del servidor.']);
        }
        @chmod($tmpPath, 0600);
        $_SESSION['pending_upload'] = ['path' => $tmpPath, 'edit_path' => null, 'gps' => $gps, 'expires' => time() + 1800, 'extension' => $allowed[$mime]];
        uploadJson(200, ['ok' => true, 'gps' => $gps]);
    }

    // Subida, paso 2: recibe la versión editada en el navegador.
    if ($action === 'save_edit') {
        $pending = $_SESSION['pending_upload'] ?? null;
        $file = $_FILES['edited_photo'] ?? null;
        if (!is_array($pending) || ($pending['expires'] ?? 0) < time()
            || !is_file((string) ($pending['path'] ?? ''))) {
            uploadJson(400, ['ok' => false, 'error' => 'La sesión de subida ha caducado. Selecciona la fotografía de nuevo.']);
        }
        if (!is_array($file)) uploadJson(400, ['ok' => false, 'error' => 'No se recibió la versión editada. Vuelve a intentarlo.']);
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $tooLarge = in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            uploadJson($tooLarge ? 413 : 400, ['ok' => false, 'error' => uploadIniErrorMessage($uploadError)]);
        }
        if ((int) $file['size'] < 1 || (int) $file['size'] > MAX_IMAGE_BYTES) {
            uploadJson(413, ['ok' => false, 'error' => 'La versión editada supera el límite de 15 MB.']);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($file['tmp_name']) !== 'image/jpeg' || !@getimagesize($file['tmp_name'])) {
            uploadJson(415, ['ok' => false, 'error' => 'La versión editada no tiene un formato JPEG válido.']);
        }
        if (!empty($pending['edit_path'])) @unlink((string) $pending['edit_path']);
        $editedPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'djl-edited-' . bin2hex(random_bytes(20)) . '.jpg';
        if (!move_uploaded_file($file['tmp_name'], $editedPath)) {
            uploadJson(500, ['ok' => false, 'error' => 'No se pudo preparar la versión editada.']);
        }
        @chmod($editedPath, 0600);
        $_SESSION['pending_upload']['edit_path'] = $editedPath;
        $_SESSION['pending_upload']['expires'] = time() + 1800;
        uploadJson(200, ['ok' => true]);
    }

    // Subida, paso 3: publica la foto o la guarda como borrador.
    if ($action === 'complete') {
        $pending = $_SESSION['pending_upload'] ?? null;
        if (!is_array($pending) || ($pending['expires'] ?? 0) < time()
            || !is_file((string) ($pending['path'] ?? ''))
            || !is_file((string) ($pending['edit_path'] ?? ''))) {
            @unlink((string) ($pending['path'] ?? ''));
            if (!empty($pending['edit_path'])) @unlink((string) $pending['edit_path']);
            unset($_SESSION['pending_upload']);
            uploadJson(410, ['ok' => false, 'error' => 'La preparación de la imagen ha caducado. Selecciónala de nuevo.']);
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        if ($title === '' || site_utf8_length($title) > 120 || $category === '' || site_utf8_length($category) > 64
            || preg_match('/[\r\n\x00-\x1F]/u', $title . $category)
            || site_utf8_length($description) > 5000) {
            uploadJson(422, ['ok' => false, 'error' => 'Revisa el nombre, la categoría y la descripción (máximo 5000 caracteres).']);
        }

        $latRaw = trim((string) ($_POST['latitude'] ?? ''));
        $lngRaw = trim((string) ($_POST['longitude'] ?? ''));
        $gps = null;
        if ($latRaw !== '' || $lngRaw !== '') {
            if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
                uploadJson(422, ['ok' => false, 'error' => 'Completa ambas coordenadas o deja los dos campos vacíos.']);
            }
            $lat = (float) $latRaw;
            $lng = (float) $lngRaw;
            if (abs($lat) > 90 || abs($lng) > 180) uploadJson(422, ['ok' => false, 'error' => 'Las coordenadas están fuera de rango.']);
            $gps = ['latitude' => round($lat, 6), 'longitude' => round($lng, 6)];
        }

        if (!is_dir(UPLOAD_DIRECTORY) || !is_writable(UPLOAD_DIRECTORY)) {
            uploadJson(500, ['ok' => false, 'error' => 'La carpeta externa de originales no existe o PHP no puede escribir en ella.']);
        }
        $stem = safeUploadStem($title);
        do {
            $basename = $stem . '-' . bin2hex(random_bytes(3)) . '.' . $pending['extension'];
            $target = UPLOAD_DIRECTORY . '/' . $basename;
            $sidecar = UPLOAD_DIRECTORY . '/' . $stem . '-' . substr($basename, -10, 6) . '.txt';
        } while (file_exists($target) || file_exists($sidecar) || file_exists(UPLOAD_DIRECTORY . '/' . pathinfo($basename, PATHINFO_FILENAME) . '.edit.jpg'));
        $editedTarget = UPLOAD_DIRECTORY . '/' . pathinfo($basename, PATHINFO_FILENAME) . '.edit.jpg';

        if (!rename((string) $pending['path'], $target)) {
            uploadJson(500, ['ok' => false, 'error' => 'No se pudo guardar el original en la carpeta de fotos.']);
        }
        if (!rename((string) $pending['edit_path'], $editedTarget)) {
            @unlink($target);
            uploadJson(500, ['ok' => false, 'error' => 'No se pudo guardar la versión editada.']);
        }
        $sidecarText = $title;
        if ($description !== '') $sidecarText .= "\n---\n" . $description;
        $sidecarText .= "\n\n# Categoría: " . $category;
        if ($gps !== null) {
            $sidecarText .= "\n# Coordenadas: " . $gps['latitude'] . ',' . $gps['longitude'];
        }
        if ($description === '') $sidecarText .= "\n# auto-description-pending";
        if (($_POST['featured'] ?? '') === '1') $sidecarText .= "\n# Destacada: 1";
        if (($_POST['draft'] ?? '') === '1') $sidecarText .= "\n# Borrador: 1";
        $sidecarText .= "\n";
        if (@file_put_contents($sidecar, $sidecarText, LOCK_EX) === false) {
            @unlink($target);
            @unlink($editedTarget);
            uploadJson(500, ['ok' => false, 'error' => 'No se pudieron guardar los metadatos de la foto.']);
        }
        @chmod($target, 0644);
        @chmod($editedTarget, 0644);
        @chmod($sidecar, 0644);
        unset($_SESSION['pending_upload']);

        if ($description === '') {
            $aiConfig = ai_text_config(
                [],
                site_text('text_52179dc42df7efe5'),
                rtrim(getenv('GALLERY_PUBLIC_URL') ?: '', '/')
            );
            if ($aiConfig['ai_enabled']) {
                $generator = new AiTextGenerator(__DIR__, $aiConfig);
                $generator->processMissing([[
                    'original' => $basename,
                    'sidecar' => $sidecar,
                    'filename_clean' => $title,
                ]], 1);
            }
        }

        $publishedMeta = read_sidecar($sidecar);
        $publishedSlug = (string) ($publishedMeta['slug'] ?? '');
        if ($publishedSlug === '') {
            $publishedSlug = generate_slug($title);
            if ($publishedSlug !== '') {
                $candidate = $publishedSlug; $suffix = 2;
                foreach (managedPhotos() as $existingPhoto) {
                    if ($existingPhoto['filename'] === $basename) continue;
                    while (($existingPhoto['slug'] ?? '') === $candidate) $candidate = $publishedSlug . '-' . $suffix++;
                }
                $publishedSlug = $candidate;
                @file_put_contents($sidecar, rtrim((string) file_get_contents($sidecar)) . "\n# Slug: " . $publishedSlug . "\n", LOCK_EX);
            }
        }
        header('Location: /admin.php?library=1&published=' . rawurlencode($basename), true, 303);
        exit;
    }

    uploadJson(400, ['ok' => false, 'error' => 'Acción no válida.']);
}

// =============================================================================
// 4. PÁGINAS (GET)
// Con sesión iniciada: cada `if (isset($_GET[...]))` dibuja una pantalla y termina la petición.
// Si no hay parámetro, se llega a la pantalla de subir foto, al final de este bloque.
// =============================================================================

if (!empty($_SESSION['upload_authenticated'])) {
    if (!empty($_SESSION['pending_upload']['expires']) && $_SESSION['pending_upload']['expires'] < time()) {
        @unlink((string) ($_SESSION['pending_upload']['path'] ?? ''));
        if (!empty($_SESSION['pending_upload']['edit_path'])) @unlink((string) $_SESSION['pending_upload']['edit_path']);
        unset($_SESSION['pending_upload']);
    }
    // Perfil, Diseño y editor de páginas de texto (?settings=1&section=profile|design|page).
    if (isset($_GET['settings'])) {
        $status = isset($_GET['saved']) ? '<p class="upload-message" role="status">Configuración guardada.</p>' : '';
        $section = (string) ($_GET['section'] ?? 'profile');
        if (!in_array($section, ['profile', 'design', 'texts', 'page'], true)) $section = 'profile';
        if ($section === 'page') {
            $page = (string) ($_GET['doc'] ?? '');
            if (!in_array($page, site_editable_page_keys(), true)) $page = 'legal_notice';
            $status = isset($_GET['saved']) ? '<p class="upload-message" role="status">Página guardada.</p>' : '';
            uploadPage(site_page_labels()[$page], adminNavigation((string) $_SESSION['csrf']) . $status
                . '<nav class="admin-subnav admin-subnav--back" aria-label="Secciones de configuración"><a href="/admin.php?settings=1&amp;section=texts">← Textos</a></nav>'
                . site_page_editor_form((string) $_SESSION['csrf'], $page), 200, true);
        }
        uploadPage($section === 'profile' ? 'Perfil' : ($section === 'texts' ? 'Textos' : 'Diseño'), adminNavigation((string) $_SESSION['csrf']) . $status
            . site_settings_form((string) $_SESSION['csrf'], null, $section), 200, true);
    }
    // Gestión de categorías.
    if (isset($_GET['categories'])) {
        $photos = managedPhotos();
        $groups = [];
        foreach ($photos as $photo) {
            $name = trim((string) $photo['category']);
            if ($name === '') $name = 'Sin categoría';
            $groups[$name][] = $photo;
        }
        uksort($groups, 'strnatcasecmp');
        $status = isset($_GET['status']) ? '<p class="upload-message" role="status">' . uploadEscape((string) $_GET['status']) . '</p>' : '';
        $content = adminNavigation((string) $_SESSION['csrf']) . $status
            . '<p class="upload-help">Las acciones se aplican a todas las fotos de la categoría. Para cambiar una sola, ve a <a href="/admin.php?library=1">Gestionar fotos</a> y pulsa «Editar».</p>';
        if (!$groups) $content .= '<p class="upload-message">Todavía no hay categorías.</p>';
        foreach ($groups as $name => $items) {
            $escapedName = uploadEscape($name);
            $options = '';
            foreach (array_keys($groups) as $candidate) {
                if ($candidate === $name) continue;
                $value = uploadEscape($candidate);
                $options .= '<option value="' . $value . '">' . $value . '</option>';
            }
            $content .= '<section class="admin-category-card"><div><h2>' . $escapedName . '</h2><p>' . count($items) . ' fotografías</p></div>'
                . '<form method="post" action="/admin.php" class="admin-category-form" data-category-action>'
                . '<input type="hidden" name="action" value="manage_category"><input type="hidden" name="csrf" value="' . uploadEscape((string) $_SESSION['csrf']) . '">'
                . '<input type="hidden" name="source" value="' . $escapedName . '">'
                . '<div class="upload-field"><label>Acción</label><select name="operation" data-category-operation>'
                . '<option value="rename">Cambiar nombre o combinar</option><option value="move_existing"' . ($options === '' ? ' disabled' : '') . '>Mover a una existente</option>'
                . '<option value="move_new">Mover a una nueva</option><option value="delete_photos">Eliminar categoría y todas sus fotos</option></select></div>'
                . '<div class="upload-field" data-existing-target hidden><label>Destino existente</label><select name="target" data-existing-select disabled>' . ($options ?: '<option value="">No hay otras categorías</option>') . '</select></div>'
                . '<div class="upload-field" data-new-target><label>Nuevo nombre de categoría</label><input type="text" name="target" maxlength="64" data-new-input placeholder="Escribe el nombre" required></div>'
                . '<div class="upload-field" data-delete-confirm hidden><label>Escribe ELIMINAR para confirmar el borrado de ' . count($items) . ' fotos</label><input type="text" name="confirm" maxlength="20" data-confirm-input autocomplete="off" disabled></div>'
                . '<button class="upload-submit" type="submit">Aplicar a las ' . count($items) . ' fotos</button></form></section>';
        }
        uploadPage('Gestionar categorías', $content, 200, true);
    }
    // Biblioteca: listado, búsqueda, orden y borrado de fotos.
    if (isset($_GET['library'])) {
        (new ImageProcessor(__DIR__))->processAll();
        $photos = managedPhotos();
        $cards = '';
        foreach ($photos as $photo) {
            $filename = uploadEscape($photo['filename']);
            $title = uploadEscape($photo['title']);
            $category = uploadEscape($photo['category'] !== '' ? $photo['category'] : 'Sin categoría');
            $thumbnail = uploadEscape($photo['thumbnail']);
            $editUrl = '/admin.php?edit=' . rawurlencode($photo['filename']);
            $featured = !empty($photo['featured']);
            $draft = !empty($photo['draft']);
            $slug = uploadEscape((string) ($photo['slug'] ?? ''));
            $cards .= '<article class="admin-photo-card' . ($featured ? ' is-featured' : '') . ($draft ? ' is-draft' : '') . '" data-photo-file="' . $filename . '" data-photo-mtime="' . (int) $photo['mtime'] . '">'
                . '<img src="' . $thumbnail . '" alt="" loading="lazy" decoding="async">'
                . '<div class="admin-photo-card__body"><div class="admin-photo-card__heading"><h2>' . $title . '</h2>'
                . ($featured ? '<span class="admin-photo-featured" title="Fotografía destacada">★ Destacada</span>' : '')
                . ($draft ? '<span class="admin-photo-draft">Borrador</span>' : '') . '</div><p>' . $category . '</p>'
                . ($slug !== '' ? '<p class="admin-photo-hearts" data-admin-heart="' . $slug . '">♥ <span>0</span> corazones</p>' : '')
                . '<div class="admin-photo-order-actions"><button type="button" data-order-up aria-label="Mover antes">↑</button><button type="button" data-order-down aria-label="Mover después">↓</button></div>'
                . '<div class="admin-photo-card__actions"><a class="upload-logout" href="' . uploadEscape($editUrl) . '">Editar</a>'
                . '<button class="admin-photo-delete" type="button" data-delete-file="' . $filename
                . '" data-delete-title="' . $title . '">Eliminar</button></div></div></article>';
        }
        $status = isset($_GET['status'])
            ? '<p class="upload-message" role="status">' . uploadEscape((string) $_GET['status']) . '</p>' : '';
        if (isset($_GET['published'])) {
            $published = managedPhotoData((string) $_GET['published']);
            if ($published) {
                $isDraft = !empty($published['draft']);
                $view = !$isDraft && !empty($published['slug']) ? '<a class="upload-logout" href="/foto/' . uploadEscape($published['slug']) . '">Ver publicada</a>' : '';
                $status = '<div class="upload-message" role="status">' . ($isDraft ? 'Borrador guardado correctamente.' : 'Fotografía publicada correctamente.') . ' <span class="admin-published-actions">' . $view
                    . '<a class="upload-logout" href="/admin.php?edit=' . rawurlencode($published['filename']) . '">Volver a editar</a>'
                    . '<a class="upload-logout" href="/admin.php">Subir otra</a></span></div>';
            }
        }
        $content = adminNavigation((string) $_SESSION['csrf']) . $status
            . '<div class="admin-library__intro"><p>Administra cada fotografía publicada sin modificar el original.</p>'
            . '<a class="upload-submit" href="/admin.php">+ Subir nueva</a></div>'
            . '<div class="admin-library-tools"><div class="upload-field"><label for="photoLibrarySearch">Buscar por título, categoría o archivo</label>'
            . '<input id="photoLibrarySearch" type="search" autocomplete="off" placeholder="Buscar fotografías"></div>'
            . '<div class="upload-field"><label for="photoLibrarySort">Orden</label><select id="photoLibrarySort"><option value="manual">Manual</option><option value="recent">Más recientes</option><option value="popular">Más corazones</option></select></div>'
            . '<button class="upload-submit" id="savePhotoOrder" type="button">Guardar orden manual</button></div>'
            . '<p class="admin-library__count" id="photoLibraryCount">' . count($photos) . ' fotografías</p>'
            . '<div class="admin-photo-grid" id="adminPhotoGrid">' . $cards . '</div>'
            . '<p class="admin-library__empty" id="photoLibraryEmpty"' . ($photos ? ' hidden' : '') . '>No hay fotografías para mostrar.</p>'
            . '<dialog class="admin-delete-dialog" id="adminDeleteDialog" aria-labelledby="deleteDialogTitle">'
            . '<form id="adminDeleteForm" method="post" action="/admin.php"><input type="hidden" name="action" value="delete_existing">'
            . '<input type="hidden" name="csrf" value="' . uploadEscape((string) $_SESSION['csrf']) . '">'
            . '<input type="hidden" name="file" id="deletePhotoFile">'
            . '<h2 id="deleteDialogTitle">Eliminar fotografía</h2><p id="deletePhotoTitle"></p>'
            . '<p>Se eliminarán el original, la versión editada, los datos y las miniaturas. Esta acción no se puede deshacer.</p>'
            . '<label for="deleteConfirm">Escribe ELIMINAR para confirmar</label>'
            . '<input id="deleteConfirm" name="confirm" type="text" autocomplete="off" required>'
            . '<p class="admin-delete-dialog__error" id="deleteFeedback" role="alert" hidden></p>'
            . '<div class="admin-delete-dialog__actions"><button type="button" id="deleteCancel">Cancelar</button>'
            . '<button type="submit" id="deleteSubmit">Eliminar definitivamente</button></div></form></dialog>';
        uploadPage('Gestionar fotografías', $content, 200, true);
    }

    // Subir foto nueva o editar una existente (?edit=archivo): formulario + editor de imagen.
    $editFile = (string) ($_GET['edit'] ?? '');
    $edit = $editFile !== '' ? managedPhotoData($editFile) : null;
    if ($editFile !== '' && $edit === null) {
        uploadPage('Fotografía no encontrada', adminNavigation((string) $_SESSION['csrf'])
            . '<p class="upload-message upload-message--error">No se encontró esa fotografía.</p>', 404);
    }

    $categories = [];
    $files = @scandir(UPLOAD_DIRECTORY) ?: [];
    foreach ($files as $filename) {
        if (str_ends_with(strtolower($filename), '.txt')) {
            $txt = @file_get_contents(UPLOAD_DIRECTORY . '/' . $filename);
            if (is_string($txt) && preg_match('/^# Categoría:\s*(.+?)\s*$/m', $txt, $m)) {
                $category = trim($m[1]);
                if ($category !== '') $categories[$category] = true;
            }
            continue;
        }
        if (!preg_match('/\.(?:jpe?g|png)$/i', $filename)) continue;
        $base = pathinfo($filename, PATHINFO_FILENAME);
        if (preg_match('/^.+?\(([^)]+)\)\((-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)\)$/u', $base, $m)) {
            $category = trim($m[1]);
            if ($category !== '') $categories[$category] = true;
        }
    }
    $categoryNames = array_keys($categories);
    sort($categoryNames, SORT_NATURAL | SORT_FLAG_CASE);
    $options = '';
    foreach ($categoryNames as $categoryOption) {
        $escapedCategory = uploadEscape($categoryOption);
        $selected = $edit !== null && $categoryOption === $edit['category'] ? ' selected' : '';
        $options .= '<option value="' . $escapedCategory . '"' . $selected . '>' . $escapedCategory . '</option>';
    }
    $csrf = uploadEscape((string) $_SESSION['csrf']);
    $status = isset($_GET['status']) ? '<p class="upload-message">' . uploadEscape((string) $_GET['status']) . '</p>' : '';
    $csrfJson = json_encode(
        (string) $_SESSION['csrf'],
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );
    $formTemplate = <<<'HTML'
__STATUS__
__UPLOAD_GUIDE__
<form class="upload-form" id="photoUploadForm" data-edit-file="__EDIT_FILE__" data-has-edited="__HAS_EDITED__" method="post" action="/admin.php">
    <input type="hidden" name="action" value="__ACTION__">
    <input type="hidden" name="csrf" value="__CSRF__">
    <input type="hidden" name="file" value="__EDIT_FILE__">
    <div class="upload-field" id="fileSourceField" __FILE_HIDDEN__><label for="photoFile">Fotografía</label><input id="photoFile" name="photo" type="file" accept="image/jpeg,image/png,.jpg,.jpeg,.png" __FILE_REQUIRED__><div class="upload-source-actions"><button class="upload-logout" id="cameraSourceButton" type="button">Usar cámara</button></div><small class="upload-help">Selecciona una foto y pulsa «Continuar a la edición». Las fotos grandes se optimizan en este dispositivo.</small></div>
    <button class="upload-submit upload-continue" id="startEditingButton" type="button" __START_BUTTON_HIDDEN__ disabled>Continuar a la edición</button>
    <p class="upload-gps-status" id="gpsStatus" aria-live="polite" __GPS_STATUS_HIDDEN__>Elige una imagen para detectar si incluye coordenadas GPS.</p>
    <section class="photo-editor" id="photoEditor" aria-label="Editor de fotografía" hidden>
        <header class="photo-editor__topbar">
            <button type="button" class="photo-editor__cancel" id="editorCancel" aria-label="Cancelar edición y volver al inicio" title="Cancelar y volver al inicio"><span aria-hidden="true">×</span><span class="photo-editor__cancel-label">Cancelar</span></button>
            <button type="button" class="photo-editor__top-action" id="editorChangePhoto">__CHANGE_LABEL__</button>
            <span class="photo-editor__step">1 / 2 · Ajustes</span>
            <button type="button" class="photo-editor__next" id="editorContinue" aria-label="Continuar a las opciones de publicación" title="Continuar a las opciones de publicación">Continuar</button>
        </header>
        <div class="photo-editor__stage" id="editorStage">
            <div class="photo-editor__backdrop" id="editorBackdrop" aria-hidden="true"></div>
            <canvas class="photo-editor__canvas" id="editorCanvas" role="img" aria-label="Previsualización de la fotografía editada"></canvas>
            <div class="photo-editor__crop-selection" id="cropSelection" role="group" tabindex="0" aria-label="Selección de recorte. Arrastra para moverla y usa sus esquinas para cambiar su tamaño." hidden>
                <span data-crop-handle="nw" aria-hidden="true"></span><span data-crop-handle="ne" aria-hidden="true"></span>
                <span data-crop-handle="sw" aria-hidden="true"></span><span data-crop-handle="se" aria-hidden="true"></span>
            </div>
            <button type="button" class="photo-editor__zoom-reset" id="editorZoomReset" aria-label="Restablecer zoom" hidden>Ajustar</button>
            <button type="button" class="photo-editor__compare" id="editorCompare" aria-label="Mantén pulsado para ver la foto original" aria-pressed="false" title="Mantén pulsado para ver el antes">Antes</button>
            <span class="photo-editor__zoom-hint" aria-hidden="true">Pellizca o usa la rueda para ampliar</span>
            <p class="photo-editor__progress" id="editorProgress" aria-live="polite" hidden></p>
        </div>
        <div class="photo-editor__controls">
            <div class="photo-editor__tool-panel" id="toolPanel">
                <div class="photo-editor__control-heading"><span id="toolName">Luz</span><output id="toolValue">0%</output><button type="button" class="photo-editor__subtle-button" id="toolReset">Restablecer</button></div>
                <input class="photo-editor__range" id="toolSlider" type="range" min="-100" max="100" value="0" aria-label="Porcentaje de ajuste">
            </div>
            <div class="photo-editor__crop-panel" id="cropPanel" hidden>
                <div class="photo-editor__control-heading"><span>Elige tu encuadre</span><button type="button" class="photo-editor__subtle-button" id="cropReset">Original</button></div>
                <div class="photo-editor__ratios" id="cropRatios" role="group" aria-label="Proporción de recorte">
                    <button type="button" data-ratio="free" class="is-active">Libre</button>
                    <button type="button" data-ratio="original">Original</button>
                    <button type="button" data-ratio="1">1:1</button><button type="button" data-ratio="0.8">4:5</button>
                    <button type="button" data-ratio="1.25">5:4</button><button type="button" data-ratio="1.5">3:2</button>
                    <button type="button" data-ratio="0.6666667">2:3</button><button type="button" data-ratio="1.3333333">4:3</button>
                    <button type="button" data-ratio="0.75">3:4</button><button type="button" data-ratio="1.7777778">16:9</button>
                    <button type="button" data-ratio="0.5625">9:16</button>
                </div>
                <div class="photo-editor__crop-actions"><span>Arrastra la selección o sus esquinas</span><button type="button" id="cropCancel">Cancelar</button><button type="button" id="cropApply">Aplicar recorte</button></div>
            </div>
            <div class="photo-editor__filter-panel" id="filterPanel" hidden>
                <div class="photo-editor__control-heading"><span>Filtros fotográficos</span><select id="filterCategory" aria-label="Familia de filtros"></select></div>
                <div class="photo-editor__filter-carousel"><button class="photo-editor__filter-nav" id="filterPrev" type="button" aria-label="Filtros anteriores">‹</button><div class="photo-editor__filters" id="photoEditorPresets" role="group" tabindex="0" aria-label="Filtros deslizando horizontalmente"></div><button class="photo-editor__filter-nav" id="filterNext" type="button" aria-label="Filtros siguientes">›</button></div>
                <div class="photo-editor__filter-strength"><label for="filterStrength">Intensidad <output id="filterStrengthValue">100%</output></label><input class="photo-editor__range" id="filterStrength" type="range" min="0" max="100" value="100"></div>
            </div>
            <nav class="photo-editor__tools" id="toolRail" aria-label="Herramientas de edición"></nav>
        </div>
    </section>
    <section class="photo-details" id="photoDetails" aria-label="Detalles de la fotografía" hidden>
        <header class="photo-details__topbar">
            <button type="button" class="photo-editor__top-action" id="editorBack">← Editar</button>
            <span>__STEP_LABEL__</span>
            <button type="button" class="photo-editor__top-action" id="detailsChangePhoto">__CHANGE_LABEL__</button>
            <button type="button" class="photo-editor__cancel" id="detailsCancel" aria-label="Cancelar edición y volver al inicio" title="Cancelar y volver al inicio"><span aria-hidden="true">×</span><span class="photo-editor__cancel-label">Cancelar</span></button>
        </header>
        <div class="photo-details__preview"><img id="reviewPreview" alt="Previsualización de la publicación"><span id="photoGpsSummary">Ubicación pendiente</span></div><p class="upload-gps-status photo-details__gps-status" id="gpsStatusReview" aria-live="polite"></p>
        <div class="upload-field"><label for="photoTitle">Nombre de la fotografía</label><input id="photoTitle" name="title" type="text" maxlength="120" autocomplete="off" value="__TITLE__" required></div>
        <div class="upload-field"><label for="photoCategoryChoice">Categoría</label><select id="photoCategoryChoice" required><option value="" selected disabled>Selecciona una categoría</option>__OPTIONS__<option value="__new__">+ Crear categoría nueva</option></select><input id="photoCategory" name="category" type="text" maxlength="64" autocomplete="off" placeholder="Escribe una categoría nueva" aria-label="Nueva categoría" required disabled hidden>__CATEGORY_HELP__</div>
        <div class="upload-field"><label for="photoDescription">Descripción <span class="upload-help">__DESCRIPTION_HELP__</span></label><textarea id="photoDescription" name="description" rows="4" maxlength="5000">__DESCRIPTION__</textarea></div>
        <label class="upload-featured"><input type="checkbox" name="featured" value="1" __FEATURED_CHECKED__> <span>Marcar como fotografía destacada</span></label>
        <label class="upload-featured"><input type="checkbox" name="draft" value="1" __DRAFT_CHECKED__> <span>Guardar como borrador (no aparecerá públicamente)</span></label>
        __SLUG_FIELD__
        <fieldset class="upload-gps" id="gpsFields" hidden><legend>Ubicación GPS (opcional)</legend><p class="upload-help">Puedes añadir coordenadas para mostrar la foto en el mapa. Deja ambos campos vacíos si no quieres compartir su ubicación.</p><div class="upload-form"><div class="upload-field"><label for="photoLatitude">Latitud</label><input id="photoLatitude" name="latitude" type="number" min="-90" max="90" step="any" value="__LATITUDE__"></div><div class="upload-field"><label for="photoLongitude">Longitud</label><input id="photoLongitude" name="longitude" type="number" min="-180" max="180" step="any" value="__LONGITUDE__"></div></div></fieldset>
        <div class="upload-actions"><button class="upload-submit" id="uploadSubmit" type="submit" disabled>__SUBMIT_LABEL__</button></div>
    </section>
    </form>
<p class="upload-help" id="uploadFeedback" aria-live="polite"></p>

HTML;
    $editMode = $edit !== null;
    $slugField = $editMode
        ? '<div class="upload-field"><label for="photoSlug">Enlace de la fotografía</label>'
          . '<input id="photoSlug" name="slug" type="text" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="'
          . uploadEscape($edit['slug']) . '" placeholder="Se generará a partir del título">'
          . '<small class="upload-help">Cambiar este enlace puede romper enlaces compartidos anteriormente.</small></div>'
        : '';
    $form = strtr($formTemplate, [
        '__STATUS__' => $status,
        '__UPLOAD_GUIDE__' => '',
        '__CSRF__' => $csrf,
        '__OPTIONS__' => $options,
        '__CATEGORY_HELP__' => $editMode ? '<small class="upload-help">Aquí puedes cambiar la categoría de esta foto. Para cambiar varias a la vez, usa «Gestionar categorías».</small>' : '',
        '__EDIT_FILE__' => $editMode ? uploadEscape($edit['filename']) : '',
        '__HAS_EDITED__' => $editMode && $edit['edited'] ? '1' : '0',
        '__ACTION__' => $editMode ? 'update_existing' : 'complete',
        '__FILE_HIDDEN__' => $editMode ? 'hidden' : '',
        '__FILE_REQUIRED__' => $editMode ? '' : 'required',
        '__GPS_STATUS_HIDDEN__' => $editMode ? 'hidden' : '',
        '__CHANGE_LABEL__' => $editMode ? 'Usar original' : 'Cambiar foto',
        '__START_BUTTON_HIDDEN__' => $editMode ? 'hidden' : '',
        '__STEP_LABEL__' => $editMode ? '2 / 2 · Actualizar fotografía' : '2 / 2 · Nueva publicación',
        '__TITLE__' => $editMode ? uploadEscape($edit['title']) : '',
        '__DESCRIPTION__' => $editMode ? uploadEscape($edit['description']) : '',
        '__DESCRIPTION_HELP__' => $editMode ? '(opcional; puedes dejarla vacía)' : '(opcional; se generará automáticamente si queda vacía)',
        '__FEATURED_CHECKED__' => $editMode && !empty($edit['featured']) ? 'checked' : '',
        '__DRAFT_CHECKED__' => $editMode && !empty($edit['draft']) ? 'checked' : '',
        '__SLUG_FIELD__' => $slugField,
        '__LATITUDE__' => $editMode && $edit['latitude'] != 0.0 ? uploadEscape((string) $edit['latitude']) : '',
        '__LONGITUDE__' => $editMode && $edit['longitude'] != 0.0 ? uploadEscape((string) $edit['longitude']) : '',
        '__SUBMIT_LABEL__' => $editMode ? 'Guardar cambios' : 'Publicar fotografía',
    ]);

    uploadPage($editMode ? 'Editar fotografía' : 'Subir una fotografía',
        adminNavigation((string) $_SESSION['csrf']) . $form);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') uploadPage('Acceso privado', loginForm((string) $_SESSION['csrf']));
// =============================================================================
// 5. ACCESO: formulario de inicio de sesión
// =============================================================================

function loginForm(string $csrf): string
{
    return '<form class="upload-form" method="post" action="/admin.php"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="' . uploadEscape($csrf) . '"><div class="upload-field"><label for="username">Usuario</label><input id="username" name="username" type="text" autocomplete="username" required></div><div class="upload-field"><label for="password">Contraseña</label><input id="password" name="password" type="password" autocomplete="current-password" required></div><button class="upload-submit" type="submit">Iniciar sesión</button><a href="/" class="project__link">Volver a la web</a></form>';
}
uploadPage('Acceso privado', loginForm((string) $_SESSION['csrf']));
