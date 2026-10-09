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

require_once __DIR__ . '/premium-galleries.php';

// =============================================================================
// 1. ALMACENAMIENTO Y OPCIONES DE DISEÑO
// =============================================================================

/**
 * Ruta del JSON de configuración. Está fuera del directorio público y de los archivos desplegados
 * (como upload-auth.php), así que un despliegue no la pisa.
 */
function site_settings_path(): string
{
    return '/davidjimenezllanes.es/config/site-settings.json';
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
        'social' => ['label' => 'Enlaces sociales', 'hint' => 'Contacto y redes', 'toggle' => 'show_social'],
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
 * Valores iniciales de todos los ajustes. Un archivo antiguo al que le falte una clave hereda estos
 * valores.
 */
function site_settings_defaults(): array
{
    return ['palette' => 'current', 'grid' => 'adaptive', 'columns_mobile' => 3,
        'columns_desktop' => 3, 'photos_mobile' => 12, 'photos_desktop' => 20, 'header_mobile' => 'current', 'header_desktop' => 'current',
        'gallery_mobile' => 'standard', 'gallery_desktop' => 'standard', 'hover' => 'soft', 'pagination_shape' => 'circle', 'gallery_premium' => 'none', 'site_title' => '',
        'section_order' => array_keys(site_section_labels()), 'show_header_mobile' => true, 'show_header_desktop' => true,
        'show_categories' => true, 'show_map' => true, 'show_project' => true, 'show_social' => true,
        'texts' => [], 'pages' => [], 'profile_image' => '/david.webp', 'logo_image' => '/escudoleon.webp',
        'instagram_url' => 'https://www.instagram.com/davidjllanes',
        'threads_url' => 'https://www.threads.net/@davidjllanes',
        'social_links' => []];
}

/**
 * Claves de texto que se editan en la pestaña «Perfil» (el resto de textos se editan en las páginas).
 */
function site_editable_text_keys(): array
{
    return [
        'text_4577bab0d9627af1',
        'text_2e2b80d871481edc',
        'text_54b7645201863e32',
        'text_7e6debbd1dab03b1',
        // Usuarios visibles de los perfiles sociales (Instagram y Threads).
        'text_3b20084d3d94b4ee',
        'social_threads_user',
    ];
}

/**
 * Etiqueta que se muestra en el panel para cada texto editable.
 */
function site_admin_text_labels(): array
{
    return [
        'text_4577bab0d9627af1' => 'Usuario',
        'text_2e2b80d871481edc' => 'Descripción principal',
        'text_54b7645201863e32' => 'Descripción secundaria',
        'text_7e6debbd1dab03b1' => 'Web de usuario',
        'text_3b20084d3d94b4ee' => 'Usuario de Instagram',
        'social_threads_user' => 'Usuario de Threads',
    ];
}

/**
 * URL local de la imagen de perfil o del logo; si la ruta no es válida, devuelve la imagen por defecto.
 */
function site_media_url(string $key): string
{
    $settings = site_settings_load();
    $default = $key === 'logo_image' ? '/escudoleon.webp' : '/david.webp';
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
    if (!preg_match('//u', $title) || preg_match('/[\x00-\x1F\x7F]/', $title) || mb_strlen($title) > 80) {
        throw new InvalidArgumentException('El título de la web no es válido (máximo 80 caracteres y sin saltos de línea).');
    }
    $out['site_title'] = $title === site_title_default() ? '' : $title;
    if (!isset($input['texts']) || !is_array($input['texts'])) {
        throw new InvalidArgumentException('Los textos no tienen un formato válido.');
    }
    foreach (site_editable_text_keys() as $key) {
        $entry = $key === 'social_threads_user'
            ? ['default' => '@davidjllanes']
            : site_text_catalog()[$key];
        $value = $input['texts'][$key] ?? $entry['default'];
        if (!is_string($value) || strlen($value) > 20000 || !preg_match('//u', $value)) {
            throw new InvalidArgumentException('Hay un texto inválido o demasiado largo.');
        }
        if ($key === 'social_threads_user' && trim($value) === '') $value = $entry['default'];
        if ($value !== $entry['default']) $out['texts'][$key] = $value;
    }
    $pages = $input['pages'] ?? [];
    if (!is_array($pages)) throw new InvalidArgumentException('El contenido de las páginas no tiene un formato válido.');
    foreach (site_editable_page_keys() as $key) {
        if (!array_key_exists($key, $pages)) continue;
        if (!is_string($pages[$key])) throw new InvalidArgumentException('El contenido de una página no es válido.');
        $out['pages'][$key] = site_sanitize_page_html($pages[$key]);
    }
    foreach (['profile_image' => '/david.webp', 'logo_image' => '/escudoleon.webp'] as $key => $default) {
        $value = (string) ($input[$key] ?? $default);
        if (!preg_match('~^/[a-zA-Z0-9._/-]+$~', $value)) {
            throw new InvalidArgumentException('La ruta de imagen no es válida.');
        }
        $out[$key] = $value;
    }
    foreach (['instagram_url' => 'https://www.instagram.com/davidjllanes', 'threads_url' => 'https://www.threads.net/@davidjllanes'] as $key => $default) {
        $value = trim((string) ($input[$key] ?? $default));
        if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('~^https://~i', $value)) {
            throw new InvalidArgumentException('La URL del perfil social no es válida.');
        }
        $out[$key] = $value;
    }
    $allowedSocials = ['facebook', 'x', 'youtube', 'tiktok', 'flickr', 'linkedin', 'pinterest', '500px', 'bluesky', 'mastodon'];
    $socialLinks = $input['social_links'] ?? [];
    if (!is_array($socialLinks)) throw new InvalidArgumentException('Las redes sociales no tienen un formato válido.');
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
    $t = static fn(string $key): string => site_text_html($key);
    $content = [
        'legal_notice' => '<h2>' . $t('text_062798b6730bc603') . '</h2><p>' . $t('text_3ab8939d4e00a5af') . ' <strong>' . $t('text_27e9006e3ef330f5') . '</strong> ' . $t('text_3efdbafd42b5d980') . ' <strong>' . $t('text_a6b54c20a7b96eea') . '</strong>' . $t('text_a18558f9e518a164') . ' <strong>' . $t('text_8d33d070d6c4b081') . '</strong>.</p>'
            . '<h2>' . $t('text_2187148fa660c266') . '</h2><p>' . $t('text_f2ee3e3d50ad633a') . '</p>'
            . '<h2>' . $t('text_f8f93bd27ed1978b') . '</h2><p>' . $t('text_6f2df8fcd7adb09a') . '</p><p>' . $t('text_e569619cdbd969d7') . '</p>'
            . '<h2>' . $t('text_700391b16151dc07') . '</h2><p>' . $t('text_dfb5ef92b4e378f2') . '</p><p>' . $t('text_f246188b9c948197') . '</p>'
            . '<h2>' . $t('text_a281cb39d20c3b93') . '</h2><p>' . $t('text_afa8e64bd135ce59') . '</p>'
            . '<h2>' . $t('text_c1034cc37bc846be') . '</h2><p>' . $t('text_381a9a6057d20dd5') . '</p>'
            . '<h2>' . $t('text_7e9d4894ce5d22db') . '</h2><p>' . $t('text_e008dedd60aef2cc') . '</p>',
        'privacy_policy' => '<h2>' . $t('text_f40a08d55695b46c') . '</h2><p>' . $t('text_cf1af996c0acd526') . ' <strong>' . $t('text_a6b54c20a7b96eea') . '</strong>' . $t('text_a18558f9e518a164') . ' <strong>' . $t('text_8d33d070d6c4b081') . '</strong>' . $t('text_676a588f80001416') . ' <strong>' . $t('text_27e9006e3ef330f5') . '</strong>.</p>'
            . '<h2>' . $t('text_3641805d702325c1') . '</h2><p>' . $t('text_bf0616b7f3cfa510') . '</p><p>' . $t('text_de7d8a234348e034') . '</p>'
            . '<h2>' . $t('text_aa852d396fdca6d6') . '</h2><p>' . $t('text_398edb1b02bac0a3') . '</p>'
            . '<h2>' . $t('text_b019d2968c534e4f') . '</h2><p>' . $t('text_eeb19871b1ee90fc') . '</p>'
            . '<h2>' . $t('text_be4f5ac046acadcf') . '</h2><p>' . $t('text_d6022e000436fc77') . '</p>'
            . '<h2>' . $t('text_1bd56bd31b85e92d') . '</h2><p>' . $t('text_a442ae75bed6e1cd') . '</p><p>' . $t('text_4aa4c90345d1cc65') . ' <strong>' . $t('text_ee696e219150d48a') . '</strong> (<a href="https://www.aepd.es">' . $t('text_8ae1d917d6c1ea9c') . '</a>' . $t('text_49e605c7193abea0') . '</p>'
            . '<h2>' . $t('text_d9a7b28cdf8785d9') . '</h2><p>' . $t('text_baf3aa11631bd30d') . '</p>'
            . '<h2>' . $t('text_bd0dfe901546d901') . '</h2><p>' . $t('text_e1529cd46482e38e') . '</p>',
        'cookies_policy' => '<h2>' . $t('text_49c19ea97b0ee7fb') . '</h2><p>' . $t('text_a56503b90b90430c') . '</p>'
            . '<h2>' . $t('text_d9f1508e39856918') . '</h2><p>' . $t('text_2872662c369b5663') . ' <strong>' . $t('text_fdbc92936867cf4c') . '</strong>' . $t('text_d421435680d9ae41') . '</p><p>' . $t('text_8278cef56c5845b5') . '</p>'
            . '<h2>' . $t('text_080a45aeb99066c1') . '</h2><p>' . $t('text_a4e74f4dc4ac1362') . ' <strong>' . $t('text_44738161b597e017') . '</strong>' . $t('text_3688e598c791f6db') . '</p>'
            . '<h2>' . $t('text_ae27b5d8b9079084') . '</h2><p>' . $t('text_3e1b6617765084b4') . '</p><ul>'
            . '<li><a href="https://support.google.com/chrome/answer/95647">' . $t('text_0e99a87ff0da91ab') . '</a></li>'
            . '<li><a href="https://support.mozilla.org/es/kb/habilitar-y-deshabilitar-cookies-sitios-web-rastrear-preferencias">' . $t('text_acc08972fddf56f3') . '</a></li>'
            . '<li><a href="https://support.apple.com/es-es/guide/safari/sfri11471/mac">' . $t('text_3035ab6550db87a0') . '</a></li>'
            . '<li><a href="https://support.microsoft.com/es-es/microsoft-edge">' . $t('text_951e7f89043186ca') . '</a></li></ul>'
            . '<h2>' . $t('text_c14529e76b4842fd') . '</h2><p>' . $t('text_a4c979481ee6a8bf') . '</p>',
        'project' => '<p><em>' . $t('text_52179dc42df7efe5') . '</em> ' . $t('text_9b9e0ce956198ed6') . '</p><p>'
            . $t('text_ded2b00dd9f9aee9') . ' <strong>' . $t('text_30170303c506cf6c') . '</strong>' . $t('text_5b20d476495c8ab2') . '</p>',
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
