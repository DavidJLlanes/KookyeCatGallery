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
 * Biblioteca de fotos: listado, edición rápida, orden y borrado.
 *
 * El código de las acciones y pantallas se movió desde admin.php sin cambios (ver tests/admin-characterization.cjs).
 */
final class LibraryModule extends AbstractModule
{
    public function id(): string { return 'library'; }
    public function order(): int { return 20; }
    public function menu(): array { return [['id' => 'library', 'href' => '/admin.php?library=1', 'label' => 'Gestionar fotos', 'short' => 'Fotos', 'order' => 20]]; }
    public function actions(): array { return ['update_existing' => 'updateExisting', 'reorder_photos' => 'reorderPhotos', 'delete_existing' => 'deleteExisting']; }
    public function wantsPage(): bool { return isset($_GET['library']); }
    public function page(): void { $this->libraryPage(); }
    public function activeMenu(): ?string
    {
        $action = (string) ($_POST['action'] ?? '');
        return isset($_GET['library']) || isset($_GET['edit']) || $action === 'delete_existing' ? 'library' : null;
    }
    /** Biblioteca: guarda los cambios de una foto ya publicada (respuesta JSON). */
    protected function updateExisting(): void
    {
        try {
            managedSavePhoto($_POST, $_FILES['edited_photo'] ?? null);
            uploadJson(200, ['ok' => true, 'message' => 'Fotografía actualizada correctamente.']);
        } catch (RuntimeException $error) {
            uploadJson(422, ['ok' => false, 'error' => $error->getMessage()]);
        }
    }

    /** Biblioteca: guarda el orden manual de las fotos (respuesta JSON). */
    protected function reorderPhotos(): void
    {
        try {
            $order = json_decode((string) ($_POST['order'] ?? '[]'), true);
            if (!is_array($order)) throw new RuntimeException('Orden no válido.');
            managedReorderPhotos($order);
            uploadJson(200, ['ok' => true, 'message' => 'Orden guardado.']);
        } catch (RuntimeException $error) {
            uploadJson(422, ['ok' => false, 'error' => $error->getMessage()]);
        }
    }

