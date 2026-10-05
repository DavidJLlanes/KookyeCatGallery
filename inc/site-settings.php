<?php
declare(strict_types=1);

// Outside the document root and the deployment directory, like upload-auth.php.
function site_settings_path(): string
{
    $privateDir = getenv('GALLERY_PRIVATE_DIR') ?: dirname(__DIR__) . '/var';
    return rtrim($privateDir, '/\\') . '/site-settings.json';
}

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
        'hover' => ['soft' => 'Suave', 'zoom' => 'Acercamiento', 'lift' => 'Elevación', 'reveal' => 'Revelado', 'tint' => 'Virado de color', 'frame' => 'Marco luminoso', 'slide' => 'Desplazamiento', 'tilt' => 'Perspectiva', 'focus' => 'Enfoque', 'shine' => 'Destello'],
    ];
}

function site_settings_defaults(): array
{
    return ['palette' => 'current', 'grid' => 'adaptive', 'columns_mobile' => 3,
        'columns_desktop' => 3, 'header_mobile' => 'current', 'header_desktop' => 'current',
        'gallery_mobile' => 'standard', 'gallery_desktop' => 'standard', 'hover' => 'soft',
        'show_categories' => true, 'show_map' => false, 'show_project' => true, 'show_social' => false,
        'texts' => [], 'pages' => [], 'profile_image' => '/profile-placeholder.svg', 'logo_image' => '/favicon.svg',
        'instagram_url' => 'https://example.com',
        'threads_url' => 'https://example.com',
        'social_links' => []];
}

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

function site_media_url(string $key): string
{
    $settings = site_settings_load();
    $default = $key === 'logo_image' ? '/favicon.svg' : '/profile-placeholder.svg';
    $value = (string) ($settings[$key] ?? $default);
    return preg_match('~^/[a-zA-Z0-9._/-]+$~', $value) ? $value : $default;
}

function site_text_catalog(): array
{
    static $catalog;
    return $catalog ??= require __DIR__ . '/site-texts.php';
}

