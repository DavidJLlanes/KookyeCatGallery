<?php
declare(strict_types=1);

// Todos los bloques de la web pública adoptan la paleta elegida: ningún estilo público lleva el dorado ni el crema de la paleta original
// escritos a mano (se usa var(--accent), var(--fg)… o color-mix con ellos). Solo base.css los define (son los valores de la paleta por
// defecto) y las pantallas del panel quedan fuera. Los fondos negros de los velos sobre fotos (lightbox, pie de foto) son a propósito.
// Uso: php tests/palette-colors.php

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
$files = array_merge(glob($root . '/assets/css/components/*.css') ?: [], glob($root . '/assets/css/worlds/*.css') ?: [],
    glob($root . '/assets/css/{gallery-layout,photo-navigation,fab-menu,microblog,microblog-composer}.css', GLOB_BRACE) ?: []);
$allowed = ['base.css', 'admin-access.css'];
$forbidden = '/rgba\(\s*(?:212,\s*165,\s*116|227,\s*184,\s*137|243,\s*240,\s*234)\s*,|#(?:d4a574|e3b889|e2b98c|c9a27a|f3f0ea|f5f1eb|f4f1ed)\b/i';
foreach ($files as $file) {
    if (in_array(basename($file), $allowed, true)) continue;
    foreach (file($file) as $number => $line) {
        // Los mundos de cabecera definen su propia paleta «current» (--bg, --fg…): ahí los colores son valores de paleta.
        if (str_contains($line, '[data-palette="current"]')) continue;
        check(!preg_match($forbidden, $line), basename($file) . ':' . ($number + 1) . ' lleva un color de la paleta original escrito a mano; usa var(--accent), var(--fg) o color-mix().');
    }
}

// Los fondos de superficie (cabeceras, mapa, botones, avisos) vienen de la paleta.
foreach (['components/hero.css' => '#050505', 'components/preloader.css' => '#050505', 'components/project.css' => '#050505', 'components/mobile-profile.css' => '#151411', 'components/pwa.css' => '#12110f', 'components/photo-actions.css' => 'rgba(15,14,13'] as $name => $literal) {
    check(!str_contains((string) file_get_contents($root . '/assets/css/' . $name), $literal), "$name vuelve a llevar el fondo fijo $literal.");
}
echo "Palette colors tests passed.\n";
