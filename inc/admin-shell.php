<?php
declare(strict_types=1);

/**
 * Armazón (layout) del panel de administración.
 *
 * Solo define funciones: no tiene efectos secundarios al cargarse, por lo que los tests pueden
 * incluirlo directamente. Las acciones y las páginas del panel están en admin.php.
 *
 *   uploadEscape()         escapado HTML
 *   adminNavigation()      activa el armazón con menú
 *   uploadPage()           dibuja la página (con o sin armazón)
 *   adminShellHtml()       barra lateral + barra superior + barra inferior
 *   adminActiveSection()   entrada del menú resaltada
 *   adminIcon()            iconos SVG
 */

/**
 * Escapa texto para insertarlo en HTML.
 */
function uploadEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Icono SVG (trazo) del menú y de los botones de la barra superior.
 */
function adminIcon(string $name): string
{
    $paths = [
        'upload' => '<path d="M12 5v14M5 12h14"/>',
        'library' => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.6"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.6"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.6"/>',
        'categories' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'design' => '<path d="M4 6h8M18 6h2M4 12h2M12 12h8M4 18h10M20 18h0"/><circle cx="15" cy="6" r="2.2"/><circle cx="9" cy="12" r="2.2"/><circle cx="17" cy="18" r="2.2"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'logout' => '<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4M16 8l4 4-4 4M20 12H9"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/**
 * Entrada del menú que se resalta: upload, library, categories, profile o design.
 * Se deduce de la URL (GET) o de la acción enviada (POST), para que también sea correcta tras un error de formulario.
 */
function adminActiveSection(): string
{
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_site_page') return 'design';
    if (isset($_GET['settings']) || $action === 'save_site_settings') {
        $section = (string) ($_POST['section'] ?? $_GET['section'] ?? 'profile');
        return $section === 'profile' ? 'profile' : 'design';
    }
    if (isset($_GET['categories']) || $action === 'manage_category') return 'categories';
    if (isset($_GET['library']) || isset($_GET['edit']) || $action === 'delete_existing') return 'library';
    return 'upload';
}

/**
 * Armazón del panel: barra lateral (escritorio), barra superior (con instalar app, ver web y salir) y barra
 * inferior (móvil) alrededor del contenido. El CSS está en assets/css/admin.css.
 */
function adminShellHtml(string $title, string $content, bool $wide): string
{
    $active = adminActiveSection();
    $items = [
        'upload' => ['/admin.php', 'Subir foto', 'Subir'],
        'library' => ['/admin.php?library=1', 'Gestionar fotos', 'Fotos'],
        'categories' => ['/admin.php?categories=1', 'Categorías', 'Categorías'],
        'profile' => ['/admin.php?settings=1&amp;section=profile', 'Perfil', 'Perfil'],
        'design' => ['/admin.php?settings=1&amp;section=design', 'Diseño y textos', 'Diseño'],
    ];
    $links = static function (bool $short) use ($items, $active): string {
        $html = '';
        foreach ($items as $key => [$href, $label, $shortLabel]) {
            $html .= '<a class="admin-nav__link' . ($key === $active ? ' is-active' : '') . '" href="' . $href . '"'
                . ($key === $active ? ' aria-current="page"' : '') . '>' . adminIcon($key) . '<span>' . uploadEscape($short ? $shortLabel : $label) . '</span></a>';
        }
        return $html;
    };
    $csrf = uploadEscape((string) ($GLOBALS['adminShell']['csrf'] ?? ''));
    $brand = site_title();
    $mark = uploadEscape(mb_strtoupper(mb_substr($brand, 0, 1)));
    return '<body class="admin-body"><div class="admin-app">'
        . '<aside class="admin-sidebar"><a class="admin-brand" href="/admin.php"><span class="admin-brand__mark" aria-hidden="true">' . $mark . '</span>'
        . '<span class="admin-brand__text"><strong>' . uploadEscape($brand) . '</strong><small>Administración</small></span></a>'
        . '<nav class="admin-nav" aria-label="Administración">' . $links(false) . '</nav>'
        . '<p class="admin-sidebar__note">Área privada · ' . uploadEscape(site_title()) . '</p></aside>'
        . '<div class="admin-main"><header class="admin-topbar">'
        . '<a class="admin-brand" href="/admin.php"><span class="admin-brand__mark" aria-hidden="true">' . $mark . '</span>'
        . '<span class="admin-brand__text"><strong>' . uploadEscape($brand) . '</strong><small>Administración</small></span></a>'
        . '<span class="admin-topbar__title">' . uploadEscape($items[$active][1]) . '</span>'
        . '<div class="admin-topbar__actions"><button class="pwa-install-button" id="installAppButton" type="button" hidden>Instalar app</button>'
        . '<a class="admin-action" href="/" title="Volver a la web">' . adminIcon('external') . '<span class="admin-action__label">Ver la web</span></a>'
        . '<form method="post" action="/admin.php"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="' . $csrf . '">'
        . '<button class="admin-action" type="submit" title="Cerrar sesión">' . adminIcon('logout') . '<span class="admin-action__label">Cerrar sesión</span></button></form></div></header>'
        . '<main class="admin-content' . ($wide ? '' : ' admin-content--narrow') . '"><div class="admin-content__inner"><h1 class="admin-title">' . uploadEscape($title) . '</h1>' . $content . '</div></main></div>'
        . '<nav class="admin-tabbar" aria-label="Administración (móvil)">' . $links(true) . '</nav></div></body>';
}

/**
 * Dibuja una página completa del panel y termina la petición.
 *
 * Con armazón (si antes se llamó a adminNavigation()): menú y barras alrededor de `$content`.
 * Sin armazón (acceso, errores de configuración): tarjeta centrada.
 *
 * @param bool $wide true = contenido ancho (listados y ajustes); false = columna estrecha (formularios).
 */
function uploadPage(string $title, string $content, int $status = 200, bool $wide = false): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $version = uploadEscape(app_version());
    $body = isset($GLOBALS['adminShell'])
        ? adminShellHtml($title, $content, $wide)
        : '<body class="admin-body"><main class="upload-page"><section class="upload-panel"><div class="upload-brand">' . uploadEscape(site_title()) . '</div><p class="upload-kicker">Área privada · ' . uploadEscape(site_title()) . '</p><h1>' . uploadEscape($title) . '</h1>' . $content . '<button class="pwa-install-button" id="installAppButton" type="button" hidden>Instalar app</button></section></main></body>';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"><meta name="theme-color" content="#0a0a0a"><meta name="robots" content="noindex,nofollow"><meta name="app-version" content="' . uploadEscape(app_version()) . '"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="' . uploadEscape(site_title()) . '"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="manifest" href="/manifest.json?v=' . uploadEscape(app_version()) . '"><link rel="icon" type="image/png" sizes="32x32" href="/favicon.svg"><link rel="apple-touch-icon" sizes="180x180" href="/favicon.svg"><title>' . uploadEscape($title) . ' · ' . uploadEscape(site_title()) . '</title><link rel="stylesheet" href="/assets/css/style.css?v=' . uploadEscape(app_version()) . '"><link rel="stylesheet" href="/assets/css/photo-editor.css?v=' . uploadEscape(app_version()) . '"><link rel="stylesheet" href="/assets/css/photo-manager.css?v=' . uploadEscape(app_version()) . '"><script src="/assets/js/photo-filter-engine.js?v=' . uploadEscape(app_version()) . '" defer></script><script src="/assets/js/photo-editor-presets.js?v=' . uploadEscape(app_version()) . '" defer></script><script src="/assets/js/photo-editor.js?v=' . uploadEscape(app_version()) . '" defer></script><script src="/assets/js/photo-manager.js?v=' . uploadEscape(app_version()) . '" defer></script><link rel="stylesheet" href="/assets/css/admin.css?v=' . $version . '"><script src="/assets/js/admin-ui.js?v=' . $version . '" defer></script>' . site_client_texts() . '<script src="/assets/js/pwa.js?v=' . $version . '" defer></script></head>' . $body . '</html>';
    exit;
}

/**
 * Activa el armazón con menú para esta página. Se mantiene como primer elemento del contenido
 * (`adminNavigation($csrf) . ...`) por compatibilidad: no devuelve HTML, solo guarda el token CSRF
 * que usa el botón «Cerrar sesión» de la barra superior.
 */
function adminNavigation(string $csrf): string
{
    // Activa el armazón con menú; uploadPage() lo dibuja alrededor del contenido.
    $GLOBALS['adminShell'] = ['csrf' => $csrf];
    return '';
}
