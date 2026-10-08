<?php
declare(strict_types=1);

/**
 * Formularios del panel de administración para la configuración del sitio.
 *
 * Se carga desde inc/site-settings.php. Aquí solo se genera HTML; la validación y el
 * guardado viven en site_settings_validate() y site_settings_save().
 *
 *   site_settings_form()      Pestañas «Perfil» y «Diseño» (una sola <form>; el CSS oculta la que no
 * toca).
 *   site_page_editor_form()   Editor de las páginas de texto (aviso legal, privacidad, cookies,
 * proyecto).
 */

/**
 * Formulario de ajustes. `$section` decide qué parte se ve: 'profile' o 'design'.
 *
 * Estructura de la parte «Diseño» (una tarjeta por apartado):
 *   Apariencia · Cabecera · Galería · Estructura de la página · Textos de páginas
 * y de la parte «Perfil»: Perfil · Más redes sociales · Imágenes.
 *
 * @param array|null $values Valores a mostrar (p. ej. tras un error); null = los guardados.
 */
function site_settings_form(string $csrf, ?array $values = null, string $section = 'profile'): string
{
    $settings = $values ?? site_settings_load();
    $escape = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<form method="post" action="/admin.php?settings=1" class="upload-form site-settings" data-settings-section="' . $escape($section) . '">'
        . '<input type="hidden" name="action" value="save_site_settings">'
        . '<input type="hidden" name="csrf" value="' . $escape($csrf) . '">'
        . '<input type="hidden" name="section" value="' . $escape($section) . '">'
        . '<p>Personaliza la web. Los textos de fotografías y categorías se editan en Gestionar fotos. Los campos admiten texto plano.</p>';
    $labels = ['palette' => 'Paleta de colores', 'grid' => 'Tipo de cuadrícula', 'header_mobile' => 'Cabecera móvil', 'header_desktop' => 'Cabecera de escritorio', 'gallery_mobile' => 'Galería móvil', 'gallery_desktop' => 'Galería de escritorio', 'hover' => 'Efecto Hover'];
    $choices = site_design_choices();
    $select = static function (string $key, string $label, array $options, $current) use ($escape): string {
        $out = '<div class="upload-field"><label for="setting-' . $key . '">' . $escape($label) . '</label><select id="setting-' . $key . '" name="' . $key . '">';
        foreach ($options as $value => $text) $out .= '<option value="' . $escape((string) $value) . '"' . ((string) $current === (string) $value ? ' selected' : '') . '>' . $escape((string) $text) . '</option>';
        return $out . '</select></div>';
    };
    $switch = static fn(string $key, string $label, bool $on): string => '<label class="site-section-switch"><input type="checkbox" name="' . $key . '" value="1"' . ($on ? ' checked' : '') . '><span class="site-section-switch__track" aria-hidden="true"></span><span>' . $escape($label) . '</span></label>';
    $card = static fn(string $title, string $lead, string $body): string => '<section class="admin-card"><header class="admin-card__head"><h2>' . $escape($title) . '</h2><p>' . $escape($lead) . '</p></header><div class="admin-card__body">' . $body . '</div></section>';
    $range = static function (string $key, string $label, array $values, $current, string $zero = '') use ($escape): string {
        $options = [];
        foreach ($values as $n) $options[$n] = $n === 0 ? $zero : (string) $n;
        return '<div class="upload-field"><label for="setting-' . $key . '">' . $escape($label) . '</label><select id="setting-' . $key . '" name="' . $key . '">'
            . implode('', array_map(static fn($n) => '<option value="' . $n . '"' . ((int) $current === $n ? ' selected' : '') . '>' . $escape($options[$n]) . '</option>', $values)) . '</select></div>';
    };

    // ── Diseño ────────────────────────────────────────────────────────────
    $html .= '<div class="settings-design">';
    // Tarjeta «Apariencia»: paleta y efecto hover.
    $html .= $card('Apariencia', 'Colores y efecto al pasar el ratón por las fotos.',
        '<div class="admin-fields">' . $select('palette', $labels['palette'], $choices['palette'], $settings['palette']) . $select('hover', $labels['hover'], $choices['hover'], $settings['hover']) . '</div>');
    // Tarjeta «Cabecera»: estilo y visibilidad independientes en móvil y escritorio.
    $html .= $card('Cabecera', 'Elige el estilo y decide si se muestra en cada tipo de pantalla.',
        '<div class="admin-fields">' . $select('header_mobile', $labels['header_mobile'], $choices['header_mobile'], $settings['header_mobile']) . $select('header_desktop', $labels['header_desktop'], $choices['header_desktop'], $settings['header_desktop']) . '</div>'
        . '<div class="admin-switches">' . $switch('show_header_mobile', 'Mostrar la cabecera en móvil', !empty($settings['show_header_mobile'])) . $switch('show_header_desktop', 'Mostrar la cabecera en escritorio', !empty($settings['show_header_desktop'])) . '</div>');
    // Tarjeta «Galería»: composición, columnas y fotos visibles a la vez (paginación).
    $columnsMobile = range(1, 4); $columnsDesktop = range(1, 10);
    $html .= $card('Galería', 'Composición, columnas y cuántas fotos se ven a la vez.',
        '<div class="admin-fields">' . $select('grid', $labels['grid'], $choices['grid'], $settings['grid'])
        . $select('gallery_mobile', $labels['gallery_mobile'], $choices['gallery_mobile'], $settings['gallery_mobile'])
        . $select('gallery_desktop', $labels['gallery_desktop'], $choices['gallery_desktop'], $settings['gallery_desktop'])
        . $range('columns_mobile', 'Columnas en móvil', $columnsMobile, $settings['columns_mobile'])
        . $range('columns_desktop', 'Columnas en escritorio', $columnsDesktop, $settings['columns_desktop'])
        . $range('photos_mobile', 'Fotos visibles a la vez en móvil', site_photos_per_view_choices(), $settings['photos_mobile'], 'Todas')
        . $range('photos_desktop', 'Fotos visibles a la vez en escritorio', site_photos_per_view_choices(), $settings['photos_desktop'], 'Todas') . '</div>'
        . '<p class="upload-help">Las fotos visibles a la vez no dependen de las columnas: si hay más fotos, se paginan. Elige «Todas» para mostrarlas juntas. Masonry conserva las proporciones originales; para fotos cuadradas, horizontales o verticales, elige la galería «Cuadrícula» y su tipo de cuadrícula.</p>');

    $order = site_section_order_normalize($settings['section_order'] ?? null);
    // Tarjeta «Estructura de la página»: orden (flechas, ver assets/js/admin-ui.js) y visibilidad de cada bloque.
    // El orden del DOM es el que se envía en section_order[].
    $definitions = site_section_definitions();
    $list = '<ol class="section-order" data-section-order>';
    foreach ($order as $key) {
        $name = $definitions[$key]['label'];
        $toggle = $definitions[$key]['toggle'];
        $control = $toggle !== null
            ? $switch($toggle, 'Mostrar', !empty($settings[$toggle]))
            : '<span class="section-order__fixed">Siempre visible</span>';
        $list .= '<li class="section-order__item" data-section-key="' . $key . '"><input type="hidden" name="section_order[]" value="' . $key . '">'
            . '<span class="section-order__grip" aria-hidden="true">⋮⋮</span>'
            . '<span class="section-order__text"><strong>' . $escape($name) . '</strong><small>' . $escape($definitions[$key]['hint']) . '</small></span>'
            . '<span class="section-order__control">' . $control . '</span>'
            . '<span class="section-order__move"><button type="button" data-move="up" aria-label="Subir ' . $escape($name) . '">↑</button><button type="button" data-move="down" aria-label="Bajar ' . $escape($name) . '">↓</button></span></li>';
    }
    $list .= '</ol>';
    $html .= $card('Estructura de la página', 'Ordena los bloques de la portada con las flechas y oculta los que no quieras mostrar.', $list);

    // Tarjeta «Textos de páginas»: enlaces al editor de cada página.
    $pages = '<div class="page-editor-options__grid">';
    foreach (site_page_labels() as $key => $label) {
        $pages .= '<a class="page-editor-option" href="/admin.php?settings=1&amp;section=page&amp;doc=' . $key . '"><span>' . $escape($label) . '</span><span aria-hidden="true">Editar →</span></a>';
    }
    $html .= $card('Textos de páginas', 'Abre una página para editar su contenido y formato.', $pages . '</div>') . '</div>';
    $html = str_replace('<form method="post"', '<form method="post" enctype="multipart/form-data"', $html);
    // ── Perfil ────────────────────────────────────────────────────────────
    $html .= '<div class="settings-profile"><section class="admin-card"><header class="admin-card__head"><h2>Perfil</h2><p>Nombre, descripciones y enlaces que aparecen en la cabecera y en los contactos.</p></header><div class="admin-card__body">';
    foreach (site_editable_text_keys() as $key) {
        $entry = $key === 'social_threads_user'
            ? ['default' => '@kookyecatgallery']
            : site_text_catalog()[$key];
        $value = $settings['texts'][$key] ?? $entry['default'];
        $label = site_admin_text_labels()[$key] ?? $entry['default'];
        $html .= '<div class="upload-field"><label for="text-' . $key . '">' . $escape($label) . '</label>'
            . '<textarea id="text-' . $key . '" name="texts[' . $key . ']" rows="2" maxlength="20000">' . $escape($value) . '</textarea></div>';
    }
    $html .= '<div class="upload-field"><label for="instagram-url">Enlace de Instagram</label><input id="instagram-url" name="instagram_url" type="url" maxlength="500" value="' . $escape((string) $settings['instagram_url']) . '"></div>'
        . '<div class="upload-field"><label for="threads-url">Enlace de Threads</label><input id="threads-url" name="threads_url" type="url" maxlength="500" value="' . $escape((string) $settings['threads_url']) . '"></div>';
    $socialNames = ['facebook'=>'Facebook','x'=>'X','youtube'=>'YouTube','tiktok'=>'TikTok','flickr'=>'Flickr','linkedin'=>'LinkedIn','pinterest'=>'Pinterest','500px'=>'500px','bluesky'=>'Bluesky','mastodon'=>'Mastodon'];
    $html .= '</div></section><section class="admin-card"><header class="admin-card__head"><h2>Más redes sociales</h2><p>Rellena solo las redes que quieras mostrar.</p></header><div class="admin-card__body">';
    $html .= '<div class="upload-field"><label>Redes</label><div class="social-settings-list">';
    for ($i = 0; $i < 12; $i++) {
        $row = $settings['social_links'][$i] ?? ['network'=>'','url'=>'','handle'=>''];
        $html .= '<div class="social-settings-row"><select name="social_links['.$i.'][network]"><option value="">Añadir red…</option>';
        foreach ($socialNames as $value => $label) $html .= '<option value="'.$value.'"'.(($row['network'] ?? '') === $value ? ' selected' : '').'>'.$label.'</option>';
        $html .= '</select><input type="url" name="social_links['.$i.'][url]" maxlength="500" placeholder="https://…" value="'.$escape((string)($row['url'] ?? '')).'"><input type="text" name="social_links['.$i.'][handle]" maxlength="120" placeholder="@usuario o nombre" value="'.$escape((string)($row['handle'] ?? '')).'"></div>';
    }
    $html .= '<small class="upload-help">Rellena solo las redes que quieras mostrar. Puedes añadir hasta 12.</small></div></div>';
    $html .= '</div></section><section class="admin-card"><header class="admin-card__head"><h2>Imágenes</h2><p>Foto de perfil y logo de la web.</p></header><div class="admin-card__body">';
    $html .= '<input type="hidden" name="profile_image" value="' . $escape((string) $settings['profile_image']) . '">'
        . '<input type="hidden" name="logo_image" value="' . $escape((string) $settings['logo_image']) . '">'
        . '<div class="upload-field"><label for="profile-image">Foto de perfil</label>'
        . '<input id="profile-image" name="profile_image_file" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">'
        . '<small class="upload-help">JPG, PNG o WebP. Máximo 15 MB.</small></div>'
        . '<div class="upload-field"><label for="logo-image">Logo</label>'
        . '<input id="logo-image" name="logo_image_file" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">'
        . '<small class="upload-help">JPG, PNG o WebP. Máximo 15 MB.</small></div></div></section></div>';
    return $html . '<div class="admin-savebar"><span class="admin-savebar__hint">Los cambios se aplican a la web al guardar.</span><a class="upload-logout" href="/" target="_blank" rel="noopener">Ver la web</a><button class="upload-submit" type="submit">Guardar configuración</button></div></form>';
}