function site_settings_validate(array $input): array
{
    $out = site_settings_defaults();
    foreach (site_design_choices() as $key => $choices) {
        if (!isset($input[$key]) || !is_string($input[$key]) || !isset($choices[$input[$key]])) {
            throw new InvalidArgumentException('Selecciona una opción válida para ' . $key . '.');
        }
        $out[$key] = $input[$key];
    }
    foreach (['show_categories', 'show_map', 'show_project', 'show_social'] as $key) {
        $out[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
    foreach (['columns_mobile' => 4, 'columns_desktop' => 10] as $key => $max) {
        $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < 1 || $value > $max) {
            throw new InvalidArgumentException('El número de columnas debe estar entre 1 y ' . $max . '.');
        }
        $out[$key] = $value;
    }
    if (!isset($input['texts']) || !is_array($input['texts'])) {
        throw new InvalidArgumentException('Los textos no tienen un formato válido.');
    }
    foreach (site_editable_text_keys() as $key) {
        $entry = $key === 'social_threads_user'
            ? ['default' => '@kookyecatgallery']
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
    foreach (['profile_image' => '/profile-placeholder.svg', 'logo_image' => '/favicon.svg'] as $key => $default) {
        $value = (string) ($input[$key] ?? $default);
        if (!preg_match('~^/[a-zA-Z0-9._/-]+$~', $value)) {
            throw new InvalidArgumentException('La ruta de imagen no es válida.');
        }
        $out[$key] = $value;
    }
    foreach (['instagram_url' => 'https://example.com', 'threads_url' => 'https://example.com'] as $key => $default) {
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

function site_text(string $key): string
{
    static $settings;
    $settings ??= site_settings_load();
    return $settings['texts'][$key] ?? site_text_catalog()[$key]['default'] ?? '';
}

function site_text_html(string $key): string
{
    return htmlspecialchars(site_text($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_editable_page_keys(): array
{
    return ['legal_notice', 'privacy_policy', 'cookies_policy', 'project'];
}

function site_page_labels(): array
{
    return ['legal_notice' => 'Aviso legal', 'privacy_policy' => 'Política de privacidad',
        'cookies_policy' => 'Política de cookies', 'project' => 'El Proyecto'];
}

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

function site_page_content(string $page): string
{
    if (!in_array($page, site_editable_page_keys(), true)) return '';
    $settings = site_settings_load();
    return $settings['pages'][$page] ?? site_page_default_html($page);
}

function site_design_attributes(): string
{
    $settings = site_settings_load();
    $attributes = '';
    foreach (['palette', 'grid', 'header_mobile', 'header_desktop', 'gallery_mobile', 'gallery_desktop', 'hover'] as $key) {
        $attributes .= ' data-' . str_replace('_', '-', $key) . '="' . htmlspecialchars($settings[$key], ENT_QUOTES, 'UTF-8') . '"';
    }
    foreach (['show_categories', 'show_map', 'show_project', 'show_social'] as $key) {
        $attributes .= ' data-' . str_replace('_', '-', $key) . '="' . ($settings[$key] ? 'true' : 'false') . '"';
    }
    return $attributes . ' style="--columns-mobile:' . $settings['columns_mobile'] . ';--columns-desktop:' . $settings['columns_desktop'] . '"';
}

function site_client_texts(): string
{
    $texts = [];
    foreach (site_text_catalog() as $key => $entry) $texts[$entry['default']] = site_text($key);
    return '<script id="siteTexts" type="application/json">'
        . json_encode($texts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
        . '</script><script src="/assets/js/site-texts.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/site-texts.js'), 0, 12) . '"></script>';
}

function site_settings_form(string $csrf, ?array $values = null, string $section = 'profile'): string
{
    $settings = $values ?? site_settings_load();
    $escape = fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<form method="post" action="/admin.php?settings=1" class="upload-form site-settings" data-settings-section="' . $escape($section) . '">'
        . '<input type="hidden" name="action" value="save_site_settings">'
        . '<input type="hidden" name="csrf" value="' . $escape($csrf) . '">'
        . '<input type="hidden" name="section" value="' . $escape($section) . '">'
        . '<p>Personaliza la web. Los textos de fotografías y categorías se editan en Gestionar fotos. Los campos admiten texto plano.</p>';
    $html .= '<div class="settings-design"><h2>Diseño estándar</h2>';
    $labels = ['palette' => 'Paleta de colores', 'grid' => 'Tipo de cuadrícula', 'header_mobile' => 'Cabecera móvil', 'header_desktop' => 'Cabecera de escritorio', 'gallery_mobile' => 'Galería móvil', 'gallery_desktop' => 'Galería de escritorio', 'hover' => 'Efecto Hover'];
    foreach (site_design_choices() as $key => $choices) {
        $html .= '<div class="upload-field"><label for="setting-' . $key . '">' . $labels[$key] . '</label><select id="setting-' . $key . '" name="' . $key . '">';
        foreach ($choices as $value => $label) $html .= '<option value="' . $value . '"' . ($settings[$key] === $value ? ' selected' : '') . '>' . $label . '</option>';
        $html .= '</select></div>';
    }
    foreach (['columns_mobile' => ['Columnas en móvil', 4], 'columns_desktop' => ['Columnas en escritorio', 10]] as $key => [$label, $max]) {
        $html .= '<div class="upload-field"><label for="setting-' . $key . '">' . $label . '</label><select id="setting-' . $key . '" name="' . $key . '">';
        for ($i = 1; $i <= $max; $i++) $html .= '<option value="' . $i . '"' . ((int) $settings[$key] === $i ? ' selected' : '') . '>' . $i . '</option>';
        $html .= '</select></div>';
    }
    $html .= '<p class="upload-help">Masonry conserva siempre las proporciones originales y coloca cada foto en la columna más corta. Para fotos cuadradas, horizontales o verticales, elige la galería «Cuadrícula» y su tipo de cuadrícula. El resto de galerías conserva su composición propia. El efecto hover se elige por separado y también responde al foco de teclado y al toque.</p>';
    $html .= '<fieldset class="site-section-switches"><legend>Secciones de la página</legend>';
    foreach (['show_categories' => 'Mostrar categorías', 'show_map' => 'Mostrar mapa', 'show_project' => 'Mostrar «El proyecto»', 'show_social' => 'Mostrar enlaces sociales'] as $key => $label) {
        $checked = !empty($settings[$key]) ? ' checked' : '';
        $html .= '<label class="site-section-switch"><input type="checkbox" name="' . $key . '" value="1"' . $checked . '><span class="site-section-switch__track" aria-hidden="true"></span><span>' . $label . '</span></label>';
    }
    $html .= '</fieldset></div>';
    $html .= '<section class="page-editor-options"><h2>Editar textos de páginas</h2><p class="upload-help">Abre una página para editar su contenido y formato.</p><div class="page-editor-options__grid">';
    foreach (site_page_labels() as $key => $label) {
        $html .= '<a class="page-editor-option" href="/admin.php?settings=1&amp;section=page&amp;doc=' . $key . '"><span>' . $escape($label) . '</span><span aria-hidden="true">Editar →</span></a>';
    }
    $html .= '</div></section>';
    $html = str_replace('<form method="post"', '<form method="post" enctype="multipart/form-data"', $html);
    $html .= '<div class="settings-profile"><h2>Perfil</h2>';
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
    $html .= '<div class="upload-field"><label>Más redes sociales</label><div class="social-settings-list">';
    for ($i = 0; $i < 12; $i++) {
        $row = $settings['social_links'][$i] ?? ['network'=>'','url'=>'','handle'=>''];
        $html .= '<div class="social-settings-row"><select name="social_links['.$i.'][network]"><option value="">Añadir red…</option>';
        foreach ($socialNames as $value => $label) $html .= '<option value="'.$value.'"'.(($row['network'] ?? '') === $value ? ' selected' : '').'>'.$label.'</option>';
        $html .= '</select><input type="url" name="social_links['.$i.'][url]" maxlength="500" placeholder="https://…" value="'.$escape((string)($row['url'] ?? '')).'"><input type="text" name="social_links['.$i.'][handle]" maxlength="120" placeholder="@usuario o nombre" value="'.$escape((string)($row['handle'] ?? '')).'"></div>';
    }
    $html .= '<small class="upload-help">Rellena solo las redes que quieras mostrar. Puedes añadir hasta 12.</small></div></div>';
    $html .= '<input type="hidden" name="profile_image" value="' . $escape((string) $settings['profile_image']) . '">'
        . '<input type="hidden" name="logo_image" value="' . $escape((string) $settings['logo_image']) . '">'
        . '<div class="upload-field"><label for="profile-image">Foto de perfil</label>'
        . '<input id="profile-image" name="profile_image_file" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">'
        . '<small class="upload-help">JPG, PNG o WebP. Máximo 15 MB.</small></div>'
        . '<div class="upload-field"><label for="logo-image">Logo</label>'
        . '<input id="logo-image" name="logo_image_file" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">'
        . '<small class="upload-help">JPG, PNG o WebP. Máximo 15 MB.</small></div></div>';
    return $html . '<button class="upload-submit" type="submit">Guardar configuración</button><a href="/" target="_blank" rel="noopener">Ver la web</a></form>';
}

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
