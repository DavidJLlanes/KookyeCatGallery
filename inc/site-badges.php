<?php
declare(strict_types=1);

use Kcg\Core\Registry;

/**
 * Insignias de verificación (themes/badges/*.php, una por archivo): la marca junto al nombre en la cabecera de perfil.
 * Se pueden mostrar u ocultar (`show_verified_badge`) y elegir (`verified_badge`). Se dibujan en SVG con los colores de la
 * paleta elegida (assets/css/components/verified-badge.css). (Cargado por inc/site-settings.php.)
 */

/**
 * HTML de la insignia elegida, o cadena vacía si está oculta. Sin parámetros usa los ajustes guardados.
 *
 * @param array<string, mixed>|null $settings
 */
function site_verified_badge_html(?array $settings = null, ?string $label = null, ?string $title = null): string
{
    $settings ??= site_settings_load();
    if (empty($settings['show_verified_badge'])) return '';
    $registry = Registry::for('badges');
    $id = $registry->has((string) ($settings['verified_badge'] ?? '')) ? (string) $settings['verified_badge'] : 'check';
    $badge = $registry->get($id);
    if ($badge === null) return '';
    $escape = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $label ??= site_text('text_6ba397681d3ee9c1');
    return '<span class="verified-badge verified-badge--' . $escape($id) . '" role="img" aria-label="' . $escape($label) . '"'
        . ($title !== null ? ' title="' . $escape($title) . '"' : '') . '>'
        . '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $badge['svg'] . '</svg></span>';
}

/** Vista previa de una insignia para el selector del panel (siempre visible, sin depender del interruptor). */
function site_verified_badge_preview(string $id): string
{
    return site_verified_badge_html(['show_verified_badge' => true, 'verified_badge' => $id], (string) (Registry::for('badges')->get($id)['name'] ?? $id));
}
