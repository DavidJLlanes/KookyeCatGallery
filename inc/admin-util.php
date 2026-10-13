<?php
declare(strict_types=1);

/**
 * Utilidades del panel de administración (respuestas JSON, coordenadas EXIF, configuración de acceso, CSRF y nombres de archivo).
 * Solo define funciones: no tiene efectos secundarios al cargarse. Las usan admin.php y los módulos de core/Admin/.
 */

function uploadJson(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    header('Content-Length: ' . strlen($body)); // con la longitud, el navegador da la respuesta por completa aunque queden tareas (uploadAfterResponse)
    echo $body;
    exit;
}

/** Redirección 303 sin cuerpo. Como uploadJson(), deja que el navegador termine antes que las tareas de uploadAfterResponse(). */
function uploadRedirect(string $url): never
{
    http_response_code(303);
    header('Location: ' . $url);
    header('Content-Length: 0');
    exit;
}

/**
 * Ejecuta una tarea lenta (p. ej. pedir una descripción a la IA) después de responder al navegador: quien sube la foto no espera.
 * Se llama antes de uploadJson()/uploadRedirect(); la tarea corre al terminar el script. Sin PHP-FPM, el navegador ya tiene la
 * respuesta completa por su Content-Length.
 */
function uploadAfterResponse(callable $task): void
{
    register_shutdown_function(static function () use ($task): void {
        ignore_user_abort(true);
        @set_time_limit(180);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) @ob_end_flush();
            @flush();
        }
        try {
            $task();
        } catch (Throwable $error) {
            error_log('Tarea posterior a la respuesta: ' . $error->getMessage());
        }
    });
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
    if ($stem === '') $stem = 'fotografia-leon';
    return substr($stem, 0, 70);
}
