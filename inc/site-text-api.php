<?php
declare(strict_types=1);

/**
 * Textos editables del sitio: catálogo, lectura por clave y marcado para la edición en línea. Los textos por defecto están en inc/site-texts.php.
 * (Cargado por inc/site-settings.php.)
 */

/**
 * Claves de texto que se editan en «Textos» o pertenecen a las plantillas elegidas.
 */
function site_editable_text_keys(): array
{
    $keys = [
        'text_52179dc42df7efe5', // nombre de la web: portada, pie de página y panel
        'admin_area_name',
        'text_30170303c506cf6c',
        'text_4577bab0d9627af1',
        'text_2e2b80d871481edc',
        'text_54b7645201863e32',
        'text_7e6debbd1dab03b1',
        'text_bdd8f61b94c52379',
        'text_49a006d539216e16',
        'text_ab3ceef210237f9d',
        'text_6d1f0b7e2a4c9853',
        'text_b3e82f5a7c10d946',
        'text_c9248cc61aa75376', // rótulo de «El proyecto»
        'text_6636c29ab23b6ec8', // título de «El proyecto»
    ];
    foreach (site_template_text_groups() as $group) {
        array_push($keys, ...array_keys($group['fields']));
    }
    return array_values(array_unique($keys));
}

/**
 * Etiqueta que se muestra en el panel para cada texto editable.
 */
function site_admin_text_labels(): array
{
    return [
        'text_52179dc42df7efe5' => 'Nombre de la web',
        'admin_area_name' => 'Nombre que aparece en el área privada',
        'text_30170303c506cf6c' => 'Nombre del fotógrafo o estudio',
        'text_4577bab0d9627af1' => 'Nombre de usuario del perfil',
        'text_2e2b80d871481edc' => 'Descripción principal',
        'text_54b7645201863e32' => 'Descripción secundaria',
        'text_7e6debbd1dab03b1' => 'Web de usuario',
        'text_bdd8f61b94c52379' => 'Título de Redes Sociales',
        'text_49a006d539216e16' => 'Subtítulo de Redes Sociales',
        'text_ab3ceef210237f9d' => 'Texto de Redes Sociales',
        'text_6d1f0b7e2a4c9853' => 'Rótulo pequeño del Microblog',
        'text_b3e82f5a7c10d946' => 'Título del Microblog',
        'text_c9248cc61aa75376' => 'Rótulo de «El proyecto»',
        'text_6636c29ab23b6ec8' => 'Título de «El proyecto»',
    ];
}

/**
 * URL local de la imagen de perfil o del logo; si la ruta no es válida, devuelve la imagen por defecto.
 */
function site_media_url(string $key): string
{
    $settings = site_settings_load();
    $default = (string) \Kcg\Core\Settings\Schema::defaults()[$key];
    $value = (string) ($settings[$key] ?? $default);
    return preg_match('~^/[a-zA-Z0-9._/-]+$~', $value) ? $value : $default;
}

/**
 * El nombre de la web partido en las dos líneas del título de la portada: primera palabra y resto («Fotos de autor» →
 * «Fotos» y «de autor»). Un nombre de una sola palabra ocupa solo la primera línea.
 *
 * @return array{0: string, 1: string}
 */
function site_name_lines(string $name): array
{
    $name = trim((string) preg_replace('/\s+/u', ' ', $name));
    $parts = explode(' ', $name, 2);
    return [$parts[0], $parts[1] ?? ''];
}

/**
 * Un trozo del nombre de la web (HTML escapado). Con la edición en línea activa es pulsable y edita el nombre completo:
 * el nombre es uno solo y se ve igual en la portada, el pie de página y el panel.
 */
function site_name_part_html(string $part): string
{
    $key = 'text_52179dc42df7efe5';
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (empty($GLOBALS['siteInlineEditorEnabled'])) return $escape($part);
    return '<span class="inline-edit-text" data-inline-edit-key="' . $key . '" data-inline-edit-label="' . $escape(site_inline_text_label($key))
        . '" data-inline-edit-value="' . $escape(site_text($key)) . '">' . $escape($part) . '</span>';
}

