<?php
declare(strict_types=1);

/**
 * Configuración del sitio: ajustes de diseño, bloques de la portada, textos y páginas editables.
 *
 * Índice:
 *   1. Almacenamiento y opciones de diseño
 *   2. Bloques de la portada (orden y visibilidad)
 *   3. Valores por defecto y textos editables
 *   4. Validación y guardado
 *   5. Textos del sitio
 *   6. Páginas de texto editables
 *   7. Salida para la web pública
 *   (formularios del panel: inc/site-settings-form.php)
 *   (galerías premium: inc/premium-galleries.php)
 */

require_once __DIR__ . '/private-storage.php';
require_once __DIR__ . '/premium-galleries.php';
require_once __DIR__ . '/template-texts.php';
require_once __DIR__ . '/site-brand-assets.php';

// =============================================================================
// 1. ALMACENAMIENTO Y OPCIONES DE DISEÑO
// =============================================================================

/**
 * Ruta del JSON de configuración. Está fuera del directorio público y de los archivos desplegados
 * (como upload-auth.php), así que un despliegue no la pisa.
 */
function site_settings_path(): string
{
    return rtrim(gallery_private_directory(), '/\\') . DIRECTORY_SEPARATOR . 'site-settings.json';
}

/**
 * Opciones permitidas para cada ajuste de diseño: clave => [valor => etiqueta]. Es la lista blanca
 * que usa site_settings_validate().
 */
function site_design_choices(): array
{
    return [
        'palette' => ['current' => 'Elegante', 'black' => 'Noche · negro y blanco', 'white' => 'Luz · blanco y tinta', 'cyberpunk' => 'Cyberpunk · neón', 'japanese' => 'Japón · papel y carmesí', 'forest' => 'Bosque · verde y marfil', 'ocean' => 'Océano · azul profundo y turquesa'],
        'grid' => ['adaptive' => 'Adaptativa', 'masonry' => 'Masonry · alturas naturales', 'square' => 'Cuadrada', 'landscape' => 'Horizontal · 4:3', 'portrait' => 'Vertical · 3:4'],
        'header_mobile' => ['current' => 'Perfil social', 'centered' => 'Retrato centrado', 'compact' => 'Compacta', 'editorial' => 'Editorial',
            'reel' => 'Cine · tira de película', 'atlas' => 'Atlas · cuaderno cartográfico', 'studio' => 'Estudio · retícula suiza',
            'orbit' => 'Órbita · cielo 3D', 'scrapbook' => 'Álbum · recortes y postales'],
        'header_desktop' => ['current' => 'Tipográfica', 'centered' => 'Retrato centrado', 'compact' => 'Compacta', 'editorial' => 'Editorial',
            'museum' => 'Museo · galería blanca', 'observatory' => 'Observatorio · constelación 3D', 'newsroom' => 'Periódico · portada de autor',
            'noir' => 'Noir · estreno de cine', 'blueprint' => 'Plano · archivo técnico'],
        'gallery_mobile' => ['standard' => 'Masonry · alturas naturales', 'grid' => 'Cuadrícula', 'mosaic' => 'Mosaico cromático', 'asymmetric' => 'Editorial asimétrica', 'category-rails' => 'Filas por categoría', 'scattered' => 'Álbum desordenado', 'exhibition' => 'Sala de exposición', 'contact-sheet' => 'Hoja de contactos', 'narrative' => 'Secuencia narrativa', 'triptych' => 'Trípticos'],
        'gallery_desktop' => ['standard' => 'Masonry · alturas naturales', 'grid' => 'Cuadrícula', 'mosaic' => 'Mosaico cromático', 'asymmetric' => 'Editorial asimétrica', 'category-rails' => 'Filas por categoría', 'scattered' => 'Álbum desordenado', 'exhibition' => 'Sala de exposición', 'contact-sheet' => 'Hoja de contactos', 'narrative' => 'Secuencia narrativa', 'triptych' => 'Trípticos'],
        'pagination_shape' => ['circle' => 'Círculos', 'square' => 'Cuadrados'],
        'gallery_premium' => site_premium_gallery_choices(),
        'hover' => ['soft' => 'Suave', 'zoom' => 'Acercamiento', 'lift' => 'Elevación', 'reveal' => 'Revelado', 'tint' => 'Virado de color', 'frame' => 'Marco luminoso', 'slide' => 'Desplazamiento', 'tilt' => 'Perspectiva', 'focus' => 'Enfoque', 'shine' => 'Destello'],
    ];
}

