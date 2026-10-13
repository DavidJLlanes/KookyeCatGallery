<?php
declare(strict_types=1);

namespace Kcg\Core\Admin;

use Kcg\Core\Admin\Modules\UploadModule;

/**
 * Enrutador del panel de administración (admin.php).
 *
 * Reúne los módulos de core/Admin/Modules/, les reparte las acciones (POST) y las pantallas (GET) y construye el menú.
 * Orden de una petición: configuración de acceso → fotografía original (?source=) → acción (POST) → pantalla (GET,
 * con sesión) → formulario de acceso. Las URL y los nombres de las acciones son los de siempre.
 */
final class Router
{
    /** Pantalla que se muestra si ningún módulo reclama la petición. */
    private const FALLBACK = 'upload';

    /** Raíz del sitio (la carpeta donde está admin.php). */
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Módulos descubiertos en core/Admin/Modules/*Module.php, ordenados por order().
     *
     * @return list<Module>
     */
    public static function modules(): array
    {
        static $modules;
        if ($modules !== null) return $modules;
        $found = [];
        foreach (glob(__DIR__ . '/Modules/*Module.php') ?: [] as $file) {
            $class = __NAMESPACE__ . '\\Modules\\' . basename($file, '.php');
            $module = new $class();
            if (!$module instanceof Module) throw new \RuntimeException($class . ' debe implementar ' . Module::class . '.');
            $found[] = $module;
        }
        usort($found, static fn(Module $a, Module $b): int => [$a->order(), $a->id()] <=> [$b->order(), $b->id()]);
        return $modules = $found;
    }

    public static function module(string $id): ?Module
    {
        foreach (self::modules() as $module) if ($module->id() === $id) return $module;
        return null;
    }

    /**
     * Entradas del menú de todos los módulos, por orden: id => [href, label, short].
     *
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function menu(): array
    {
        $entries = [];
        foreach (self::modules() as $module) foreach ($module->menu() as $entry) $entries[] = $entry;
        usort($entries, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);
        $menu = [];
        foreach ($entries as $entry) $menu[$entry['id']] = [$entry['href'], $entry['label'], $entry['short']];
        return $menu;
    }

    /** Entrada del menú que se resalta en esta petición («upload» si ningún módulo la reclama). */
    public static function activeMenu(): string
    {
        foreach (self::modules() as $module) {
            $active = $module->activeMenu();
            if ($active !== null) return $active;
        }
        return self::FALLBACK;
    }

    /** Atiende la petición completa. */
    public static function run(): void
    {
        $auth = \readUploadConfig();
        if (empty($auth['username']) || empty($auth['password_hash'])) {
            \uploadPage('Preparar acceso', '<p class="upload-message upload-message--error">El acceso privado aún no está configurado en el servidor. Configura el archivo externo de autenticación y vuelve a intentarlo.</p>', 503);
        }

        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['source'])) {
            if (empty($_SESSION['upload_authenticated'])) \uploadJson(401, ['ok' => false, 'error' => 'Inicia sesión para ver la fotografía.']);
            \managedPhotoSource((string) $_GET['source'], (string) ($_GET['variant'] ?? 'published'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') self::handlePost();

        if (!empty($_SESSION['upload_authenticated'])) self::renderPage();

        \uploadPage('Acceso privado', \loginForm((string) $_SESSION['csrf']));
    }

    /** Acciones (POST): todas exigen token CSRF; salvo las públicas (el inicio de sesión), también sesión iniciada. */
    private static function handlePost(): void
    {
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES)) {
            $limits = 'post_max_size = ' . (string) ini_get('post_max_size') . ', upload_max_filesize = ' . (string) ini_get('upload_max_filesize');
            $message = 'El servidor rechazó la subida completa y no se ha guardado nada: los archivos juntos pesan más de lo que admite el hosting (' . $limits
                . '). Sube las imágenes de una en una o pide al hosting subir esos límites (por ejemplo a 20M). Con Nginx, también client_max_body_size.';
            if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') && !empty($_SESSION['upload_authenticated'])) {
                \uploadPage('Subida demasiado grande', \adminNavigation((string) $_SESSION['csrf'])
                    . '<p class="upload-message upload-message--error" role="alert">' . \uploadEscape($message) . '</p><a class="upload-logout" href="javascript:history.back()">Volver</a>', 413, true);
            }
            \uploadJson(413, ['ok' => false, 'error' => $message]);
        }
        if (!\verifyCsrf()) \uploadJson(403, ['ok' => false, 'error' => 'La sesión ha caducado. Recarga la página y vuelve a intentarlo.']);

        $action = (string) ($_POST['action'] ?? '');
        $owner = null;
        foreach (self::modules() as $module) {
            if (isset($module->actions()[$action])) { $owner = $module; break; }
        }
        if ($owner !== null && in_array($action, $owner->publicActions(), true)) $owner->handle($action);

        if (empty($_SESSION['upload_authenticated'])) \uploadJson(401, ['ok' => false, 'error' => 'Inicia sesión para continuar.']);

        if ($owner !== null) $owner->handle($action);

        \uploadJson(400, ['ok' => false, 'error' => 'Acción no válida.']);
    }

    /** Pantallas (GET) con sesión iniciada: la del primer módulo que la reclama, o la de subir foto. */
    private static function renderPage(): void
    {
        if (!empty($_SESSION['pending_upload']['expires']) && $_SESSION['pending_upload']['expires'] < time()) {
            @unlink((string) ($_SESSION['pending_upload']['path'] ?? ''));
            if (!empty($_SESSION['pending_upload']['edit_path'])) @unlink((string) $_SESSION['pending_upload']['edit_path']);
            unset($_SESSION['pending_upload']);
        }
        foreach (self::modules() as $module) {
            if ($module->wantsPage()) $module->page();
        }
        $fallback = self::module(self::FALLBACK);
        if ($fallback instanceof UploadModule) $fallback->fallbackPage();
    }
}
