<?php
declare(strict_types=1);

/**
 * Panel de administración del microblog (pestaña «Microblog»).
 *
 * Solo define funciones; las llama admin.php una vez comprobada la sesión y el token CSRF.
 *
 *   admin_microblog_handle()   acciones POST: microblog_save (crear / editar / responder) y microblog_delete
 *   admin_microblog_page()     página: nuevo post, lista de hilos, edición y respuesta
 *   admin_microblog_form()     formulario de un post
 *
 * Las reglas de contenido (límites, imágenes, niveles) están en inc/microblog.php.
 */

/**
 * Procesa las acciones del microblog. Termina con una redirección o con la página del panel con el error.
 */
function admin_microblog_handle(string $action, string $csrf): void
{
    if ($action === 'microblog_delete') {
        $id = (string) ($_POST['id'] ?? '');
        try {
            if (!microblog_valid_id($id)) throw new InvalidArgumentException('El post no es válido.');
            $removed = microblog_delete($id);
            $message = $removed > 1 ? 'Hilo eliminado (' . $removed . ' posts).' : ($removed === 1 ? 'Post eliminado.' : 'El post ya no existía.');
            header('Location: /admin.php?microblog=1&status=' . rawurlencode($message), true, 303);
            exit;
        } catch (InvalidArgumentException | RuntimeException $error) {
            admin_microblog_page($csrf, ['error' => $error->getMessage()]);
        }
    }

    // microblog_save
    $id = (string) ($_POST['id'] ?? '');
    $parent = (string) ($_POST['parent'] ?? '');
    try {
        $uploads = microblog_collect_uploads($_FILES);
        if ($id !== '') {
            if (!microblog_valid_id($id)) throw new InvalidArgumentException('El post no es válido.');
            $remove = array_values(array_filter(is_array($_POST['remove_images'] ?? null) ? $_POST['remove_images'] : [], 'is_string'));
            microblog_update($id, $_POST, $uploads, $remove);
            $message = 'Post actualizado.';
        } else {
            if ($parent !== '' && !microblog_valid_id($parent)) throw new InvalidArgumentException('El post al que respondes no es válido.');
            $created = microblog_create($_POST, $uploads, $parent !== '' ? $parent : null);
            $message = microblog_is_scheduled($created)
                ? ($parent !== '' ? 'Respuesta' : 'Post') . ' programado para el ' . microblog_format_time('j/n/Y \a \l\a\s H:i', $created['created']) . '.'
                : ($parent !== '' ? 'Respuesta publicada.' : 'Post publicado.');
            // Formulario flotante de la web: responde en JSON en lugar de redirigir.
            if (!empty($_POST['ajax'])) {
                uploadJson(200, ['ok' => true, 'message' => $message, 'id' => $created['id'], 'scheduled' => microblog_is_scheduled($created),
                    'visible' => !empty(site_settings_load()['show_microblog'])]);
            }
        }
        header('Location: /admin.php?microblog=1&status=' . rawurlencode($message), true, 303);
        exit;
    } catch (InvalidArgumentException | RuntimeException $error) {
        if (!empty($_POST['ajax'])) uploadJson(422, ['ok' => false, 'error' => $error->getMessage()]);
        $context = ['error' => $error->getMessage(), 'values' => $_POST];
        if ($id !== '') $context['edit'] = $id;
        elseif ($parent !== '') $context['reply'] = $parent;
        admin_microblog_page($csrf, $context);
    }
}

/**
 * Formulario de un post.
 *
 * @param array|null $post   Post que se edita (null = nuevo).
 * @param array      $values Valores enviados antes de un error (sustituyen a los del post).
 * @param string     $parent Id del post al que se responde ('' = post nuevo).
 */
