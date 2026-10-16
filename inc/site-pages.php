<?php
declare(strict_types=1);

/**
 * Páginas de texto editables desde el panel (aviso legal, privacidad, cookies y «El proyecto»).
 * (Cargado por inc/site-settings.php.)
 */

/**
 * Páginas cuyo contenido se edita en el panel.
 */
function site_editable_page_keys(): array
{
    return ['legal_notice', 'privacy_policy', 'cookies_policy', 'project', 'project_aside'];
}

/**
 * Nombre de cada página editable.
 */
function site_page_labels(): array
{
    return ['legal_notice' => 'Aviso legal', 'privacy_policy' => 'Política de privacidad',
        'cookies_policy' => 'Política de cookies', 'project' => 'El Proyecto', 'project_aside' => 'El Proyecto · segunda columna'];
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
    // «project_aside» (segunda columna de «El proyecto») empieza vacía: solo se usa si eliges esa disposición y la escribes.
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
