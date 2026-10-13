<?php
declare(strict_types=1);

namespace Kcg\Core\Admin\Modules;

use InvalidArgumentException;
use RuntimeException;
use AiTextGenerator;
use ImageProcessor;
use Kcg\Core\Admin\AbstractModule;
use Kcg\Core\Admin\Router;
use finfo;

/**
 * Acceso al panel: inicio de sesión (con límite de intentos) y cierre de sesión.
 *
 * El código de las acciones y pantallas se movió desde admin.php sin cambios (ver tests/admin-characterization.cjs).
 */
final class AuthModule extends AbstractModule
{
    public function id(): string { return 'auth'; }
    public function order(): int { return 0; }
    public function actions(): array { return ['login' => 'login', 'logout' => 'logout']; }
    /** El inicio de sesión es lo único que se puede pedir sin haber iniciado sesión. */
    public function publicActions(): array { return ['login']; }
    /** Inicio de sesión, con límite de 5 intentos cada 15 minutos. */
    protected function login(): void
    {
        $auth = readUploadConfig();
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

    /** Cierre de sesión (descarta también la subida pendiente). */
    protected function logout(): void
    {
        if (!empty($_SESSION['pending_upload']['path'])) @unlink((string) $_SESSION['pending_upload']['path']);
        if (!empty($_SESSION['pending_upload']['edit_path'])) @unlink((string) $_SESSION['pending_upload']['edit_path']);
        $_SESSION = [];
        session_destroy();
        header('Location: /admin.php', true, 303);
        exit;
    }
}