/**
 * Fotos visibles a la vez por pantalla (0 = todas). Es independiente del número de columnas; main.js
 * pagina con este valor.
 */
/**
 * Diseños estándar que aplican la proporción elegida en «Tipo de cuadrícula».
 * Las dos galerías (móvil y escritorio) comparten el mismo ajuste, por eso basta
 * con que una de ellas sea compatible para mantenerlo disponible.
 */
function site_grid_setting_layouts(): array
{
    return ['standard', 'grid', 'contact-sheet', 'triptych'];
}

function site_grid_setting_is_used(array $settings): bool
{
    $supported = site_grid_setting_layouts();
    return in_array($settings['gallery_mobile'] ?? 'standard', $supported, true)
        || in_array($settings['gallery_desktop'] ?? 'standard', $supported, true);
}

function site_photos_per_view_choices(): array
{
    return [6, 8, 9, 10, 12, 15, 16, 18, 20, 24, 30, 36, 40, 50, 60, 100, 0];
}

// =============================================================================
// 2. BLOQUES DE LA PORTADA
// =============================================================================

/**
 * Bloques reordenables de la portada, en su orden por defecto. Cada uno tiene su archivo en
 * inc/blocks/ (ver inc/page-blocks.php).
 * label: nombre en el panel · hint: ayuda · toggle: ajuste `show_*` que lo activa (null = siempre visible).
 */
function site_section_definitions(): array
{
    return [
        'categories' => ['label' => 'Categorías', 'hint' => 'Filtro por categorías', 'toggle' => 'show_categories'],
        'gallery' => ['label' => 'Bloque de galería', 'hint' => 'Fotos, buscador y presentación', 'toggle' => null],
        'map' => ['label' => 'Mapa', 'hint' => 'Mapa de ubicaciones', 'toggle' => 'show_map'],
        'project' => ['label' => '«El proyecto»', 'hint' => 'Texto y retrato del proyecto', 'toggle' => 'show_project'],
        'social' => ['label' => 'Redes Sociales', 'hint' => 'Perfiles y enlaces sociales', 'toggle' => 'show_social'],
    ];
}

/**
 * Nombres de los bloques reordenables (clave => etiqueta).
 */
function site_section_labels(): array
{
    return array_map(static fn(array $definition): string => $definition['label'], site_section_definitions());
}

/**
 * Ajustes de tipo interruptor (true/false): el de cada bloque que se puede ocultar más los de las
 * dos cabeceras. Los usan la validación y los atributos del <body>, así que añadir un bloque nuevo
 * con `toggle` en site_section_definitions() basta para que se valide y llegue al CSS.
 */
function site_toggle_keys(): array
{
    $keys = array_values(array_filter(array_column(site_section_definitions(), 'toggle')));
    return array_merge($keys, ['show_header_mobile', 'show_header_desktop']);
}

/**
 * Normaliza `section_order`: descarta claves desconocidas o repetidas y añade al final las que
 * falten, para que siempre estén todos los bloques.
 */
function site_section_order_normalize(mixed $order): array
{
    $known = array_keys(site_section_labels());
    $result = [];
    foreach (is_array($order) ? $order : [] as $key) {
        if (is_string($key) && in_array($key, $known, true) && !in_array($key, $result, true)) $result[] = $key;
    }
    return array_merge($result, array_values(array_diff($known, $result)));
}

// =============================================================================
// 3. VALORES POR DEFECTO Y TEXTOS EDITABLES
// =============================================================================