/**
 * Igual que site_media_url() pero con ?v=fecha del archivo, para el atributo src: al subir otra imagen con el mismo nombre
 * el navegador (o la caché del hosting) no sigue mostrando la anterior.
 */
function site_media_src(string $key): string
{
    $url = site_media_url($key);
    $file = dirname(__DIR__) . $url;
    return $url . (is_file($file) ? '?v=' . (string) filemtime($file) : '');
}

/**
 * Catálogo de textos con los valores por defecto del núcleo (inc/site-texts.php), sin los de la instalación.
 */
function site_text_core_catalog(): array
{
    static $catalog;
    return $catalog ??= require __DIR__ . '/site-texts.php';
}

/**
 * Catálogo de textos con su valor por defecto: el del núcleo o, si la instalación lo cambia, el suyo (installation.php).
 */
function site_text_catalog(): array
{
    static $catalog;
    if ($catalog !== null) return $catalog;
    $catalog = site_text_core_catalog();
    // Los textos iniciales propios de la instalación (installation.php) sustituyen a los del núcleo.
    foreach (\Kcg\Core\Installation::textDefaults() as $key => $value) {
        if (isset($catalog[$key])) $catalog[$key]['default'] = (string) $value;
    }
    return $catalog;
}

/**
 * Texto editable por clave: el guardado en los ajustes o, si no hay, el valor por defecto del catálogo.
 */
function site_text(string $key): string
{
    static $settings;
    $settings ??= site_settings_load();
    if (isset($settings['texts'][$key])) return $settings['texts'][$key];
    // El nombre público sigue el mismo resolver que el panel y la marca móvil.
    if ($key === 'text_52179dc42df7efe5') return site_title();
    $default = site_text_catalog()[$key]['default'] ?? '';
    // Los textos por defecto que mencionan el título siguen el nombre actual del sitio.
    return str_replace(site_title_default(), site_title(), $default);
}

/**
 * Título original de la web: el valor por defecto del texto «nombre del sitio» del catálogo.
 */
function site_title_default(): string
{
    return site_text_catalog()['text_52179dc42df7efe5']['default'];
}

/**
 * Nombre de la web: el texto «Nombre de la web» (Ajustes → Textos, o la edición en la propia página) o, si no hay, el del núcleo.
 * Es el título grande de la portada, el pie, el <title>, los textos accesibles y la marca del panel: un único sitio donde se guarda.
 */
function site_title(): string
{
    $settings = site_settings_load();
    $name = trim((string) ($settings['texts']['text_52179dc42df7efe5'] ?? ''));
    return $name !== '' ? $name : site_title_default();
}

/**
 * Igual que site_text(), escapado para usarlo dentro de HTML.
 */
function site_text_html(string $key): string
{
    return htmlspecialchars(site_text($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_inline_editable_text_keys(): array
{
    return site_editable_text_keys();
}

function site_inline_text_values(): array
{
    $values = [];
    foreach (site_inline_editable_text_keys() as $key) {
        $values[$key] = ['value' => site_text($key), 'label' => site_inline_text_label($key)];
    }
    return $values;
}

function site_inline_text_label(string $key): string
{
    $labels = site_admin_text_labels();
    if (isset($labels[$key])) return $labels[$key];
    foreach (site_template_text_groups() as $group) {
        if (isset($group['fields'][$key])) return $group['fields'][$key];
    }
    return (string) (site_text_catalog()[$key]['group'] ?? 'Texto');
}

function site_inline_text_html(string $key, bool $preserveLineBreaks = false): string
{
    $raw = site_text($key);
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $value = $preserveLineBreaks ? nl2br($escape($raw), false) : $escape($raw);
    if (empty($GLOBALS['siteInlineEditorEnabled']) || !in_array($key, site_inline_editable_text_keys(), true)) return $value;
    return '<span class="inline-edit-text" data-inline-edit-key="' . $escape($key) . '" data-inline-edit-label="' . $escape(site_inline_text_label($key)) . '">' . $value . '</span>';
}