function admin_microblog_form(string $csrf, ?array $post, array $values = [], string $parent = '', bool $focus = false): string
{
    $e = 'uploadEscape';
    $pick = static fn(string $key, string $default = '') => $values[$key] ?? $default;
    $isReply = $parent !== '' || ($post !== null && $post['parent'] !== null);
    $text = (string) $pick('text', $post['text'] ?? '');
    $quote = $post['quote'] ?? ['text' => '', 'source' => '', 'url' => ''];
    $level = (int) $pick('level', (string) ($post['level'] ?? 0));
    $uid = $post !== null ? $post['id'] : ($parent !== '' ? 'r' . $parent : 'new');
    // La fecha de publicación solo se puede fijar al crear o mientras el post sigue programado.
    $scheduled = $post !== null && microblog_is_scheduled($post);
    $showSchedule = $post === null || $scheduled;
    $publishValue = (string) $pick('publish_at', $scheduled ? microblog_format_time('Y-m-d\TH:i', $post['created']) : '');
    $pinned = array_key_exists('text', $values) ? !empty($values['pinned']) : !empty($post['pinned']);

    $html = '<form method="post" action="/admin.php?microblog=1" enctype="multipart/form-data" class="upload-form mb-admin-form" data-mb-admin-form>'
        . '<input type="hidden" name="action" value="microblog_save"><input type="hidden" name="csrf" value="' . $e($csrf) . '">'
        . ($post !== null ? '<input type="hidden" name="id" value="' . $e($post['id']) . '">' : '')
        . ($parent !== '' ? '<input type="hidden" name="parent" value="' . $e($parent) . '">' : '')
        . '<div class="upload-field"><label for="mb-text-' . $e($uid) . '">' . ($isReply ? 'Respuesta' : 'Texto') . '</label>'
        . '<div class="mb-admin-toolbar"><button type="button" class="mb-admin-tool" data-mb-link-tool title="Insertar enlace (Ctrl+K)">🔗 Enlace</button></div>'
        . '<div class="mb-admin-linkbox" data-mb-linkbox hidden>'
        . '<div class="upload-field"><label>Texto que se verá</label><input type="text" data-mb-link-label maxlength="200" placeholder="mi web"></div>'
        . '<div class="upload-field"><label>Dirección</label><input type="url" data-mb-link-href maxlength="500" placeholder="https://…"></div>'
        . '<div class="mb-admin-linkbox__actions"><button type="button" class="mb-admin-tool" data-mb-link-cancel>Cancelar</button><button type="button" class="mb-admin-tool is-primary" data-mb-link-apply>Insertar</button></div>'
        . '<small class="upload-help" data-mb-link-error role="alert" hidden></small></div>'
        . '<textarea id="mb-text-' . $e($uid) . '" name="text" rows="' . ($isReply ? 3 : 5) . '" maxlength="' . MICROBLOG_MAX_TEXT . '"'
        . ($focus ? ' autofocus' : '') . ' placeholder="' . ($isReply ? 'Continúa el hilo…' : '¿Qué quieres compartir?') . '" data-mb-text>' . $e($text) . '</textarea>'
        . '<small class="upload-help"><span data-mb-count>0</span> / ' . MICROBLOG_MAX_TEXT . ' · Enlace con texto: <code>[texto](https://…)</code> o el botón Enlace (Ctrl+K). Las URLs sueltas se enlazan solas.</small></div>';

    // Nivel de destacado (solo posts raíz).
    if (!$isReply) {
        $descriptions = [0 => 'Tarjeta sencilla', 1 => 'Franja de color y velo', 2 => 'Borde luminoso y brillo'];
        $html .= '<fieldset class="mb-levels"><legend>Destacado</legend><div class="mb-levels__grid">';
        foreach (microblog_levels() as $value => $label) {
            $html .= '<label class="mb-level mb-level--' . $value . '"><input type="radio" name="level" value="' . $value . '"' . ($level === $value ? ' checked' : '') . '>'
                . '<span class="mb-level__preview" aria-hidden="true"><i></i><i></i><i></i></span>'
                . '<span class="mb-level__name">' . $e($label) . '</span><small>' . $e($descriptions[$value]) . '</small></label>';
        }
        $html .= '</div></fieldset>';
    }

    // Publicación: fijar arriba y programar.
    if (!$isReply || $showSchedule) {
        $html .= '<div class="mb-publish">';
        if (!$isReply) {
            $html .= '<label class="site-section-switch"><input type="checkbox" name="pinned" value="1"' . ($pinned ? ' checked' : '') . '>'
                . '<span class="site-section-switch__track" aria-hidden="true"></span><span>Fijar arriba del feed (sustituye al fijado actual)</span></label>';
        }
        if ($showSchedule) {
            $html .= '<div class="upload-field"><label for="mb-publish-' . $e($uid) . '">Programar publicación (opcional)</label>'
                . '<input id="mb-publish-' . $e($uid) . '" name="publish_at" type="datetime-local" value="' . $e($publishValue) . '">'
                . '<small class="upload-help">Hora de España. Vacío = publicar ahora. Hasta esa hora el post no se ve en la web.</small></div>';
        }
        $html .= '</div>';
    }

    // Cita.
    $quoteText = (string) $pick('quote_text', $quote['text']);
    $quoteOpen = $quoteText !== '' || (string) $pick('quote_source', $quote['source']) !== '';
    $html .= '<details class="mb-fold"' . ($quoteOpen ? ' open' : '') . '><summary>Cita</summary><div class="mb-fold__body">'
        . '<div class="upload-field"><label for="mb-quote-' . $e($uid) . '">Texto de la cita</label><textarea id="mb-quote-' . $e($uid) . '" name="quote_text" rows="3" maxlength="' . MICROBLOG_MAX_QUOTE . '">' . $e($quoteText) . '</textarea></div>'
        . '<div class="mb-fold__row"><div class="upload-field"><label for="mb-qsrc-' . $e($uid) . '">Fuente</label><input id="mb-qsrc-' . $e($uid) . '" name="quote_source" type="text" maxlength="120" value="' . $e((string) $pick('quote_source', $quote['source'])) . '" placeholder="Autor, libro, web…"></div>'
        . '<div class="upload-field"><label for="mb-qurl-' . $e($uid) . '">Enlace de la fuente (opcional)</label><input id="mb-qurl-' . $e($uid) . '" name="quote_url" type="url" maxlength="500" value="' . $e((string) $pick('quote_url', $quote['url'])) . '" placeholder="https://…"></div></div>'
        . '</div></details>';

    // Enlaces.
    $linkUrls = is_array($values['link_url'] ?? null) ? array_values($values['link_url']) : array_column($post['links'] ?? [], 'url');
    $linkTitles = is_array($values['link_title'] ?? null) ? array_values($values['link_title']) : array_column($post['links'] ?? [], 'title');
    $filledLinks = count(array_filter($linkUrls, static fn($u) => trim((string) $u) !== ''));
    $html .= '<details class="mb-fold"' . ($filledLinks ? ' open' : '') . '><summary>Enlaces</summary><div class="mb-fold__body" data-mb-links>';
    for ($i = 0; $i < MICROBLOG_MAX_LINKS; $i++) {
        $url = trim((string) ($linkUrls[$i] ?? ''));
        $html .= '<div class="mb-fold__row" data-mb-link-row' . ($i > 0 && $url === '' && $i >= max(1, $filledLinks) ? ' hidden' : '') . '>'
            . '<div class="upload-field"><label for="mb-lurl-' . $e($uid) . '-' . $i . '">Enlace ' . ($i + 1) . '</label><input id="mb-lurl-' . $e($uid) . '-' . $i . '" name="link_url[]" type="url" maxlength="500" value="' . $e($url) . '" placeholder="https://…"></div>'
            . '<div class="upload-field"><label for="mb-ltitle-' . $e($uid) . '-' . $i . '">Título (opcional)</label><input id="mb-ltitle-' . $e($uid) . '-' . $i . '" name="link_title[]" type="text" maxlength="120" value="' . $e((string) ($linkTitles[$i] ?? '')) . '"></div></div>';
    }
    $html .= '<button type="button" class="mb-fold__add" data-mb-add-link>＋ Añadir otro enlace</button></div></details>';

    // Imágenes.
    $existing = $post['images'] ?? [];
    $html .= '<details class="mb-fold"' . ($existing ? ' open' : '') . '><summary>Imágenes</summary><div class="mb-fold__body">';
    if ($existing) {
        $html .= '<div class="mb-existing">';
        foreach ($existing as $image) {
            $html .= '<label class="mb-existing__item"><img src="' . $e(microblog_image_url($image['file'])) . '" alt="' . $e($image['alt']) . '" loading="lazy">'
                . '<span><input type="checkbox" name="remove_images[]" value="' . $e($image['file']) . '"> Quitar</span></label>';
        }
        $html .= '</div>';
    }
    $html .= '<div class="upload-field"><label for="mb-images-' . $e($uid) . '">' . ($existing ? 'Añadir imágenes' : 'Imágenes') . '</label>'
        . '<input id="mb-images-' . $e($uid) . '" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple data-mb-images data-mb-max="' . (MICROBLOG_MAX_IMAGES - count($existing)) . '">'
        . '<small class="upload-help">Hasta ' . MICROBLOG_MAX_IMAGES . ' por post · JPG, PNG o WebP · máximo 15 MB cada una. Se guardan aparte de la galería, reducidas y sin datos de ubicación.</small></div>'
        . '<div class="mb-previews" data-mb-previews></div></div></details>';

    $html .= '<div class="upload-actions"><button class="upload-submit" type="submit">' . ($post !== null ? 'Guardar cambios' : ($isReply ? 'Publicar respuesta' : 'Publicar')) . '</button>'
        . ($post !== null || $parent !== '' ? '<a class="upload-logout" href="/admin.php?microblog=1">Cancelar</a>' : '') . '</div></form>';
    return $html;
}