/**
 * Redes disponibles en el editor y en la portada. Instagram y Threads conservan sus marcas CSS actuales.
 */
function site_social_networks(): array
{
    return ['instagram'=>'Instagram', 'threads'=>'Threads', 'facebook'=>'Facebook', 'x'=>'X', 'youtube'=>'YouTube',
        'tiktok'=>'TikTok', 'flickr'=>'Flickr', 'linkedin'=>'LinkedIn', 'pinterest'=>'Pinterest', '500px'=>'500px',
        'bluesky'=>'Bluesky', 'mastodon'=>'Mastodon'];
}

/**
 * Valores iniciales de todos los ajustes. Un archivo antiguo al que le falte una clave hereda estos
 * valores.
 */
function site_settings_defaults(): array
{
    return ['palette' => 'current', 'grid' => 'adaptive', 'columns_mobile' => 3,
        'columns_desktop' => 3, 'photos_mobile' => 12, 'photos_desktop' => 20, 'header_mobile' => 'current', 'header_desktop' => 'current',
        'gallery_mobile' => 'standard', 'gallery_desktop' => 'standard', 'hover' => 'soft', 'pagination_shape' => 'circle', 'gallery_premium' => 'none', 'site_title' => '',
        'section_order' => array_keys(site_section_labels()), 'show_header_mobile' => true, 'show_header_desktop' => true,
        'show_categories' => true, 'show_map' => false, 'show_project' => true, 'show_social' => false,
        'texts' => [], 'pages' => [], 'profile_image' => '/profile-placeholder.svg', 'logo_image' => '/favicon.svg',
        'profile_website_url' => 'https://example.com',
        'social_links' => []];
}

/**
 * Claves de texto que se editan en «Textos» o pertenecen a las plantillas elegidas.
 */
function site_editable_text_keys(): array
{
    $keys = [
        'text_30170303c506cf6c',
        'text_318bb1fc64a6e036',
        'text_4577bab0d9627af1',
        'text_2e2b80d871481edc',
        'text_54b7645201863e32',
        'text_7e6debbd1dab03b1',
        'text_bdd8f61b94c52379',
        'text_49a006d539216e16',
        'text_ab3ceef210237f9d',
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
        'text_30170303c506cf6c' => 'Nombre del fotógrafo o estudio',
        'text_318bb1fc64a6e036' => 'Crédito del pie de página (después del año)',
        'text_4577bab0d9627af1' => 'Nombre de usuario del perfil',
        'text_2e2b80d871481edc' => 'Descripción principal',
        'text_54b7645201863e32' => 'Descripción secundaria',
        'text_7e6debbd1dab03b1' => 'Web de usuario',
        'text_bdd8f61b94c52379' => 'Título de Redes Sociales',
        'text_49a006d539216e16' => 'Subtítulo de Redes Sociales',
        'text_ab3ceef210237f9d' => 'Texto de Redes Sociales',
    ];
}

/**
 * URL local de la imagen de perfil o del logo; si la ruta no es válida, devuelve la imagen por defecto.
 */
function site_media_url(string $key): string
{
    $settings = site_settings_load();
    $default = $key === 'logo_image' ? '/favicon.svg' : '/profile-placeholder.svg';
    $value = (string) ($settings[$key] ?? $default);
    return preg_match('~^/[a-zA-Z0-9._/-]+$~', $value) ? $value : $default;
}

/**
 * Catálogo de textos con su valor por defecto (inc/site-texts.php).
 */
function site_text_catalog(): array
{
    static $catalog;
    return $catalog ??= require __DIR__ . '/site-texts.php';
}

// =============================================================================
// 4. VALIDACIÓN Y GUARDADO
// =============================================================================

/**
 * Valida y normaliza los ajustes recibidos (formulario o JSON). Lanza InvalidArgumentException si
 * algo no es válido; devuelve siempre un conjunto completo de ajustes.
 */
/**
 * Cuenta caracteres UTF-8 sin exigir que mbstring esté instalado en el hosting.
 */
