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
 * Subir fotos: una a una (inspeccionar, editar, publicar), por lotes, y la pantalla de subir o editar una foto.
 *
 * El código de las acciones y pantallas se movió desde admin.php sin cambios (ver tests/admin-characterization.cjs).
 */
final class UploadModule extends AbstractModule
{
    public function id(): string { return 'upload'; }
    public function order(): int { return 10; }
    public function menu(): array { return [['id' => 'upload', 'href' => '/admin.php', 'label' => 'Subir foto', 'short' => 'Subir', 'order' => 10]]; }
    public function actions(): array
    {
        return ['cancel_edit' => 'cancelEdit', 'batch_upload' => 'batchUpload', 'inspect' => 'inspect', 'save_edit' => 'saveEdit', 'complete' => 'complete'];
    }
    public function wantsPage(): bool { return isset($_GET['batch']); }
    public function page(): void { $this->batchPage(); }
    /** Lo que se ve si ninguna otra pantalla reclama la petición. */
    public function fallbackPage(): void { $this->uploadPage(); }
    /** Subida: descarta la foto pendiente de publicar. */
    protected function cancelEdit(): void
    {
        if (!empty($_SESSION['pending_upload']['path'])) @unlink((string) $_SESSION['pending_upload']['path']);
        if (!empty($_SESSION['pending_upload']['edit_path'])) @unlink((string) $_SESSION['pending_upload']['edit_path']);
        unset($_SESSION['pending_upload']);
        uploadJson(200, ['ok' => true]);
    }

    /** Carga en lote: el navegador envía cada foto por separado para respetar los límites del hosting. */
    protected function batchUpload(): void
    {
        try {
            $file = $_FILES['photo'] ?? null;
            $category = trim((string) ($_POST['category'] ?? ''));
            if (!is_array($file)) throw new RuntimeException('Selecciona una imagen.');
            if ($category === '' || mb_strlen($category, 'UTF-8') > 64 || preg_match('/[\\x00-\\x1F\\x7F]/u', $category)) {
                throw new RuntimeException('Escribe una categoría de hasta 64 caracteres.');
            }
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) throw new RuntimeException(uploadIniErrorMessage($error));
            if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > MAX_IMAGE_BYTES || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
                throw new RuntimeException('Cada fotografía debe pesar como máximo 15 MB.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (!isset($extensions[$mime]) || !@getimagesize((string) $file['tmp_name'])) throw new RuntimeException('Formato no admitido. Usa JPG, JPEG o PNG.');
            if (!is_dir(UPLOAD_DIRECTORY) || !is_writable(UPLOAD_DIRECTORY)) throw new RuntimeException('La carpeta de fotos no permite guardar imágenes.');
            $title = trim(preg_replace('/\\s+/u', ' ', pathinfo((string) ($file['name'] ?? ''), PATHINFO_FILENAME)) ?? '');
            if ($title === '') $title = 'Fotografía';
            $title = mb_substr($title, 0, 120, 'UTF-8');
            $stem = safeUploadStem($title);
            do {
                $basename = $stem . '-' . bin2hex(random_bytes(3)) . '.' . $extensions[$mime];
                $target = UPLOAD_DIRECTORY . '/' . $basename;
                $sidecar = UPLOAD_DIRECTORY . '/' . pathinfo($basename, PATHINFO_FILENAME) . '.txt';
            } while (file_exists($target) || file_exists($sidecar));
            $slug = generate_slug($title);
            $baseSlug = $slug !== '' ? $slug : 'fotografia';
            $suffix = 2;
            $usedSlugs = array_column(managedPhotos(), 'slug');
            while (in_array($slug, $usedSlugs, true)) $slug = $baseSlug . '-' . $suffix++;
            if (!move_uploaded_file((string) $file['tmp_name'], $target)) throw new RuntimeException('No se pudo guardar la fotografía.');
            $sidecarText = $title . "\n\n# Categoría: " . $category . "\n# Slug: " . $slug . "\n# auto-description-pending";
            if (($_POST['draft'] ?? '') === '1') $sidecarText .= "\n# Borrador: 1";
            $sidecarText .= "\n";
            if (@file_put_contents($sidecar, $sidecarText, LOCK_EX) === false) {
                @unlink($target);
                throw new RuntimeException('No se pudieron guardar los datos de la fotografía.');
            }
            @chmod($target, 0644);
            @chmod($sidecar, 0644);
            $aiConfig = ai_text_config([], site_text('text_52179dc42df7efe5'), rtrim(getenv('GALLERY_PUBLIC_URL') ?: '', '/'));
            if ($aiConfig['ai_enabled']) {
                $generator = new AiTextGenerator(Router::root(), $aiConfig);
                $generator->processMissing([['original' => $basename, 'sidecar' => $sidecar, 'filename_clean' => $title]], 1);
            }
            uploadJson(200, ['ok' => true, 'title' => $title, 'filename' => $basename]);
        } catch (RuntimeException $error) {
            uploadJson(422, ['ok' => false, 'error' => $error->getMessage()]);
        }
    }

