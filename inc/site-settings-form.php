<?php
declare(strict_types=1);

use Kcg\Core\Settings\Schema;

/**
 * Formularios del panel de administración para la configuración del sitio.
 *
 * Se carga desde inc/site-settings.php. Aquí solo se genera HTML; la validación y el
 * guardado viven en site_settings_validate() y site_settings_save().
 *
 *   site_settings_form()      Secciones «Perfil», «Diseño» y «Textos» (una sola <form>; el CSS muestra la elegida).
 *   site_page_editor_form()   Editor de las páginas de texto (aviso legal, privacidad, cookies,
 * proyecto).
 */

/**
 * Formulario de ajustes. `$section` decide qué parte se ve: 'profile', 'design' o 'texts'.
 *
 * Las secciones agrupan contenido (imágenes de marca), diseño, páginas de texto, redes sociales y publicación (visibilidad).
 *
 * @param array|null $values Valores a mostrar (p. ej. tras un error); null = los guardados.
 */
function site_settings_form(string $csrf, ?array $values = null, string $section = 'profile'): string
{
    $settings = $values ?? site_settings_load();
    if ($section === 'profile') $section = 'content';
    if (!in_array($section, ['content', 'design', 'texts', 'social', 'publication'], true)) $section = 'content';
    $escape = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<form method="post" action="/admin.php?settings=1" class="upload-form site-settings" data-settings-section="' . $escape($section) . '">'
        . '<input type="hidden" name="action" value="save_site_settings">'
        . '<input type="hidden" name="csrf" value="' . $escape($csrf) . '">'
        . '<input type="hidden" name="section" value="' . $escape($section) . '">'
        . '<p>Configura cada parte de la web desde su sección. Los títulos, descripciones y categorías de las fotos se editan en Gestionar fotos.</p>';
    // Diseño y Textos ya son secciones principales del panel: no se repiten en Ajustes.
    $settingsSections = ['content' => 'Contenido', 'social' => 'Redes sociales', 'publication' => 'Publicación'];
    if (!in_array($section, ['design', 'texts'], true)) {
        $settingsNav = '<nav class="admin-subnav settings-subnav" aria-label="Secciones de ajustes">';
        foreach ($settingsSections as $key => $label) {
            $settingsNav .= '<a href="/admin.php?settings=1&amp;section=' . $key . '"' . ($section === $key ? ' class="is-active" aria-current="page"' : '') . '>' . $escape($label) . '</a>';
        }
        $settingsNav .= '</nav>';
        $html .= $settingsNav;
    }
    // Las etiquetas salen del esquema de ajustes (core/Settings/Schema.php).
    $labels = [];
    foreach (['palette', 'verified_badge', 'grid', 'header_mobile', 'header_desktop', 'gallery_mobile', 'gallery_desktop', 'hover', 'pagination_shape', 'gallery_premium'] as $labelKey) {
        $labels[$labelKey] = Schema::label($labelKey);
    }
    $choices = site_design_choices();
    // Las capacidades del diseño determinan qué controles se pueden usar.
    $premiumOn = site_premium_gallery_active($settings) !== null;
    $contract = ['standard' => site_gallery_capabilities(), 'premium' => []];
    foreach (site_premium_gallery_definitions() as $key => $definition) {
        $contract['premium'][$key] = array_values(array_diff(site_gallery_control_keys(), site_premium_ignored_settings($key)));
    }
    $html .= '<script type="application/json" data-gallery-capabilities>'
        . json_encode($contract, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) . '</script>';
    $controlAttr = static fn(string $key): string => ' data-gallery-control="' . $key . '"'
        . (site_gallery_control_enabled($key, $settings) ? '' : ' disabled');
    $help = static fn(string $key): string => '<small class="upload-help" data-gallery-help="' . $key . '"'
        . (site_gallery_control_help($key, $settings) === '' ? ' hidden' : '') . '>'
        . $escape(site_gallery_control_help($key, $settings)) . '</small>';
    $select = static function (string $key, string $label, array $options, $current, bool $galleryOption = false) use ($escape, $controlAttr, $settings, $help): string {
        $out = '<div class="upload-field' . ($galleryOption && !site_gallery_control_enabled($key, $settings) ? ' is-disabled' : '') . '"' . ($key === 'grid' ? ' data-grid-setting' : '') . '><label for="setting-' . $key . '">' . $escape($label) . '</label><select id="setting-' . $key . '" name="' . $key . '"'
            . ($galleryOption ? $controlAttr($key) : '') . '>';
        foreach ($options as $value => $text) $out .= '<option value="' . $escape((string) $value) . '"' . ((string) $current === (string) $value ? ' selected' : '') . '>' . $escape((string) $text) . '</option>';
        return $out . '</select>' . ($galleryOption ? $help($key) : '') . '</div>';
    };
    $switch = static fn(string $key, string $label, bool $on): string => '<label class="site-section-switch"><input type="checkbox" name="' . $key . '" value="1"' . ($on ? ' checked' : '') . '><span class="site-section-switch__track" aria-hidden="true"></span><span>' . $escape($label) . '</span></label>';
    $card = static fn(string $title, string $lead, string $body): string => '<section class="admin-card"><header class="admin-card__head"><h2>' . $escape($title) . '</h2><p>' . $escape($lead) . '</p></header><div class="admin-card__body">' . $body . '</div></section>';
    $range = static function (string $key, string $label, array $values, $current, string $zero = '', bool $galleryOption = false) use ($escape, $controlAttr, $settings, $help): string {
        $options = [];
        foreach ($values as $n) $options[$n] = $n === 0 ? $zero : (string) $n;
        return '<div class="upload-field' . ($galleryOption && !site_gallery_control_enabled($key, $settings) ? ' is-disabled' : '') . '"><label for="setting-' . $key . '">' . $escape($label) . '</label><select id="setting-' . $key . '" name="' . $key . '"'
            . ($galleryOption ? $controlAttr($key) : '') . '>'
            . implode('', array_map(static fn($n) => '<option value="' . $n . '"' . ((int) $current === $n ? ' selected' : '') . '>' . $escape($options[$n]) . '</option>', $values)) . '</select>' . ($galleryOption ? $help($key) : '') . '</div>';
    };

    // ── Diseño ────────────────────────────────────────────────────────────
    $html .= '<div class="settings-design">';
    // Tarjeta «Apariencia»: paleta y efecto hover.
    $paletteCards = '<fieldset class="palette-picker"><legend>' . $escape($labels['palette']) . '</legend>';
    foreach ($choices['palette'] as $key => $name) {
        [$swatchBg, $swatchFg, $swatchAccent] = \Kcg\Themes\Palettes::swatch($key);
        $paletteCards .= '<label class="palette-card"><input type="radio" name="palette" value="' . $escape($key) . '"' . ($settings['palette'] === $key ? ' checked' : '') . '>'
            . '<span class="palette-card__swatch" style="--sw-bg:' . $escape($swatchBg) . ';--sw-fg:' . $escape($swatchFg) . ';--sw-accent:' . $escape($swatchAccent) . '" aria-hidden="true"><i></i><i></i><i></i></span>'
            . '<span class="palette-card__name">' . $escape($name) . '</span></label>';
    }
    $paletteCards .= '</fieldset>';
    $html .= $card('Apariencia', 'Colores y efecto al pasar el ratón por las fotos.',
        $paletteCards . '<div class="admin-fields">' . $select('hover', $labels['hover'], $choices['hover'], $settings['hover']) . '</div>');
    $fontCards = '<div class="font-picker" data-font-picker><div class="font-picker__filters" role="group" aria-label="Filtrar combinaciones">';
    foreach (['all' => 'Todas'] + \Kcg\Themes\Fonts::GROUPS as $group => $label) {
        $fontCards .= '<button type="button" data-font-filter="' . $group . '"' . ($group === 'all' ? ' class="is-active" aria-pressed="true"' : ' aria-pressed="false"') . '>' . $escape($label) . '</button>';
    }
    $fontCards .= '</div><div class="font-picker__status"><p class="font-picker__count" data-font-count aria-live="polite">' . count(site_font_pairs()) . ' combinaciones disponibles</p>'
        . '<p class="font-picker__selected" data-font-selected>Elegida: ' . $escape(site_font_pair($settings['font_pair'])['name']) . '</p></div>'
        . '<fieldset class="font-picker__grid"><legend class="sr-only">Pareja de tipografías de la web</legend>';
    foreach (site_font_pairs() as $key => $pair) {
        $heading = $escape($pair['heading']);
        $body = $escape($pair['body']);
        $fontCards .= '<label class="font-pair-card" data-font-category="' . $pair['group'] . '" style="--preview-heading:\'' . $heading . '\';--preview-body:\'' . $body . '\'">'
            . '<input type="radio" name="font_pair" value="' . $escape($key) . '"' . ($settings['font_pair'] === $key ? ' checked' : '') . '>'
            . '<span class="font-pair-card__top"><strong>' . $escape($pair['name']) . '</strong><span class="font-pair-card__check" aria-hidden="true">✓</span></span>'
            . '<span class="font-pair-card__heading">La luz cuenta historias</span>'
            . '<span class="font-pair-card__body">Una colección de imágenes para mirar sin prisa.</span>'
            . '<span class="font-pair-card__names">Títulos: ' . $heading . ' · Texto: ' . $body . '</span></label>';
    }
    $fontCards .= '</fieldset></div><p class="upload-help">Vista previa con las fuentes reales. Se aplican a todas las páginas y a los diseños de móvil y escritorio después de guardar.</p>';
    $html .= $card('Tipografías', 'Elige una pareja para los títulos y el resto del texto. Las fuentes se sirven desde tu propia web.', $fontCards);
    // Tarjeta «Cabecera»: estilo y visibilidad independientes en móvil y escritorio.
    $html .= $card('Cabecera', 'Elige el estilo y decide si se muestra en cada tipo de pantalla.',
        '<div class="admin-fields">' . $select('header_mobile', $labels['header_mobile'], $choices['header_mobile'], $settings['header_mobile']) . $select('header_desktop', $labels['header_desktop'], $choices['header_desktop'], $settings['header_desktop']) . '</div>'
        . '<div class="admin-switches">' . $switch('show_header_mobile', 'Mostrar la cabecera en móvil', !empty($settings['show_header_mobile'])) . $switch('show_header_desktop', 'Mostrar la cabecera en escritorio', !empty($settings['show_header_desktop'])) . '</div>');
    // Tarjeta «Insignia de verificación»: mostrarla u ocultarla y elegir entre las de themes/badges.
    $badgeCards = '<div class="admin-switches">' . $switch('show_verified_badge', 'Mostrar la insignia junto al nombre', !empty($settings['show_verified_badge'])) . '</div>'
        . '<fieldset class="badge-picker"><legend class="sr-only">' . $escape($labels['verified_badge']) . '</legend>';
    foreach ($choices['verified_badge'] as $badgeId => $badgeName) {
        $badgeCards .= '<label class="badge-card"><input type="radio" name="verified_badge" value="' . $escape($badgeId) . '"' . ($settings['verified_badge'] === $badgeId ? ' checked' : '') . '>'
            . '<span class="badge-card__preview">' . site_verified_badge_preview($badgeId) . '</span><span class="badge-card__name">' . $escape($badgeName) . '</span></label>';
    }
    $badgeCards .= '</fieldset><link rel="stylesheet" href="/assets/css/components/verified-badge.css?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/css/components/verified-badge.css'), 0, 12) . '">';
    $html .= $card('Insignia de verificación', 'La marca junto al nombre en la cabecera de perfil del móvil. Toma los colores de la paleta.', $badgeCards);
    // Tarjeta «Galería premium»: diseños completos que sustituyen a la galería estándar (inc/premium-galleries.php).
    // El orden del JS (assets/js/admin-ui.js) desactiva los campos de la tarjeta siguiente al elegir una.
    $premiumList = '';
    foreach (site_premium_gallery_definitions() as $definition) {
        $premiumList .= '<li><strong>' . $escape($definition['label']) . '</strong> · ' . $escape($definition['description']) . '</li>';
    }
    $html .= $card('Galería premium', 'Diseños completos que sustituyen a la galería estándar y se integran con la paleta y las cabeceras.',
        '<div class="admin-fields">' . $select('gallery_premium', $labels['gallery_premium'], $choices['gallery_premium'], $settings['gallery_premium'] ?? 'none') . '</div>'
        . '<ul class="admin-premium-list">' . $premiumList . '</ul>'
        . '<p class="upload-help admin-premium-note" data-premium-note' . ($premiumOn ? '' : ' hidden') . '>Con una galería premium activa se ignoran los ajustes de la galería estándar de la tarjeta siguiente que ella no use, y sus campos se desactivan (algunas galerías, como «Estilo Burbujas», siguen usando «Fotos visibles a la vez»). Sus valores se conservan por si vuelves a la galería estándar.</p>');
    // Tarjeta «Galería estándar»: composición, columnas y fotos visibles a la vez (paginación).
    $columnsMobile = range(1, 4); $columnsDesktop = range(1, 10);
    $html .= $card('Galería estándar', 'Composición, columnas y cuántas fotos se ven a la vez.',
        '<div class="admin-fields" data-premium-fields>' . $select('grid', $labels['grid'], $choices['grid'], $settings['grid'], true)
        . $select('gallery_mobile', $labels['gallery_mobile'], $choices['gallery_mobile'], $settings['gallery_mobile'], true)
        . $select('gallery_desktop', $labels['gallery_desktop'], $choices['gallery_desktop'], $settings['gallery_desktop'], true)
        . $select('pagination_shape', $labels['pagination_shape'], $choices['pagination_shape'], $settings['pagination_shape'], true)
        . $range('columns_mobile', Schema::label('columns_mobile'), $columnsMobile, $settings['columns_mobile'], '', true)
        . $range('columns_desktop', Schema::label('columns_desktop'), $columnsDesktop, $settings['columns_desktop'], '', true)
        . $range('photos_mobile', Schema::label('photos_mobile'), site_photos_per_view_choices(), $settings['photos_mobile'], 'Todas', true)
        . $range('photos_desktop', Schema::label('photos_desktop'), site_photos_per_view_choices(), $settings['photos_desktop'], 'Todas', true) . '</div>'
        . '<p class="upload-help" data-grid-help' . (site_grid_setting_is_used($settings) ? ' hidden' : '') . '>La proporción solo se aplica a Cuadrícula y Hoja de contactos. Los demás diseños conservan sus proporciones propias.</p>'
        . '<p class="upload-help admin-grid-explanation">Las fotos visibles a la vez no dependen de las columnas: si hay más fotos, se paginan. Elige «Todas» para mostrarlas juntas. Masonry conserva las proporciones originales; para fotos cuadradas, horizontales o verticales, elige la galería «Cuadrícula» y su tipo de cuadrícula.</p>');

    $order = site_section_order_normalize($settings['section_order'] ?? null);
    // Tarjeta «Estructura de la página»: orden (flechas, ver assets/js/admin-ui.js) y visibilidad de cada bloque.
    // El orden del DOM es el que se envía en section_order[].
    $definitions = site_section_definitions();
    $list = '<ol class="section-order" data-section-order>';
    $publicationControls = '<div class="admin-switches settings-publication-list">';
    foreach ($order as $key) {
        $name = $definitions[$key]['label'];
        $toggle = $definitions[$key]['toggle'];
        $control = '<span class="section-order__fixed">' . ($toggle !== null ? 'Ajustar en Publicación' : 'Siempre visible') . '</span>';
        if ($toggle !== null) $publicationControls .= $switch($toggle, 'Mostrar ' . $name, !empty($settings[$toggle]));
        $list .= '<li class="section-order__item" data-section-key="' . $key . '"><input type="hidden" name="section_order[]" value="' . $key . '">'
            . '<span class="section-order__grip" aria-hidden="true">⋮⋮</span>'
            . '<span class="section-order__text"><strong>' . $escape($name) . '</strong><small>' . $escape($definitions[$key]['hint']) . '</small></span>'
            . '<span class="section-order__control">' . $control . '</span>'
            . '<span class="section-order__move"><button type="button" data-move="up" aria-label="Subir ' . $escape($name) . '">↑</button><button type="button" data-move="down" aria-label="Bajar ' . $escape($name) . '">↓</button></span></li>';
    }
    $list .= '</ol>';
    $publicationControls .= '</div>';
    $socialNames = site_social_networks();
    $socialEditor = '<section class="admin-social-settings" data-social-editor><h3>Perfiles sociales</h3>'
        . '<p>Elige qué perfiles aparecerán en esta sección.</p><div class="social-settings-list" data-social-list>';
    for ($i = 0; $i < 12; $i++) {
        $row = $settings['social_links'][$i] ?? ['network'=>'','url'=>'','handle'=>''];
        $network = (string) ($row['network'] ?? '');
        $url = (string) ($row['url'] ?? '');
        $handle = (string) ($row['handle'] ?? '');
        $filled = trim($network . $url . $handle) !== '';
        $networkLabel = $socialNames[$network] ?? 'red social';
        $socialEditor .= '<div class="social-settings-row" data-social-row data-social-empty="' . ($filled ? 'false' : 'true') . '">'
            . '<div class="social-settings-row__head"><label class="sr-only" for="social-network-' . $i . '">Red social ' . ($i + 1) . '</label>'
            . '<select id="social-network-' . $i . '" name="social_links['.$i.'][network]" aria-label="Red social ' . ($i + 1) . '"><option value="">Seleccionar red social…</option>';
        foreach ($socialNames as $value => $label) $socialEditor .= '<option value="'.$escape($value).'"'.($network === $value ? ' selected' : '').'>'.$escape($label).'</option>';
        $socialEditor .= '</select><button type="button" class="social-settings-row__remove" data-social-remove aria-label="Quitar ' . $escape($networkLabel) . '">Quitar</button></div>'
            . '<div class="social-settings-row__fields"><label class="social-settings-field"><span>Enlace del perfil</span>'
            . '<input type="url" name="social_links['.$i.'][url]" maxlength="500" aria-label="Enlace del perfil de la red social ' . ($i + 1) . '" placeholder="https://…" value="'.$escape($url).'"></label>'
            . '<label class="social-settings-field"><span>Nombre visible</span>'
            . '<input type="text" name="social_links['.$i.'][handle]" maxlength="120" aria-label="Nombre visible del perfil ' . ($i + 1) . '" placeholder="@usuario o nombre" value="'.$escape($handle).'"></label></div></div>';
    }
    $socialEditor .= '</div><div class="social-settings-actions"><button type="button" class="social-settings-add" data-social-add>＋ Añadir red social</button>'
        . '<small class="upload-help">Hasta 12 perfiles. Las filas vacías se muestran al añadirlas.</small></div></section>';
    $socialCopyFields = '<div class="social-settings-copy"><h4>Textos de la sección</h4><div class="social-settings-copy__fields">';
    foreach ([
        'text_bdd8f61b94c52379' => ['Título', 1],
        'text_49a006d539216e16' => ['Subtítulo', 2],
        'text_ab3ceef210237f9d' => ['Texto', 2],
    ] as $key => [$label, $rows]) {
        $entry = site_text_catalog()[$key];
        $value = $settings['texts'][$key] ?? $entry['default'];
        $socialCopyFields .= '<div class="upload-field"><label for="social-copy-' . $escape($key) . '">' . $escape($label) . '</label>'
            . '<textarea id="social-copy-' . $escape($key) . '" name="texts[' . $escape($key) . ']" rows="' . $rows . '" maxlength="20000">'
            . $escape((string) $value) . '</textarea></div>';
    }
    $socialEditor .= $socialCopyFields . '</div></div></section>';
    $microblogCopy = '<div class="social-settings-copy__fields">';
    foreach (['text_6d1f0b7e2a4c9853' => 'Rótulo pequeño', 'text_b3e82f5a7c10d946' => 'Título'] as $key => $label) {
        $value = $settings['texts'][$key] ?? site_text_catalog()[$key]['default'];
        $microblogCopy .= '<div class="upload-field"><label for="microblog-copy-' . $escape($key) . '">' . $escape($label) . '</label>'
            . '<input id="microblog-copy-' . $escape($key) . '" name="texts[' . $escape($key) . ']" type="text" maxlength="200" value="' . $escape((string) $value) . '"></div>';
    }
    $microblogCopy .= '</div><p class="upload-help">También puedes editarlos pulsando sobre ellos en la propia web con el lápiz de «Editar página».</p>';
    $html .= $card('Estructura de la página', 'Ordena los bloques de la portada y cabeceras.', $list);
    $html .= '</div><div class="settings-publication">'
        . $card('Visibilidad de la página', 'Elige qué bloques se muestran en la web. Los cambios se aplican al guardar.', $publicationControls)
        . $card('Textos del microblog', 'Rótulo y título que encabezan el feed en la portada.', $microblogCopy)
        . '</div><div class="settings-social">'
        . $card('Redes Sociales', 'Configura los perfiles y los textos que aparecen en esa sección.', $socialEditor)
        . '</div><div class="settings-texts">';
    $pages = '<div class="page-editor-options__grid">';
    foreach (site_page_labels() as $key => $label) {
        $pages .= '<a class="page-editor-option" href="/admin.php?settings=1&amp;section=page&amp;doc=' . $key . '"><span>' . $escape($label) . '</span><span aria-hidden="true">Editar →</span></a>';
    }
    if ($section === 'texts') {
        $adminAreaName = (string) ($settings['texts']['admin_area_name'] ?? site_text_catalog()['admin_area_name']['default']);
        $adminAreaField = '<div class="upload-field"><label for="admin-area-name">Nombre visible</label>'
            . '<input id="admin-area-name" name="texts[admin_area_name]" type="text" maxlength="120" autocomplete="organization" value="' . $escape($adminAreaName) . '">'
            . '<small class="upload-help">Aparece después de «Área privada ·» en el panel de administración.</small></div>';
        $siteName = site_title();
        $siteNameField = '<div class="upload-field"><label for="site-name">Nombre de la web</label>'
            . '<input id="site-name" name="texts[text_52179dc42df7efe5]" type="text" maxlength="80" autocomplete="off" value="' . $escape($siteName) . '">'
            . '<small class="upload-help">Es uno solo y se ve en el título grande de la portada (la primera palabra arriba y el resto debajo), en el pie de página, en el panel de administración y en el título de las pestañas. Puedes cambiarlo aquí o pulsándolo en la propia web con «Editar página»: se actualiza en todos los sitios.</small></div>';
        $html .= $card('Nombre de la web', 'El nombre que identifica a tu web en todas partes.', $siteNameField);
        $projectKey = 'text_c9248cc61aa75376';
        $projectField = '<div class="upload-field"><label for="project-label">Rótulo de la sección</label>'
            . '<input id="project-label" name="texts[' . $projectKey . ']" type="text" maxlength="80" autocomplete="off" value="' . $escape((string) ($settings['texts'][$projectKey] ?? site_text_catalog()[$projectKey]['default'])) . '">'
            . '<small class="upload-help">El texto pequeño «El proyecto» que encabeza esa sección de la portada. También puedes cambiarlo pulsando sobre él en la propia web con el lápiz de «Editar página».</small></div>';
        $titleKey = 'text_6636c29ab23b6ec8';
        $projectField .= '<div class="upload-field"><label for="project-title">Título de la sección</label>'
            . '<input id="project-title" name="texts[' . $titleKey . ']" type="text" maxlength="120" autocomplete="off" value="' . $escape((string) ($settings['texts'][$titleKey] ?? site_text_catalog()[$titleKey]['default'])) . '">'
            . '<small class="upload-help">El título grande de esa sección. Es un solo texto; también se cambia desde la web con el lápiz.</small></div>';
        $html .= $card('Sección «El proyecto»', 'El rótulo y el título de la sección; el contenido se edita en «Páginas de texto».', $projectField);
        $html .= $card('Identidad del área privada', 'Personaliza el nombre del propietario, fotógrafo o estudio.', $adminAreaField);
        $html .= $card('Páginas de texto', 'Edita el contenido de «El proyecto», aviso legal, privacidad y cookies.', $pages . '</div>');
    }
    $html .= '</div>';
    $html = str_replace('<form method="post"', '<form method="post" enctype="multipart/form-data"', $html);
    if ($section === 'content') {
    // ── Contenido: imágenes de marca ───────────────────────────────────────
    $html .= '<div class="settings-content"><section class="admin-card"><header class="admin-card__head"><h2>Imágenes</h2><p>Foto de perfil, logo, icono y imagen para redes. Cada una indica su proporción de recorte.</p></header><div class="admin-card__body">';
    // Imagen que hay ahora en cada hueco (la subida sustituye a la actual; sin subir nada se conserva).
    $current = static function (string $url, string $caption, bool $wide = false) use ($escape): string {
        return '<div class="settings-image-current' . ($wide ? ' settings-image-current--wide' : '') . '">'
            . ($url !== '' ? '<img src="' . $escape($url) . '" alt="" loading="lazy">' : '<span class="settings-image-current__empty" aria-hidden="true">—</span>')
            . '<span>' . $escape($caption) . '</span></div>';
    };
    $isOwn = static fn(string $url): bool => $url !== '' && !in_array($url, ['/profile-placeholder.svg', '/favicon.svg'], true) && is_file(dirname(__DIR__) . strtok($url, '?'));
    $profileUrl = site_media_src('profile_image');
    $logoUrl = site_media_src('logo_image');
    $iconCustom = site_brand_has_custom_icon();
    $ogUrl = site_brand_og_url();
    $html .= '<input type="hidden" name="profile_image" value="' . $escape((string) $settings['profile_image']) . '">'
        . '<input type="hidden" name="logo_image" value="' . $escape((string) $settings['logo_image']) . '">'
        . '<div class="upload-field"><label for="profile-image">Foto de perfil</label>'
        . '<input id="profile-image" name="profile_image_file" type="file" data-camera="user" data-crop-ratio="0.8" data-crop-label="4:5 (vertical)" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">'
        . $current($profileUrl, $isOwn($profileUrl) ? 'Imagen actual' : 'Sin foto propia: se usa la predeterminada')
        . '<small class="upload-help"><strong>Proporción 4:5 (vertical)</strong>, la del marco de la sección «El proyecto»; por ejemplo 800 × 1000 px. Si la imagen no la tiene, se abre un recortador. JPG, PNG o WebP. Máximo 15 MB.</small></div>'
        . '<div class="upload-field"><label for="logo-image">Logo</label>'
        . '<input id="logo-image" name="logo_image_file" type="file" data-crop-ratio="1" data-crop-label="1:1 (cuadrada)" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">'
        . $current($logoUrl, $isOwn($logoUrl) ? 'Imagen actual' : 'Sin logo propio: se usa el predeterminado')
        . '<small class="upload-help"><strong>Proporción 1:1 (cuadrada)</strong>; por ejemplo 512 × 512 px. Si la imagen no es cuadrada, se abre un recortador. JPG, PNG o WebP. Máximo 15 MB.</small></div>'
        . '<div class="upload-field"><label for="app-icon-file">Icono de la aplicación y favicon</label>'
        . '<input id="app-icon-file" name="app_icon_file" type="file" data-crop-ratio="1" data-crop-min="512" data-crop-label="1:1 (cuadrada)" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">'
        . $current(site_brand_icon_url(192), $iconCustom ? 'Icono actual' : 'Sin icono propio: se usa el predeterminado')
        . '<small class="upload-help"><strong>Proporción 1:1 (cuadrada)</strong>, mínimo 512 × 512 px. Si no es cuadrada, se abre un recortador. Se usa también como favicon y se adapta automáticamente a 32, 180, 192 y 512 px para favicon, Apple Touch y la app instalable. Máximo 15 MB.</small></div>'
        . '<div class="upload-field"><label for="og-image-file">Imagen para compartir en redes sociales (Open Graph)</label>'
        . '<input id="og-image-file" name="og_image_file" type="file" data-crop-ratio="1.905" data-crop-label="1,91:1 (apaisada, 1200 × 630)" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">'
        . $current($ogUrl, $ogUrl !== '' ? 'Imagen actual' : 'Sin imagen propia: se usa la primera foto de la galería', true)
        . '<small class="upload-help"><strong>Proporción 1,91:1 (apaisada)</strong>, la que usan Facebook, WhatsApp, X o LinkedIn al compartir; tamaño final 1200 × 630 px (mínimo 600 × 315). Si no la tiene, se abre un recortador. JPG, PNG o WebP. Máximo 15 MB.</small></div></div></section></div>';
    // Recortador: si la imagen elegida no tiene la proporción del hueco (cuadrada, o 1,91:1 la de redes), se encuadra antes de enviarla.
    $html .= '<link rel="stylesheet" href="/assets/css/image-cropper.css?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/css/image-cropper.css'), 0, 12) . '">'
        . '<script src="/assets/js/camera-input.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/camera-input.js'), 0, 12) . '" defer></script>'
        . '<script src="/assets/js/image-cropper.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/image-cropper.js'), 0, 12) . '" defer></script>';
    }
    $html .= '<div class="admin-savebar"><span class="admin-savebar__hint">Los cambios se aplican a la web al guardar.</span><button class="upload-submit" type="submit">Guardar configuración</button></div></form>';
    if ($section === 'design') {
        $html .= '<link rel="stylesheet" href="/assets/css/font-picker.css?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/css/font-picker.css'), 0, 12) . '">'
            . '<script src="/assets/js/font-picker.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/font-picker.js'), 0, 12) . '" defer></script>';
    }
    return $html;
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
        // Editor Quill (assets/vendor/quill): la barra son controles Quill (clases ql-*) y se ve el estado activo de cada formato.
        . '<div class="page-editor-toolbar" role="toolbar" aria-label="Formato del texto" data-editor-toolbar>'
        . '<div class="page-editor-tool-group" role="group" aria-label="Estilos"><select class="ql-header" aria-label="Estilo de párrafo"><option value="" selected>Párrafo</option><option value="2">Título</option><option value="3">Subtítulo</option><option value="4">Encabezado pequeño</option></select>'
        . '<button type="button" class="ql-blockquote" title="Cita" aria-label="Cita">❝ Cita</button>'
        . '<button type="button" class="ql-bold" title="Negrita" aria-label="Negrita"><strong>N</strong></button>'
        . '<button type="button" class="ql-italic" title="Cursiva" aria-label="Cursiva"><em>C</em></button>'
        . '<button type="button" class="ql-underline" title="Subrayado" aria-label="Subrayado"><u>S</u></button>'
        . '<button type="button" class="ql-strike" title="Tachado" aria-label="Tachado"><s>T</s></button></div>'
        . '<div class="page-editor-tool-group" role="group" aria-label="Listas">'
        . '<button type="button" class="ql-list" value="bullet" title="Lista con viñetas" aria-label="Lista con viñetas">• Lista</button>'
        . '<button type="button" class="ql-list" value="ordered" title="Lista numerada" aria-label="Lista numerada">1. Lista</button></div>'
        . '<div class="page-editor-tool-group" role="group" aria-label="Alineación">'
        . '<button type="button" class="ql-align" value="" title="Alinear a la izquierda" aria-label="Alinear a la izquierda">Izquierda</button>'
        . '<button type="button" class="ql-align" value="center" title="Centrar" aria-label="Centrar">Centrar</button>'
        . '<button type="button" class="ql-align" value="right" title="Alinear a la derecha" aria-label="Alinear a la derecha">Derecha</button></div>'
        . '<div class="page-editor-tool-group" role="group" aria-label="Edición">'
        . '<button type="button" class="ql-link" title="Enlace (pulsa de nuevo para quitarlo)" aria-label="Enlace">Enlace</button>'
        . '<button type="button" data-editor-history="undo" title="Deshacer" aria-label="Deshacer">↶</button>'
        . '<button type="button" data-editor-history="redo" title="Rehacer" aria-label="Rehacer">↷</button>'
        . '<button type="button" class="ql-clean" title="Quitar formato" aria-label="Quitar formato">Limpiar</button></div>'
        . '<button type="button" class="page-editor-preview-toggle" data-editor-preview aria-pressed="false">Vista previa</button></div>'
        . '<div class="page-editor-link" data-link-panel hidden><label for="page-link-url">Dirección del enlace</label><input id="page-link-url" type="text" inputmode="url" placeholder="https://… o /ruta-interna" data-link-url><button type="button" data-link-apply>Insertar enlace</button><button type="button" data-link-cancel>Cancelar</button></div>'
        . '<div class="page-editor-surface" spellcheck="true" aria-label="Contenido editable" data-page-editor></div><div class="page-editor-status"><span data-editor-count aria-live="polite"></span><span>Los enlaces y el formato se revisan al guardar.</span></div>'
        . '<textarea name="content" data-page-content hidden>' . $escape(site_page_content($page)) . '</textarea>'
        . '<div class="upload-actions"><button class="upload-submit" type="submit">Guardar página</button><a class="upload-logout" href="/admin.php?settings=1&amp;section=texts">Volver a Textos</a></div>'
        . '</form><link rel="stylesheet" href="/assets/css/admin-page-editor.css?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/css/admin-page-editor.css'), 0, 12) . '"><link rel="stylesheet" href="/assets/vendor/quill/quill.core.css?v=2.0.3"><script src="/assets/vendor/quill/quill.js?v=2.0.3" defer></script><script src="/assets/js/admin-settings.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/admin-settings.js'), 0, 12) . '" defer></script>';
    return $html;
}