function site_utf8_length(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $length = preg_match_all('/./us', $value, $matches);
    return $length === false ? PHP_INT_MAX : $length;
}

function site_settings_validate(array $input): array
{
    $out = site_settings_defaults();
    // Con una galería premium activa, sus campos ignorados llegan desactivados (no se envían): se dejan como estén.
    $premiumOn = isset($input['gallery_premium']) && is_string($input['gallery_premium']) && $input['gallery_premium'] !== 'none';
    $ignored = $premiumOn ? site_premium_ignored_settings($input['gallery_premium']) : [];
    foreach (site_design_choices() as $key => $choices) {
        if (in_array($key, $ignored, true) && !isset($input[$key])) continue;
        if (!isset($input[$key]) || !is_string($input[$key]) || !isset($choices[$input[$key]])) {
            throw new InvalidArgumentException('Selecciona una opción válida para ' . $key . '.');
        }
        $out[$key] = $input[$key];
    }
    foreach (site_toggle_keys() as $key) {
        $out[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
    $out['section_order'] = site_section_order_normalize($input['section_order'] ?? null);
    foreach (['columns_mobile' => 4, 'columns_desktop' => 10] as $key => $max) {
        if (in_array($key, $ignored, true) && !isset($input[$key])) continue;
        $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1 || $value > $max) {
            throw new InvalidArgumentException('El número de columnas debe estar entre 1 y ' . $max . '.');
        }
        $out[$key] = $value;
    }
    foreach (['photos_mobile', 'photos_desktop'] as $key) {
        if (in_array($key, $ignored, true) && !isset($input[$key])) continue;
        $value = filter_var($input[$key] ?? $out[$key], FILTER_VALIDATE_INT);
        if ($value === false || !in_array($value, site_photos_per_view_choices(), true)) {
            throw new InvalidArgumentException('Selecciona un número válido de fotos visibles.');
        }
        $out[$key] = $value;
    }
    // Título de la web: vacío = el de por defecto del catálogo de textos.
    $title = trim((string) ($input['site_title'] ?? ''));
    if (!preg_match('//u', $title) || preg_match('/[\x00-\x1F\x7F]/', $title) || site_utf8_length($title) > 80) {
        throw new InvalidArgumentException('El título de la web no es válido (máximo 80 caracteres y sin saltos de línea).');
    }
    $out['site_title'] = $title === site_title_default() ? '' : $title;
    if (!isset($input['texts']) || !is_array($input['texts'])) {
        throw new InvalidArgumentException('Los textos no tienen un formato válido.');
    }
    foreach (site_editable_text_keys() as $key) {
        $entry = site_text_catalog()[$key];
        $value = $input['texts'][$key] ?? $entry['default'];
        if (!is_string($value) || strlen($value) > 20000 || !preg_match('//u', $value)) {
            throw new InvalidArgumentException('Hay un texto inválido o demasiado largo.');
        }
        if ($value !== $entry['default']) $out['texts'][$key] = $value;
    }
    $pages = $input['pages'] ?? [];
    if (!is_array($pages)) throw new InvalidArgumentException('El contenido de las páginas no tiene un formato válido.');
    foreach (site_editable_page_keys() as $key) {
        if (!array_key_exists($key, $pages)) continue;
        if (!is_string($pages[$key])) throw new InvalidArgumentException('El contenido de una página no es válido.');
        $out['pages'][$key] = site_sanitize_page_html($pages[$key]);
    }
    foreach (['profile_image' => '/profile-placeholder.svg', 'logo_image' => '/favicon.svg'] as $key => $default) {
        $value = (string) ($input[$key] ?? $default);
        if (!preg_match('~^/[a-zA-Z0-9._/-]+$~', $value)) {
            throw new InvalidArgumentException('La ruta de imagen no es válida.');
        }
        $out[$key] = $value;
    }
    $website = trim((string) ($input['profile_website_url'] ?? $out['profile_website_url']));
    if ($website !== '' && (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('~^https://~i', $website))) {
        throw new InvalidArgumentException('La dirección de la web personal debe empezar por https://.');
    }
    $out['profile_website_url'] = $website;
    $allowedSocials = array_keys(site_social_networks());
    $socialLinks = $input['social_links'] ?? [];
    if (!is_array($socialLinks)) throw new InvalidArgumentException('Las redes sociales no tienen un formato válido.');
    // Migra perfiles guardados antes del editor unificado. Las URLs de ejemplo no son perfiles reales.
    $legacyProfiles = [
        ['network'=>'instagram', 'url'=>(string) ($input['instagram_url'] ?? ''), 'handle'=>trim((string) ($input['texts']['text_3b20084d3d94b4ee'] ?? '')) ?: site_text_catalog()['text_3b20084d3d94b4ee']['default']],
        ['network'=>'threads', 'url'=>(string) ($input['threads_url'] ?? ''), 'handle'=>trim((string) ($input['texts']['social_threads_user'] ?? '')) ?: site_text_catalog()['social_threads_user']['default']],
    ];
    $existingNetworks = array_map(static fn($row) => is_array($row) ? strtolower((string) ($row['network'] ?? '')) : '', $socialLinks);
    $legacyProfiles = array_values(array_filter($legacyProfiles, static fn($row) =>
        trim($row['url']) !== '' && trim($row['url']) !== 'https://example.com' && !in_array($row['network'], $existingNetworks, true)));
    $socialLinks = array_merge($legacyProfiles, $socialLinks);
    $out['social_links'] = [];
    foreach ($socialLinks as $row) {
        if (!is_array($row)) continue;
        $network = strtolower(trim((string) ($row['network'] ?? '')));
        $url = trim((string) ($row['url'] ?? ''));
        $handle = trim((string) ($row['handle'] ?? ''));
        if ($network === '' && $url === '' && $handle === '') continue;
        if (!in_array($network, $allowedSocials, true)) throw new InvalidArgumentException('Selecciona una red social válida.');
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https://~i', $url)) throw new InvalidArgumentException('La URL de una red social no es válida.');
        if (strlen($handle) > 120 || !preg_match('//u', $handle)) throw new InvalidArgumentException('El usuario de una red social no es válido.');
        $out['social_links'][] = ['network' => $network, 'url' => $url, 'handle' => $handle];
        if (count($out['social_links']) >= 12) break;
    }
    return $out;
}

/**
 * Lee los ajustes guardados. Si el archivo no existe o está dañado, devuelve los valores por defecto.
 */
function site_settings_load(?string $path = null): array
{
    $path ??= site_settings_path();
    $settings = site_settings_defaults();
    if (!is_file($path)) return $settings;
    $raw = @file_get_contents($path);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) return $settings;
    foreach (['gallery_mobile', 'gallery_desktop'] as $key) {
        if (in_array($decoded[$key] ?? '', ['immersive', 'art-walk'], true)) $decoded[$key] = 'standard';
    }
    try {
        $settings = site_settings_validate(array_replace($settings, $decoded));
    } catch (InvalidArgumentException $error) {
        error_log('Invalid site settings: ' . $error->getMessage());
    }
    return $settings;
}