    /** Biblioteca: elimina una foto tras escribir ELIMINAR (respuesta JSON). */
    protected function deleteExisting(): void
    {
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

    /** Biblioteca: listado, búsqueda, orden y borrado de fotos. */
    protected function libraryPage(): void
    {
        (new ImageProcessor(Router::root()))->processAll();
        $photos = managedPhotos();
        $libraryCategories = array_values(array_unique(array_map(static fn($item) => trim((string) $item['category']) !== '' ? trim((string) $item['category']) : 'Sin categoría', $photos)));
        sort($libraryCategories, SORT_NATURAL | SORT_FLAG_CASE);
        $categoryOptions = '';
        foreach ($libraryCategories as $categoryName) {
            $categoryOptions .= '<option value="' . uploadEscape($categoryName) . '">' . uploadEscape($categoryName) . '</option>';
        }
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
            $description = uploadEscape((string) ($photo['description'] ?? ''));
            $latitude = uploadEscape((string) ($photo['latitude'] ?? ''));
            $longitude = uploadEscape((string) ($photo['longitude'] ?? ''));
            $cards .= '<article class="admin-photo-card' . ($featured ? ' is-featured' : '') . ($draft ? ' is-draft' : '') . '" data-photo-file="' . $filename . '" data-photo-mtime="' . (int) $photo['mtime'] . '" data-photo-category="' . $category . '" data-photo-draft="' . ($draft ? '1' : '0') . '" data-photo-featured="' . ($featured ? '1' : '0') . '" data-photo-title="' . $title . '" data-photo-description="' . $description . '" data-photo-slug="' . $slug . '" data-photo-latitude="' . $latitude . '" data-photo-longitude="' . $longitude . '">'
                . '<img src="' . $thumbnail . '" alt="" loading="lazy" decoding="async">'
                . '<div class="admin-photo-card__body"><div class="admin-photo-card__heading"><h2>' . $title . '</h2>'
                . ($featured ? '<span class="admin-photo-featured" title="Fotografía destacada">★ Destacada</span>' : '')
                . ($draft ? '<span class="admin-photo-draft">Borrador</span>' : '') . '</div><p>' . $category . '</p>'
                . ($slug !== '' ? '<p class="admin-photo-hearts" data-admin-heart="' . $slug . '">♥ <span>0</span> corazones</p>' : '')
                . '<div class="admin-photo-order-actions"><button type="button" data-order-up aria-label="Mover antes">↑</button><button type="button" data-order-down aria-label="Mover después">↓</button></div>'
                . '<div class="admin-photo-card__actions"><button class="upload-logout" type="button" data-quick-edit>Edición rápida</button><a class="upload-logout" href="' . uploadEscape($editUrl) . '">Editar foto</a>'
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
            . '<div class="admin-library__intro"><p>Tu archivo fotográfico, ordenado y listo para editar.</p>'
            . '<div class="admin-library__intro-actions"><a class="upload-submit" href="/admin.php?batch=1">Subida por lotes</a><a class="upload-submit" href="/admin.php">+ Subir una foto</a></div></div>'
            . '<div class="admin-library-tools"><div class="upload-field"><label for="photoLibrarySearch">Buscar fotografías</label>'
            . '<input id="photoLibrarySearch" type="search" autocomplete="off" placeholder="Título, categoría o archivo"></div>'
            . '<div class="upload-field"><label for="photoLibraryCategory">Categoría</label><select id="photoLibraryCategory"><option value="">Todas las categorías</option>' . $categoryOptions . '</select></div>'
            . '<div class="upload-field"><label for="photoLibraryStatus">Estado</label><select id="photoLibraryStatus"><option value="">Todos los estados</option><option value="published">Publicadas</option><option value="draft">Borradores</option><option value="featured">Destacadas</option></select></div>'
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
            . '<dialog class="admin-quick-edit" id="adminQuickEdit" aria-labelledby="quickEditTitle">'
            . '<form id="adminQuickEditForm" method="post" action="/admin.php"><input type="hidden" name="action" value="update_existing">'
            . '<input type="hidden" name="csrf" value="' . uploadEscape((string) $_SESSION['csrf']) . '"><input type="hidden" name="file" data-quick-file>'
            . '<h2 id="quickEditTitle">Edición rápida</h2><p class="admin-quick-edit__hint">Actualiza los datos sin entrar al editor de imagen.</p>'
            . '<div class="upload-field"><label for="quickTitle">Título</label><input id="quickTitle" name="title" maxlength="120" required><small class="upload-help">También es el texto alternativo: describe lo que aparece. Ej.: «Dos gatos jugando sobre una alfombra roja».</small></div>'
            . '<div class="upload-field"><label for="quickCategory">Categoría</label><input id="quickCategory" name="category" maxlength="64" required><small class="upload-help">Agrupa fotos parecidas. Ej.: «Paisajes» o «Retratos».</small></div>'
            . '<div class="upload-field"><label for="quickDescription">Descripción</label><textarea id="quickDescription" name="description" rows="4" maxlength="5000"></textarea><small class="upload-help">Añade contexto: lugar, momento o historia. Ej.: «La luz del atardecer entra por la ventana del estudio».</small></div>'
            . '<div class="upload-field"><label for="quickSlug">Enlace de la foto</label><input id="quickSlug" name="slug" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*"></div>'
            . '<div class="admin-quick-edit__checks"><label><input type="checkbox" name="featured" value="1"> Destacada</label><label><input type="checkbox" name="draft" value="1"> Borrador</label></div>'
            . '<input type="hidden" name="latitude" data-quick-latitude><input type="hidden" name="longitude" data-quick-longitude>'
            . '<p class="admin-quick-edit__error" data-quick-error role="alert" hidden></p>'
            . '<div class="admin-delete-dialog__actions"><button type="button" data-quick-cancel>Cancelar</button><button type="submit" data-quick-save>Guardar cambios</button></div></form></dialog>';
        uploadPage('Gestionar fotografías', $content, 200, true);
    }
}