/**
 * Ajustes → Publicación: mantenimiento de las imágenes de la galería. Rehace las versiones WebP (lado mayor 1800 px, calidad 80)
 * de las fotos nuevas o hechas con una receta anterior, por tandas, sin acceso por SSH. Es un formulario aparte del de ajustes.
 */
function site_images_maintenance_card(string $csrf): string
{
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pending = (new ImageProcessor(dirname(__DIR__)))->pendingCount();
    $state = $pending === 0
        ? 'Todas las fotos tienen ya su versión actual.'
        : $pending . ($pending === 1 ? ' foto necesita' : ' fotos necesitan') . ' rehacer su versión optimizada.';
    return '<form method="post" action="/admin.php?settings=1" class="upload-form site-settings site-settings--maintenance">'
        . '<input type="hidden" name="action" value="regenerate_images"><input type="hidden" name="csrf" value="' . $escape($csrf) . '">'
        . '<section class="admin-card"><header class="admin-card__head"><h2>Imágenes de la galería</h2>'
        . '<p>Las fotos se publican en WebP, con el lado mayor de 1800 px como máximo y calidad 80. Tus originales no se tocan.</p></header>'
        . '<div class="admin-card__body"><p class="upload-help">' . $escape($state) . ' Se rehacen de 20 en 20; si quedan más, pulsa otra vez.</p>'
        . '<button class="upload-submit" type="submit">Rehacer imágenes</button></div></section></form>';
}