/**
 * Valida y guarda los ajustes de forma atómica (archivo temporal + rename). Lanza RuntimeException
 * si no puede escribir.
 */
function site_settings_save(array $input, ?string $path = null): void
{
    $settings = site_settings_validate($input);
    $path ??= site_settings_path();
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('No se pudo crear la carpeta de configuración.');
    }
    $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $temporary = tempnam($directory, '.site-settings-');
    if ($temporary === false) throw new RuntimeException('No se pudo preparar el guardado.');
    try {
        if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !chmod($temporary, 0640) || !rename($temporary, $path)) {
            throw new RuntimeException('No se pudo guardar la configuración. Comprueba los permisos de la carpeta config.');
        }
    } finally {
        if (is_file($temporary)) unlink($temporary);
    }
}

// =============================================================================
// 5. TEXTOS DEL SITIO
// =============================================================================

/**
 * Texto editable por clave: el guardado en los ajustes o, si no hay, el valor por defecto del catálogo.
 */
function site_text(string $key): string
{
    static $settings;
    $settings ??= site_settings_load();
    if (isset($settings['texts'][$key])) return $settings['texts'][$key];
    $default = site_text_catalog()[$key]['default'] ?? '';
    // Los textos por defecto que mencionan el título de la web siguen al título elegido en el panel.
    $title = trim((string) ($settings['site_title'] ?? ''));
    return $title === '' ? $default : str_replace(site_title_default(), $title, $default);
}