    /** Subida, paso 1: valida la imagen recibida y detecta si trae coordenadas GPS. */
    protected function inspect(): void
    {
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

    /** Subida, paso 2: recibe la versión editada en el navegador. */
    protected function saveEdit(): void
    {
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

    /** Subida, paso 3: publica la foto o la guarda como borrador. */
    protected function complete(): void
    {
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
            if ($latRaw === '' || $lngRaw === '' || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
                uploadJson(422, ['ok' => false, 'error' => 'Indica ambas coordenadas o deja los dos campos vacíos.']);
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
            $siteConfigPath = \Kcg\Core\Installation::aiConfigFile();
            $siteConfig = $siteConfigPath !== '' && is_file($siteConfigPath) ? require $siteConfigPath : [];
            $siteConfig = ai_text_config($siteConfig, site_text('text_52179dc42df7efe5'), \Kcg\Core\Installation::siteUrl());
            if (!empty($siteConfig['ai_enabled'])) {
                $generator = new AiTextGenerator(Router::root(), $siteConfig);
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

    /** Carga por lotes: cada imagen se envía individualmente para respetar los límites de PHP del hosting. */
    protected function batchPage(): void
    {
        $batchCategories = array_values(array_unique(array_map(
            static fn($photo) => trim((string) $photo['category']) !== '' ? trim((string) $photo['category']) : 'Sin categoría',
            managedPhotos()
        )));
        sort($batchCategories, SORT_NATURAL | SORT_FLAG_CASE);
        $batchCategoryOptions = '<option value="" selected disabled>Selecciona una categoría</option>';
        foreach ($batchCategories as $categoryName) {
            $batchCategoryOptions .= '<option value="' . uploadEscape($categoryName) . '">' . uploadEscape($categoryName) . '</option>';
        }
        $batchContent = adminNavigation((string) $_SESSION['csrf'])
            . '<section class="admin-batch"><p class="admin-batch__intro">Selecciona varias fotos. Se subirán una a una; el título se tomará del nombre de archivo y podrás cambiarlo después con la edición rápida.</p>'
            . '<form id="photoBatchForm"><input type="hidden" name="csrf" value="' . uploadEscape((string) $_SESSION['csrf']) . '">'
            . '<div class="upload-field"><label for="batchPhotos">Fotografías (JPG o PNG, máximo 15 MB cada una)</label><input id="batchPhotos" type="file" accept="image/jpeg,image/png,.jpg,.jpeg,.png" multiple required></div>'
            . '<div class="upload-field"><label for="batchCategoryChoice">Categoría común</label><select id="batchCategoryChoice" required>' . $batchCategoryOptions . '<option value="__new__">+ Añadir categoría nueva</option></select><small class="upload-help">Se aplicará a todas las fotos del lote. Ej.: «Paisajes» o «Viaje a Bélgica».</small></div>'
            . '<div class="upload-field" id="batchNewCategoryField" hidden><label for="batchCategory">Nombre de la nueva categoría</label><input id="batchCategory" maxlength="64" placeholder="Escribe el nombre"><small class="upload-help">Elige un nombre corto que puedas reutilizar. Ej.: «Arquitectura».</small></div>'
            . '<label class="upload-featured"><input type="checkbox" id="batchDraft" checked> Guardar como borrador para revisar antes de publicar</label>'
            . '<div class="admin-batch__preview" id="batchPreview" aria-live="polite"><p>Las fotos seleccionadas aparecerán aquí.</p></div>'
            . '<p class="admin-batch__status" id="batchStatus" role="status" aria-live="polite"></p>'
            . '<button class="upload-submit" id="batchSubmit" type="submit" disabled>Subir fotografías</button></form></section>';
        uploadPage('Carga por lotes', $batchContent, 200, true);
    }

    /** Subir foto nueva o editar una existente (?edit=archivo): formulario + editor de imagen. */
    protected function uploadPage(): void
    {
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
        $formTemplate = (string) file_get_contents(Router::root() . '/core/Admin/templates/photo-form.html');
        $editMode = $edit !== null;
        $slugField = $editMode
            ? '<div class="upload-field"><label for="photoSlug">Enlace de la fotografía</label>'
              . '<input id="photoSlug" name="slug" type="text" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="'
              . uploadEscape($edit['slug']) . '" placeholder="Se generará a partir del título">'
              . '<small class="upload-help">Ej.: «gato-en-la-ventana». Si ya compartiste el enlace, cambiarlo puede romperlo.</small></div>'
            : '';
        $form = strtr($formTemplate, [
            '__STATUS__' => $status,
            '__CSRF__' => $csrf,
            '__OPTIONS__' => $options,
            '__CATEGORY_HELP__' => '<small class="upload-help">Usa una categoría breve para agrupar fotos parecidas. Ej.: «Paisajes», «Retratos» o «Bélgica».</small>',
            '__EDIT_FILE__' => $editMode ? uploadEscape($edit['filename']) : '',
            '__HAS_EDITED__' => $editMode && $edit['edited'] ? '1' : '0',
            '__ACTION__' => $editMode ? 'update_existing' : 'complete',
            '__FILE_HIDDEN__' => $editMode ? 'hidden' : '',
            '__FILE_REQUIRED__' => $editMode ? '' : 'required',
            '__GPS_STATUS_HIDDEN__' => $editMode ? 'hidden' : '',
            '__CHANGE_LABEL__' => $editMode ? 'Usar original' : 'Cambiar foto',
            '__STEP_LABEL__' => $editMode ? '2 / 2 · Actualizar fotografía' : '2 / 2 · Detalles de publicación',
            '__EDITOR_STEP__' => $editMode ? 'Editar fotografía' : '1 / 2 · Ajustes',
            '__START_BUTTON_HIDDEN__' => $editMode ? 'hidden' : '',
            '__TITLE__' => $editMode ? uploadEscape($edit['title']) : '',
            '__DESCRIPTION__' => $editMode ? uploadEscape($edit['description']) : '',
            '__DESCRIPTION_HELP__' => 'Opcional. Añade contexto que no cabe en el título: lugar, momento o historia. Ej.: «La luz del atardecer entra por la ventana del estudio».',
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
}
