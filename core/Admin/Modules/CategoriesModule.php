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
 * Categorías y colecciones: crear, describir, renombrar, mover y eliminar.
 *
 * El código de las acciones y pantallas se movió desde admin.php sin cambios (ver tests/admin-characterization.cjs).
 */
final class CategoriesModule extends AbstractModule
{
    public function id(): string { return 'categories'; }
    public function order(): int { return 30; }
    public function menu(): array { return [['id' => 'categories', 'href' => '/admin.php?categories=1', 'label' => 'Categorías', 'short' => 'Temas', 'order' => 30]]; }
    public function actions(): array
    {
        return ['create_collection' => 'createCollection', 'delete_empty_collection' => 'deleteEmptyCollection', 'save_collection' => 'saveCollection', 'manage_category' => 'manageCategory'];
    }
    public function wantsPage(): bool { return isset($_GET['categories']); }
    public function page(): void { $this->categoriesPage(); }
    public function activeMenu(): ?string
    {
        return isset($_GET['categories']) || (string) ($_POST['action'] ?? '') === 'manage_category' ? 'categories' : null;
    }
    /** Crea una categoría vacía. */
    protected function createCollection(): void
    {
        try {
            gallery_collection_create((string) ($_POST['category'] ?? ''));
            header('Location: /admin.php?categories=1&status=' . rawurlencode('Categoría añadida.'), true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            uploadPage('Gestionar categorías', adminNavigation((string) $_SESSION['csrf'])
                . '<p class="upload-message upload-message--error" role="alert">' . uploadEscape($error->getMessage()) . '</p>'
                . '<a class="upload-logout" href="/admin.php?categories=1">Volver a categorías</a>', 422, true);
        }
    }

    /** Elimina una categoría vacía. */
    protected function deleteEmptyCollection(): void
    {
        try {
            gallery_collection_delete_empty((string) ($_POST['collection'] ?? ''), managedPhotos());
            header('Location: /admin.php?categories=1&status=' . rawurlencode('Categoría vacía eliminada.'), true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            uploadPage('Gestionar categorías', adminNavigation((string) $_SESSION['csrf'])
                . '<p class="upload-message upload-message--error" role="alert">' . uploadEscape($error->getMessage()) . '</p>'
                . '<a class="upload-logout" href="/admin.php?categories=1">Volver a categorías</a>', 422, true);
        }
    }

    /** Guarda la descripción y la portada de una colección. */
    protected function saveCollection(): void
    {
        try {
            $photos = managedPhotos();
            gallery_collection_save((string) ($_POST['collection'] ?? ''), (string) ($_POST['description'] ?? ''), (string) ($_POST['cover'] ?? ''), $photos);
            header('Location: /admin.php?categories=1&status=' . rawurlencode('Colección guardada.'), true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            uploadPage('Gestionar colecciones', adminNavigation((string) $_SESSION['csrf'])
                . '<p class="upload-message upload-message--error" role="alert">' . uploadEscape($error->getMessage()) . '</p>'
                . '<a class="upload-logout" href="/admin.php?categories=1">Volver a colecciones</a>', 422, true);
        }
    }

    /** Categorías: renombra, mueve o elimina una categoría con todas sus fotos. */
    protected function manageCategory(): void
    {
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

    /** Gestión de categorías. */
    protected function categoriesPage(): void
    {
        $photos = managedPhotos();
        $groups = [];
        foreach ($photos as $photo) {
            $name = trim((string) $photo['category']);
            if ($name === '') $name = 'Sin categoría';
            $groups[$name][] = $photo;
        }
        gallery_collections_sync_categories($photos);
        $collectionSettings = gallery_collections_load();
        foreach ($collectionSettings as $collectionName => $collectionData) {
            if (!isset($groups[$collectionName]) && !empty($collectionData['manual'])) $groups[$collectionName] = [];
        }
        uksort($groups, 'strnatcasecmp');
        $status = isset($_GET['status']) ? '<p class="upload-message" role="status">' . uploadEscape((string) $_GET['status']) . '</p>' : '';
        $content = adminNavigation((string) $_SESSION['csrf']) . $status
            . '<p class="upload-help">Cada categoría reúne fotos relacionadas; después podrás asignarlas desde Gestionar fotos o Carga por lotes.</p>'
            . '<section class="admin-category-create" aria-labelledby="createCategoryTitle">'
            . '<div class="admin-category-create__intro"><span class="admin-category-create__icon" aria-hidden="true">+</span><div><h2 id="createCategoryTitle">Añadir categoría</h2><p>Crea un nuevo grupo para organizar tus fotografías.</p></div></div>'
            . '<form method="post" action="/admin.php" class="admin-category-create__form">'
            . '<input type="hidden" name="action" value="create_collection"><input type="hidden" name="csrf" value="' . uploadEscape((string) $_SESSION['csrf']) . '">'
            . '<div class="upload-field"><label for="new-category-name">Nombre de la categoría</label><input id="new-category-name" type="text" name="category" maxlength="64" placeholder="Ej.: Retratos" required></div>'
            . '<button class="upload-submit" type="submit">Crear categoría</button></form></section>';
        if (!$groups) $content .= '<p class="upload-message">Todavía no hay categorías.</p>';
        foreach ($groups as $name => $items) {
            $collection = $collectionSettings[$name] ?? ['description' => '', 'cover' => ''];
            $coverOptions = '<option value="">Elegir portada automáticamente</option>';
            foreach ($items as $photo) {
                $selected = ($collection['cover'] ?? '') === $photo['filename'] ? ' selected' : '';
                $coverOptions .= '<option value="' . uploadEscape($photo['filename']) . '"' . $selected . '>' . uploadEscape($photo['title'] ?: $photo['filename']) . '</option>';
            }
            $escapedName = uploadEscape($name);
            $options = '';
            foreach (array_keys($groups) as $candidate) {
                if ($candidate === $name) continue;
                $value = uploadEscape($candidate);
                $options .= '<option value="' . $value . '">' . $value . '</option>';
            }
            $content .= '<section class="admin-category-card admin-collection-card"><div><h2>' . $escapedName . '</h2><p>' . (count($items) ? count($items) . ' fotografías · colección' : 'Colección vacía · asígnale fotos desde Gestionar fotos o Carga por lotes.') . '</p></div>'
                . '<form method="post" action="/admin.php" class="admin-collection-form">'
                . '<input type="hidden" name="action" value="save_collection"><input type="hidden" name="csrf" value="' . uploadEscape((string) $_SESSION['csrf']) . '"><input type="hidden" name="collection" value="' . $escapedName . '">'
                . '<div class="upload-field"><label>Descripción</label><small class="upload-help">Texto que presentará la colección. Ej.: «Calles, plazas y rincones de la ciudad».</small><textarea name="description" maxlength="280" rows="2" placeholder="Ej.: Paseos tranquilos al atardecer">' . uploadEscape((string) ($collection['description'] ?? '')) . '</textarea></div>'
                . '<div class="upload-field"><label>Fotografía de portada</label><small class="upload-help">Elige la imagen que mejor representa esta colección; si no eliges una, se seleccionará automáticamente.</small><select name="cover">' . $coverOptions . '</select></div>'
                . '<button class="upload-submit" type="submit">Guardar colección</button></form>';
            if ($items) {
                $content .= '<form method="post" action="/admin.php" class="admin-category-form" data-category-action>'
                    . '<input type="hidden" name="action" value="manage_category"><input type="hidden" name="csrf" value="' . uploadEscape((string) $_SESSION['csrf']) . '">'
                    . '<input type="hidden" name="source" value="' . $escapedName . '">'
                    . '<div class="upload-field"><label>Acción</label><select name="operation" data-category-operation>'
                    . '<option value="rename">Cambiar nombre o combinar</option><option value="move_existing"' . ($options === '' ? ' disabled' : '') . '>Mover a una existente</option>'
                    . '<option value="move_new">Mover a una nueva</option><option value="delete_photos">Eliminar categoría y todas sus fotos</option></select></div>'
                    . '<div class="upload-field" data-existing-target hidden><label>Destino existente</label><select name="target" data-existing-select disabled>' . ($options ?: '<option value="">No hay otras categorías</option>') . '</select></div>'
                    . '<div class="upload-field" data-new-target><label>Nuevo nombre de categoría</label><input type="text" name="target" maxlength="64" data-new-input placeholder="Escribe el nombre" required></div>'
                    . '<div class="upload-field" data-delete-confirm hidden><label>Escribe ELIMINAR para confirmar el borrado de ' . count($items) . ' fotos</label><input type="text" name="confirm" maxlength="20" data-confirm-input autocomplete="off" disabled></div>'
                    . '<button class="upload-submit" type="submit">Aplicar a las ' . count($items) . ' fotos</button></form>';
            } elseif (!empty($collection['manual'])) {
                $content .= '<form method="post" action="/admin.php" class="admin-empty-category-delete" onsubmit="return confirm(\'¿Eliminar esta categoría vacía?\')">'
                    . '<input type="hidden" name="action" value="delete_empty_collection"><input type="hidden" name="csrf" value="' . uploadEscape((string) $_SESSION['csrf']) . '"><input type="hidden" name="collection" value="' . $escapedName . '">'
                    . '<button class="upload-submit" type="submit">Eliminar categoría vacía</button></form>';
            }
            $content .= '</section>';
        }
        uploadPage('Gestionar categorías', $content, 200, true);
    }
}
