<?php
declare(strict_types=1);

/**
 * Parejas tipográficas curadas para fotografía. Se declaran una por archivo en themes/pairs/ y sus familias
 * (archivos WOFF2 locales en assets/fonts) en themes/fonts/ (ver themes/Fonts.php). El identificador, no un nombre
 * de fuente arbitrario, es el único valor que se acepta desde el formulario.
 */
function site_font_pairs(): array
{
    return \Kcg\Themes\Fonts::pairs();
}

function site_font_pair(string $id): array
{
    $pairs = site_font_pairs();
    return $pairs[$id] ?? $pairs['classic'];
}

/** <style> con las fuentes de la pareja elegida; va justo antes de assets/css/typography.css. */
function site_fonts_style_tag(): string
{
    return \Kcg\Themes\Fonts::styleTag(site_settings_load()['font_pair']);
}