/** Miniatura-resumen de un post para la lista del panel. */
function admin_microblog_summary(array $post, string $csrf): string
{
    $e = 'uploadEscape';
    $thumbs = '';
    foreach (array_slice($post['images'], 0, 4) as $image) $thumbs .= '<img src="' . $e(microblog_image_url($image['file'])) . '" alt="" loading="lazy">';
    $kinds = [];
    if ($post['quote']) $kinds[] = 'cita';
    if ($post['links']) $kinds[] = count($post['links']) . ' enlace' . (count($post['links']) > 1 ? 's' : '');
    $levelNames = microblog_levels();
    $isReply = $post['parent'] !== null;
    $scheduled = microblog_is_scheduled($post);
    return '<div class="mb-row' . ($isReply ? ' mb-row--reply' : '') . ($scheduled ? ' is-scheduled' : '') . '">'
        . '<div class="mb-row__main"><div class="mb-row__meta">'
        . ($isReply ? '<span class="mb-chip">Respuesta</span>' : '<span class="mb-chip mb-chip--l' . $post['level'] . '">' . $e($levelNames[$post['level']]) . '</span>')
        . ($post['pinned'] ? '<span class="mb-chip mb-chip--pin">Fijado</span>' : '')
        . ($scheduled ? '<span class="mb-chip mb-chip--scheduled">Programado</span>' : '')
        . '<time datetime="' . microblog_format_time('c', $post['created']) . '">' . $e(microblog_date($post['created'])) . ' · ' . microblog_format_time('H:i', $post['created']) . '</time>'
        . ($kinds ? '<span>' . $e(implode(' · ', $kinds)) . '</span>' : '') . '</div>'
        . '<p class="mb-row__text">' . $e(microblog_excerpt($post, 200)) . '</p>'
        . ($thumbs ? '<div class="mb-row__thumbs">' . $thumbs . '</div>' : '') . '</div>'
        . '<div class="mb-row__actions">'
        . ($scheduled ? '' : '<a href="' . $e(microblog_permalink($post)) . '" target="_blank" rel="noopener">Ver</a>')
        . '<a href="/admin.php?microblog=1&amp;edit=' . $e($post['id']) . '">Editar</a>'
        . (!$isReply ? '<a href="/admin.php?microblog=1&amp;reply=' . $e($post['id']) . '">Responder</a>' : '')
        . '<form method="post" action="/admin.php?microblog=1" onsubmit="return confirm(\'' . ($isReply ? '¿Eliminar esta respuesta?' : '¿Eliminar este post y todas sus respuestas?') . '\')">'
        . '<input type="hidden" name="action" value="microblog_delete"><input type="hidden" name="csrf" value="' . $e($csrf) . '"><input type="hidden" name="id" value="' . $e($post['id']) . '">'
        . '<button type="submit" class="is-danger">Eliminar</button></form></div></div>';
}