/**
 * Editor visual de una página de texto. El contenido se sanea al guardar
 * (site_sanitize_page_html()); el JS vive en assets/js/admin-settings.js.
 */
function site_page_editor_form(string $csrf, string $page): string
{
    if (!in_array($page, site_editable_page_keys(), true)) throw new InvalidArgumentException('Selecciona una página válida.');
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<form method="post" action="/admin.php" class="upload-form site-page-editor" data-page-editor-form>'
        . '<input type="hidden" name="action" value="save_site_page"><input type="hidden" name="csrf" value="' . $escape($csrf) . '">'
        . '<input type="hidden" name="page" value="' . $escape($page) . '">'
        . '<p class="upload-help">Edita el contenido y usa la vista previa para revisar el resultado antes de guardar.</p>'
        . '<div class="page-editor-toolbar" role="toolbar" aria-label="Formato del texto" data-editor-toolbar>'
        . '<div class="page-editor-tool-group" role="group" aria-label="Estilos"><select data-editor-block aria-label="Estilo de párrafo"><option value="">Párrafo</option><option value="h2">Título</option><option value="h3">Subtítulo</option><option value="h4">Encabezado pequeño</option><option value="blockquote">Cita</option></select>'
        . '<button type="button" data-editor-command="bold" title="Negrita" aria-label="Negrita"><strong>N</strong></button>'
        . '<button type="button" data-editor-command="italic" title="Cursiva" aria-label="Cursiva"><em>C</em></button>'
        . '<button type="button" data-editor-command="underline" title="Subrayado" aria-label="Subrayado"><u>S</u></button>'
        . '<button type="button" data-editor-command="strikeThrough" title="Tachado" aria-label="Tachado"><s>T</s></button></div>'
        . '<div class="page-editor-tool-group" role="group" aria-label="Listas y sangría">'
        . '<button type="button" data-editor-command="insertUnorderedList" title="Lista con viñetas" aria-label="Lista con viñetas">• Lista</button>'
        . '<button type="button" data-editor-command="insertOrderedList" title="Lista numerada" aria-label="Lista numerada">1. Lista</button>'
        . '<button type="button" data-editor-command="outdent" title="Reducir sangría" aria-label="Reducir sangría">←</button>'
        . '<button type="button" data-editor-command="indent" title="Aumentar sangría" aria-label="Aumentar sangría">→</button></div>'
        . '<div class="page-editor-tool-group" role="group" aria-label="Alineación">'
        . '<button type="button" data-editor-command="justifyLeft" title="Alinear a la izquierda" aria-label="Alinear a la izquierda">Izquierda</button>'
        . '<button type="button" data-editor-command="justifyCenter" title="Centrar" aria-label="Centrar">Centrar</button>'
        . '<button type="button" data-editor-command="justifyRight" title="Alinear a la derecha" aria-label="Alinear a la derecha">Derecha</button></div>'
        . '<div class="page-editor-tool-group" role="group" aria-label="Edición">'
        . '<button type="button" data-editor-command="createLink" title="Insertar enlace" aria-label="Insertar enlace">Enlace</button>'
        . '<button type="button" data-editor-command="unlink" title="Quitar enlace" aria-label="Quitar enlace">Quitar enlace</button>'
        . '<button type="button" data-editor-command="undo" title="Deshacer" aria-label="Deshacer">↶</button>'
        . '<button type="button" data-editor-command="redo" title="Rehacer" aria-label="Rehacer">↷</button>'
        . '<button type="button" data-editor-command="removeFormat" title="Quitar formato" aria-label="Quitar formato">Limpiar</button></div>'
        . '<button type="button" class="page-editor-preview-toggle" data-editor-preview aria-pressed="false">Vista previa</button></div>'
        . '<div class="page-editor-link" data-link-panel hidden><label for="page-link-url">Dirección del enlace</label><input id="page-link-url" type="text" inputmode="url" placeholder="https://… o /ruta-interna" data-link-url><button type="button" data-link-apply>Insertar enlace</button><button type="button" data-link-cancel>Cancelar</button></div>'
        . '<div class="page-editor-surface" contenteditable="true" spellcheck="true" role="textbox" aria-multiline="true" aria-label="Contenido editable" data-page-editor>'
        . site_page_content($page) . '</div><div class="page-editor-status"><span data-editor-count aria-live="polite"></span><span>Los enlaces y el formato se revisan al guardar.</span></div>'
        . '<textarea name="content" data-page-content hidden>' . $escape(site_page_content($page)) . '</textarea>'
        . '<div class="upload-actions"><button class="upload-submit" type="submit">Guardar página</button><a class="upload-logout" href="/admin.php?settings=1&amp;section=design">Volver a Textos y diseño</a></div>'
        . '</form><link rel="stylesheet" href="/assets/css/admin-page-editor.css?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/css/admin-page-editor.css'), 0, 12) . '"><script src="/assets/js/admin-settings.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/admin-settings.js'), 0, 12) . '" defer></script>';
    return $html;
}
