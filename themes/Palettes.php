<?php
declare(strict_types=1);

namespace Kcg\Themes;

use Kcg\Core\Registry;

/**
 * CSS de la paleta elegida, generado desde su definición en themes/palettes/<id>.php.
 *
 * Solo se genera la paleta activa (no las demás), así que añadir una paleta es crear un archivo y no hay nada que compilar.
 * Las reglas comunes a todas las paletas siguen en assets/css/site-design.css.
 */
final class Palettes
{
    /** Reglas que comparten las paletas de modo claro (`mode` => 'light'). `{p}` es el selector de la paleta. */
    private const LIGHT_RULES = [
        '{p} .hero__noise { opacity:.025; }',
        '{p} .contact { background:var(--bg-soft); }',
        '{p} .categories-filter__chip { color:var(--fg); }',
        '{p} .lightbox { background:var(--bg); color:var(--fg); }',
    ];

    public static function registry(): Registry
    {
        return Registry::for('palettes');
    }

    /** CSS de una paleta, o cadena vacía si no existe o no sobrescribe nada (la base). */
    public static function css(string $id): string
    {
        $palette = self::registry()->get($id);
        if ($palette === null || !preg_match('/^[a-z0-9-]+$/', $id)) return '';
        $selector = 'body[data-palette="' . $id . '"]';
        $declarations = '';
        foreach ($palette['vars'] as $name => $value) $declarations .= '--' . $name . ':' . $value . '; ';
        foreach ($palette['extra_vars'] ?? [] as $name => $value) $declarations .= '--' . $name . ':' . $value . '; ';
        $rules = [];
        if ($declarations !== '') $rules[] = $selector . ' { ' . rtrim($declarations) . ' }';
        if ($palette['mode'] === 'light') {
            foreach (self::LIGHT_RULES as $rule) $rules[] = str_replace('{p}', $selector, $rule);
        }
        foreach ($palette['css'] ?? [] as $rule) $rules[] = str_replace('{p}', $selector, $rule);
        return implode("\n", $rules);
    }

    /** Etiqueta <style> con el CSS de la paleta activa, lista para imprimir en el <head>; vacía si no hace falta. */
    public static function styleTag(string $id): string
    {
        $css = self::css($id);
        return $css === '' ? '' : '<style id="palette-css">' . $css . '</style>';
    }

    /** Colores de muestra (fondo, texto y acento) para el selector del panel. La paleta base usa los de assets/css/components/base.css. */
    public static function swatch(string $id): array
    {
        $vars = self::registry()->get($id)['vars'] ?? [];
        return [$vars['bg'] ?? '#0a0a0a', $vars['fg'] ?? '#f3f0ea', $vars['accent'] ?? '#d4a574'];
    }
}