/**
 * Página del microblog en el panel y fin de la petición.
 *
 * @param array $context 'error' (mensaje), 'values' (campos enviados), 'edit' (id) o 'reply' (id).
 */
function admin_microblog_page(string $csrf, array $context = []): void
{
    $e = 'uploadEscape';
    $settings = site_settings_load();
    $edit = (string) ($context['edit'] ?? ($_GET['edit'] ?? ''));
    $reply = (string) ($context['reply'] ?? ($_GET['reply'] ?? ''));
    $values = is_array($context['values'] ?? null) ? $context['values'] : [];
    $notice = isset($context['error'])
        ? '<p class="upload-message upload-message--error" role="alert">' . $e((string) $context['error']) . '</p>'
        : (isset($_GET['status']) ? '<p class="upload-message" role="status">' . $e((string) $_GET['status']) . '</p>' : '');

    $content = adminNavigation($csrf) . $notice
        . '<link rel="stylesheet" href="/assets/css/microblog-admin.css?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/css/microblog-admin.css') ?: '0', 0, 12) . '">'
        . '<script src="/assets/js/microblog-admin.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/microblog-admin.js') ?: '0', 0, 12) . '" defer></script>';

    $status = empty($settings['show_microblog'])
        ? '<p class="upload-message">El microblog está <strong>oculto</strong> en la web. Actívalo en <a href="/admin.php?settings=1&amp;section=publication">Ajustes → Publicación</a>.</p>' : '';

    $editing = $edit !== '' ? microblog_find($edit) : null;
    if ($editing !== null) {
        $content .= $status . '<section class="admin-card"><header class="admin-card__head"><h2>Editar ' . ($editing['parent'] !== null ? 'respuesta' : 'post') . '</h2>'
            . '<p>Los cambios se ven en la web al guardar.</p></header><div class="admin-card__body">' . admin_microblog_form($csrf, $editing, $values) . '</div></section>';
        uploadPage('Microblog', $content, empty($context['error']) ? 200 : 422, true);
    }

    $replying = $reply !== '' ? microblog_find($reply) : null;
    if ($replying !== null && $replying['parent'] === null) {
        $content .= $status . '<section class="admin-card"><header class="admin-card__head"><h2>Responder en el hilo</h2><p>Solo tú puedes responder; la respuesta se añade al final del hilo.</p></header>'
            . '<div class="admin-card__body"><div class="mb-context">' . admin_microblog_summary($replying, $csrf) . '</div>'
            . admin_microblog_form($csrf, null, $values, $reply, true) . '</div></section>';
        uploadPage('Microblog', $content, empty($context['error']) ? 200 : 422, true);
    }

    // Lista de hilos: cada raíz con sus respuestas debajo.
    $list = '';
    foreach (microblog_repository()->threads(60) as $thread) {
        $list .= '<div class="mb-thread-admin">' . admin_microblog_summary($thread['root'], $csrf);
        foreach ($thread['replies'] as $answer) $list .= admin_microblog_summary($answer, $csrf);
        $list .= '</div>';
    }
    $content .= $status
        . '<section class="admin-card" id="nuevo"><header class="admin-card__head"><h2>Nuevo post</h2>'
        . '<p>Texto, cita, enlaces e imágenes, combinados como quieras. Aparece en la portada en el bloque «Microblog».</p></header>'
        . '<div class="admin-card__body">' . admin_microblog_form($csrf, null, $values, '', isset($_GET['new'])) . '</div></section>'
        . '<section class="admin-card"><header class="admin-card__head"><h2>Tus posts</h2><p>'
        . ($list === '' ? 'Todavía no has publicado nada.' : 'Del más reciente al más antiguo. Las respuestas cuelgan de su post.') . '</p></header>'
        . ($list === '' ? '' : '<div class="admin-card__body mb-admin-list">' . $list . '</div>') . '</section>';
    uploadPage('Microblog', $content, empty($context['error']) ? 200 : 422, true);
}
