<?php
declare(strict_types=1);

namespace Kcg\Themes;

use Kcg\Core\Registry;

/**
 * Familias tipográficas (themes/fonts/<id>.php) y parejas de títulos + texto (themes/pairs/<id>.php).
 *
 * Una familia declara su nombre y sus pesos; sus archivos son assets/fonts/<id>-latin.woff2 y <id>-latin-ext.woff2.
 * Una pareja apunta a dos familias por identificador. Las reglas @font-face se generan: las páginas públicas cargan
 * solo las familias de la pareja elegida; el panel carga todas para poder previsualizarlas.
 */
final class Fonts
{
    /** Subconjuntos de caracteres, en el orden en que se declaran. */
    private const SUBSETS = [
        'latin-ext' => 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C4,U+2113,U+2C60-2C7F,U+A720-A7FF',
        'latin' => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD',
    ];

    /** Grupos en que se clasifican las parejas (identificador => etiqueta del filtro del panel). */
    public const GROUPS = ['editorial' => 'Editoriales', 'modern' => 'Modernas', 'experimental' => 'Expresivas'];

    public static function families(): Registry
    {
        return Registry::for('fonts');
    }

    public static function pairsRegistry(): Registry
    {
        return Registry::for('pairs');
    }

    /**
     * Parejas con los nombres de familia ya resueltos: id => [name, heading, body, group].
     * Es la forma que usan el formulario y los atributos del <body>.
     */
    public static function pairs(): array
    {
        $families = self::families();
        $pairs = [];
        foreach (self::pairsRegistry()->all() as $id => $pair) {
            $pairs[$id] = [
                'name' => $pair['name'],
                'heading' => (string) ($families->get($pair['heading'])['name'] ?? $pair['heading']),
                'body' => (string) ($families->get($pair['body'])['name'] ?? $pair['body']),
                'group' => $pair['group'],
            ];
        }
        return $pairs;
    }

    /** Identificadores de las familias que usa una pareja (vacío si no existe). */
    public static function familiesOfPair(string $pairId): array
    {
        $pair = self::pairsRegistry()->get($pairId);
        return $pair === null ? [] : array_values(array_unique([$pair['heading'], $pair['body']]));
    }

    /** Reglas @font-face de las familias indicadas (o de todas si no se indica ninguna lista). */
    public static function fontFaceCss(?array $familyIds = null): string
    {
        $registry = self::families();
        $css = '';
        foreach ($familyIds ?? $registry->ids() as $id) {
            $family = $registry->get($id);
            if ($family === null) continue;
            foreach (self::SUBSETS as $subset => $range) {
                $css .= "@font-face { font-family:'" . $family['name'] . "'; font-style:normal; font-weight:" . $family['weights']
                    . "; font-display:swap; src:url('/assets/fonts/" . $id . '-' . $subset . ".woff2') format('woff2'); unicode-range:" . $range . "; }\n";
            }
        }
        return $css;
    }

    /** <style> con las fuentes de la pareja elegida, para el <head> de las páginas públicas. */
    public static function styleTag(string $pairId): string
    {
        $css = self::fontFaceCss(self::familiesOfPair($pairId));
        return $css === '' ? '' : '<style id="fonts-css">' . rtrim($css) . '</style>';
    }

    /** Ruta de archivo de una familia y subconjunto (para pruebas y comprobaciones). */
    public static function fontFile(string $familyId, string $subset): string
    {
        return dirname(__DIR__) . '/assets/fonts/' . $familyId . '-' . $subset . '.woff2';
    }

    /** @return list<string> subconjuntos que se declaran */
    public static function subsets(): array
    {
        return array_keys(self::SUBSETS);
    }
}
