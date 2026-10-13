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
 * Ajustes del sitio: contenido, diseño, textos, redes y publicación; páginas de texto y vista previa en móvil.
 *
 * El código de las acciones y pantallas se movió desde admin.php sin cambios (ver tests/admin-characterization.cjs).
 */
final class SettingsModule extends AbstractModule
{
    public function id(): string { return 'settings'; }
    public function order(): int { return 50; }
    public function menu(): array
    {
        return [
            ['id' => 'profile', 'href' => '/admin.php?settings=1&amp;section=content', 'label' => 'Ajustes', 'short' => 'Ajustes', 'order' => 50],
            ['id' => 'design', 'href' => '/admin.php?settings=1&amp;section=design', 'label' => 'Diseño', 'short' => 'Diseño', 'order' => 60],
            ['id' => 'texts', 'href' => '/admin.php?settings=1&amp;section=texts', 'label' => 'Textos', 'short' => 'Textos', 'order' => 70],
        ];
    }
    public function actions(): array { return ['save_site_page' => 'saveSitePage', 'save_site_settings' => 'saveSiteSettings']; }
    public function wantsPage(): bool { return isset($_GET['settings']) || isset($_GET['mobile_preview']); }
    public function page(): void
    {
        if (isset($_GET['settings'])) $this->settingsPage();
        $this->mobilePreviewPage();
    }
    public function activeMenu(): ?string
    {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_site_page') return 'texts';
        if (isset($_GET['settings']) || $action === 'save_site_settings') {
            $section = (string) ($_POST['section'] ?? $_GET['section'] ?? 'profile');
            if ($section === 'page') return 'texts';
            if ($section === 'content' || $section === 'profile' || $section === 'social' || $section === 'publication') return 'profile';
            return in_array($section, ['design', 'texts'], true) ? $section : 'profile';
        }
        return null;
    }
    /** Ajustes → Diseño: guarda el contenido de una página de texto. */
    protected function saveSitePage(): void
    {
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

    /** Ajustes → Perfil o Diseño: guarda ajustes, orden de bloques y subida de foto de perfil y logo. */
    protected function saveSiteSettings(): void
    {
        try {
            $settingsInput = $_POST;
            $currentSettings = site_settings_load();
            // Los textos generales se editan en la propia web; conserva su contenido al guardar el panel.
            foreach (['profile_website_url'] as $preservedKey) {
                if (!array_key_exists($preservedKey, $settingsInput)) $settingsInput[$preservedKey] = $currentSettings[$preservedKey] ?? '';
            }
            if (is_array($settingsInput['texts'] ?? null)) {
                $settingsInput['texts'] = array_replace($currentSettings['texts'] ?? [], $settingsInput['texts']);
            } elseif (!array_key_exists('texts', $settingsInput)) {
                $settingsInput['texts'] = $currentSettings['texts'] ?? [];
            }
            $settingsInput = site_gallery_preserve_inactive($settingsInput, $currentSettings);
            foreach (['profile_image_file' => 'profile_image', 'logo_image_file' => 'logo_image'] as $fileKey => $settingKey) {
                $settingsInput[$settingKey] = $currentSettings[$settingKey] ?? \Kcg\Core\Settings\Schema::defaults()[$settingKey];
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
                    $oldPath = Router::root() . '/' . $stem . '.' . $ext;
                    if (is_file($oldPath)) @unlink($oldPath);
                }
                $filename = $stem . '.' . $extensions[$mime];
                $destination = Router::root() . '/' . $filename;
                if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                    throw new RuntimeException('No se pudo guardar la imagen. Revisa los permisos del servidor.');
                }
                @chmod($destination, 0644);
                $settingsInput[$settingKey] = '/' . $filename;
            }
            site_brand_assets_process($_FILES);
            site_settings_save($settingsInput);
            $section = (string) ($_POST['section'] ?? 'content');
            if ($section === 'profile') $section = 'content';
            if (!in_array($section, ['content', 'design', 'texts', 'social', 'publication'], true)) $section = 'content';
            header('Location: /admin.php?settings=1&section=' . rawurlencode($section) . '&saved=1', true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            $values = site_settings_load();
            try { $values = site_settings_validate($_POST); } catch (InvalidArgumentException $ignored) {}
            $section = (string) ($_POST['section'] ?? 'content');
            if ($section === 'profile') $section = 'content';
            if (!in_array($section, ['content', 'design', 'texts', 'social', 'publication'], true)) $section = 'content';
            $settingsTitles = ['content' => 'Contenido', 'design' => 'Diseño', 'texts' => 'Textos', 'social' => 'Redes Sociales', 'publication' => 'Publicación'];
            $settingsErrorContent = adminNavigation((string) $_SESSION['csrf'])
                . '<p class="upload-message upload-message--error" role="alert">' . uploadEscape($error->getMessage()) . '</p>'
                . site_settings_form((string) $_SESSION['csrf'], $values, $section);
            uploadPage($settingsTitles[$section], $settingsErrorContent, 422, true);
        }
    }

    /** Ajustes organizados en Contenido, Diseño, Textos, Redes y Publicación. */
    protected function settingsPage(): void
    {
        $status = isset($_GET['saved']) ? '<p class="upload-message" role="status">Configuración guardada.</p>' : '';
        $section = (string) ($_GET['section'] ?? 'content');
        if ($section === 'profile') $section = 'content';
        if (!in_array($section, ['content', 'design', 'texts', 'social', 'publication', 'page'], true)) $section = 'content';
        if ($section === 'page') {
            $page = (string) ($_GET['doc'] ?? '');
            if (!in_array($page, site_editable_page_keys(), true)) $page = 'legal_notice';
            $status = isset($_GET['saved']) ? '<p class="upload-message" role="status">Página guardada.</p>' : '';
            uploadPage(site_page_labels()[$page], adminNavigation((string) $_SESSION['csrf']) . $status
                . '<nav class="admin-subnav admin-subnav--back" aria-label="Secciones de configuración"><a href="/admin.php?settings=1&amp;section=texts">← Textos</a></nav>'
                . site_page_editor_form((string) $_SESSION['csrf'], $page), 200, true);
        }
        $previewLink = $section === 'design' ? '<a class="admin-mobile-preview-link" href="/admin.php?mobile_preview=1">Vista previa en móvil</a>' : '';
        $settingsTitles = ['content' => 'Contenido', 'design' => 'Diseño', 'texts' => 'Textos', 'social' => 'Redes Sociales', 'publication' => 'Publicación'];
        $settingsContent = adminNavigation((string) $_SESSION['csrf']) . $status . $previewLink
            . site_settings_form((string) $_SESSION['csrf'], null, $section);
        uploadPage($settingsTitles[$section] ?? 'Ajustes', $settingsContent, 200, true);
    }

    /** Vista previa de la web con ancho móvil. */
    protected function mobilePreviewPage(): void
    {
        $previewContent = adminNavigation((string) $_SESSION['csrf'])
            . '<section class="admin-mobile-preview"><p><a href="/admin.php?settings=1&amp;section=design">Volver a Diseño</a> · Vista de la web con ancho móvil. Guarda los cambios de diseño y textos y vuelve aquí para revisarlos.</p>'
            . '<div class="admin-mobile-preview__device"><iframe src="/" title="Vista previa móvil de la web" loading="eager"></iframe></div></section>';
        uploadPage('Vista previa móvil', $previewContent, 200, true);
    }
}