/**
 * Título original de la web: el valor por defecto del texto «nombre del sitio» del catálogo.
 */
function site_title_default(): string
{
    return site_text_catalog()['text_52179dc42df7efe5']['default'];
}

/**
 * Título actual de la web (ajuste «Título de la web»). Se usa en cabeceras, pie, <title>, textos
 * accesibles y en el panel.
 */
function site_title(): string
{
    $title = trim((string) (site_settings_load()['site_title'] ?? ''));
    return $title !== '' ? $title : site_title_default();
}

/**
 * Igual que site_text(), escapado para usarlo dentro de HTML.
 */
function site_text_html(string $key): string
{
    return htmlspecialchars(site_text($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Textos que se pueden editar desde la web por el administrador autenticado. */
function site_inline_editable_text_keys(): array
{
    return array_values(array_diff(site_editable_text_keys(), [
        'text_bdd8f61b94c52379',
        'text_49a006d539216e16',
        'text_ab3ceef210237f9d',
    ]));
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

function site_inline_text_html(string $key): string
{
    $value = site_text_html($key);
    if (empty($GLOBALS['siteInlineEditorEnabled']) || !in_array($key, site_inline_editable_text_keys(), true)) return $value;
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<span class="inline-edit-text" data-inline-edit-key="' . $escape($key) . '" data-inline-edit-label="' . $escape(site_inline_text_label($key)) . '">' . $value . '</span>';
}

// =============================================================================
// 6. PÁGINAS DE TEXTO EDITABLES
// =============================================================================

/**
 * Páginas cuyo contenido se edita en el panel.
 */
function site_editable_page_keys(): array
{
    return ['legal_notice', 'privacy_policy', 'cookies_policy', 'project'];
}

/**
 * Nombre de cada página editable.
 */
function site_page_labels(): array
{
    return ['legal_notice' => 'Aviso legal', 'privacy_policy' => 'Política de privacidad',
        'cookies_policy' => 'Política de cookies', 'project' => 'El Proyecto'];
}

/**
 * Contenido por defecto de una página, construido con los textos del catálogo.
 */
function site_page_default_html(string $page): string
{
    $content = [
        'legal_notice' => '<h2>Información legal</h2><p>Configura aquí la identidad y los datos de contacto del responsable antes de publicar el sitio. Este texto es una plantilla de ejemplo y no constituye asesoramiento legal.</p>',
        'privacy_policy' => '<h2>Privacidad</h2><p>Completa esta política con el responsable del tratamiento, los datos que recopila el sitio, sus finalidades, bases legales, conservación y derechos de las personas usuarias antes de publicarla.</p>',
        'cookies_policy' => '<h2>Cookies y almacenamiento</h2><p>Describe aquí las cookies y tecnologías de almacenamiento que utiliza tu instalación, su finalidad, duración y cómo gestionar el consentimiento. Revisa este contenido antes de publicar.</p>',
        'project' => '<p>Presenta aquí la galería, sus fotografías y las personas que la crean.</p>',
    ];
    return $content[$page] ?? '';
}

/**
 * Deja solo las etiquetas y atributos permitidos en el contenido de una página.
 */
function site_sanitize_page_html(string $html): string
{
    if (strlen($html) > 100000 || !preg_match('//u', $html)) {
        throw new InvalidArgumentException('El contenido de la página es demasiado largo o no es válido.');
    }
    $html = preg_replace('~<(script|style|iframe|object|svg|math)\b[^>]*>.*?(?:</\1\s*>|$)~is', '', $html) ?? '';
    $allowed = ['p', 'div', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'sub', 'sup', 'ul', 'ol', 'li', 'blockquote', 'a'];
    return preg_replace_callback('~<\\s*(/?)\\s*([a-z][a-z0-9]*)\\b([^>]*)>~i', static function(array $match) use ($allowed): string {
        $tag = strtolower($match[2]);
        if (!in_array($tag, $allowed, true)) return '';
        if ($match[1] === '/') return in_array($tag, ['br'], true) ? '' : '</' . $tag . '>';
        if ($tag === 'br') return '<br>';
        if ($tag === 'a') {
            if (!preg_match('/\\bhref\\s*=\\s*(["\'])(.*?)\\1/is', $match[3], $href)) return '<a>';
            $url = trim(html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!preg_match('~^(?:https?://|mailto:|/(?!/))~i', $url)) return '<a>';
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" rel="noopener noreferrer">';
        }
        $alignment = '';
        if (in_array($tag, ['p', 'div', 'h2', 'h3', 'h4', 'blockquote'], true)
            && preg_match('/\\bstyle\\s*=\\s*(["\'])(.*?)\\1/is', $match[3], $style)
            && preg_match('/(?:^|;)\\s*text-align\\s*:\\s*(left|center|right|justify)\\s*(?:;|$)/i', trim($style[2]), $align)) {
            $alignment = ' style="text-align:' . strtolower($align[1]) . '"';
        }
        return '<' . $tag . $alignment . '>';
    }, $html) ?? '';
}

/**
 * Contenido actual de una página: el guardado o el de por defecto.
 */
function site_page_content(string $page): string
{
    if (!in_array($page, site_editable_page_keys(), true)) return '';
    $settings = site_settings_load();
    return $settings['pages'][$page] ?? site_page_default_html($page);
}

// =============================================================================
// 7. SALIDA PARA LA WEB PÚBLICA
// =============================================================================

/**
 * Atributos del <body> (data-* y variables CSS) que activan en el CSS y el JS los ajustes de diseño,
 * de visibilidad y de columnas.
 */
function site_design_attributes(): string
{
    $settings = site_settings_load();
    $attributes = '';
    // Con una galería premium activa los ajustes estándar de galería no se aplican: el <body> los marca como «premium».
    $premium = site_premium_gallery_active($settings);
    foreach (['palette', 'grid', 'header_mobile', 'header_desktop', 'gallery_mobile', 'gallery_desktop', 'hover', 'pagination_shape', 'gallery_premium'] as $key) {
        $value = $premium !== null && in_array($key, ['grid', 'gallery_mobile', 'gallery_desktop'], true) ? 'premium' : $settings[$key];
        $attributes .= ' data-' . str_replace('_', '-', $key) . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
    }
    foreach (site_toggle_keys() as $key) {
        $attributes .= ' data-' . str_replace('_', '-', $key) . '="' . ($settings[$key] ? 'true' : 'false') . '"';
    }
    $attributes .= ' data-photos-mobile="' . (int) $settings['photos_mobile'] . '" data-photos-desktop="' . (int) $settings['photos_desktop'] . '"';
    return $attributes . ' style="--columns-mobile:' . $settings['columns_mobile'] . ';--columns-desktop:' . $settings['columns_desktop'] . '"';
}

/**
 * Textos editables para el JS: JSON incrustado más site-texts.js.
 */
function site_client_texts(): string
{
    $texts = [];
    foreach (site_text_catalog() as $key => $entry) $texts[$entry['default']] = site_text($key);
    return '<script id="siteTexts" type="application/json">'
        . json_encode($texts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
        . '</script><script src="/assets/js/site-texts.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/site-texts.js'), 0, 12) . '"></script>';
}

// Formularios del panel (solo HTML), en su propio archivo.
require_once __DIR__ . '/site-settings-form.php';
