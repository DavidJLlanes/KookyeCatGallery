<?php
declare(strict_types=1);

namespace Kcg\Core\Settings;

use Kcg\Core\Registry;

/**
 * Esquema de los ajustes del sitio: una sola declaración de cada ajuste.
 *
 * Cada entrada dice de qué tipo es, su valor por defecto, qué opciones admite, en qué sección del panel se edita
 * y si llega al <body> como atributo data-*. De aquí salen los valores por defecto, la validación (Validator),
 * la lista blanca de opciones del formulario y los atributos del <body>; no hay otra lista que mantener.
 *
 * Tipos:
 *   choice      una opción de un catálogo (`choices`: id => etiqueta). Si falta, se rechaza.
 *   bool        interruptor (acepta true/false, 1/0, yes/no, on/off…).
 *   int-range   entero entre `min` y `max`.
 *   int-choice  entero de una lista cerrada (`options`).
 *   order       orden de los bloques de la portada (se normaliza, nunca falla).
 *   title       título de la web (texto de una línea, vacío = el de por defecto).
 *   text-map    textos editables por clave (catálogo en inc/site-texts.php).
 *   html-map    contenido HTML de las páginas editables (se sanea).
 *   path        ruta de imagen dentro de la web.
 *   https-url   dirección https:// (puede estar vacía).
 *   social-list lista de perfiles de redes sociales.
 *
 *   version     versión del formato del archivo (la fija el sistema, no se edita).
 *
 * Opcionales: `error` (mensaje si el valor no es válido), `label` (nombre en el panel), `section` (design, content, social, texts, pages), `body` (`data` = atributo
 * data-<clave>; `toggle` = data-<clave> true/false), `premium_mark` (el <body> lo marca «premium» mientras haya una galería premium
 * activa) y `gallery` (posición, 1…n, entre los ajustes de la galería
 * estándar, que una galería premium puede ignorar).
 *
 * El orden de las entradas es el de los valores por defecto (y el de las claves en settings.json).
 */
final class Schema
{
    /** Versión actual del archivo de ajustes: una más por cada migración de core/Settings/migrations/. */
    public static function currentVersion(): int
    {
        return Migrations::latest();
    }

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        static $definitions;
        if ($definitions !== null) return $definitions;

        // Interruptores que no son de un bloque de la portada (cabeceras e insignia de verificación): van en Diseño.
        $headerToggles = ['show_header_mobile', 'show_header_desktop', 'show_verified_badge'];
        $sectionToggles = array_values(array_diff(\site_toggle_keys(), $headerToggles));
        $registry = static fn(string $type): \Closure => static fn(): array => Registry::for($type)->choices();
        $galleries = static fn(string $device): \Closure => static fn(): array => Registry::for('galleries')->choices(
            static fn(array $gallery): bool => in_array($device, $gallery['devices'], true));

        $list = [
            'settings_version' => ['type' => 'version', 'default' => static fn(): int => Migrations::latest(), 'section' => 'system'],
            'palette' => ['type' => 'choice', 'default' => 'current', 'section' => 'design', 'label' => 'Paleta de colores', 'choices' => $registry('palettes'), 'body' => 'data'],
            'font_pair' => ['type' => 'choice', 'default' => 'classic', 'section' => 'design', 'label' => 'Pareja de tipografías',
                'choices' => static fn(): array => array_map(static fn(array $pair): string => $pair['name'], \site_font_pairs()), 'body' => 'data'],
            'grid' => ['type' => 'choice', 'default' => 'adaptive', 'section' => 'design', 'label' => 'Proporción de las fotos', 'choices' => $registry('grids'), 'body' => 'data', 'gallery' => 1, 'premium_mark' => true],
            'columns_mobile' => ['type' => 'int-range', 'default' => 3, 'section' => 'design', 'label' => 'Columnas en móvil', 'min' => 1, 'max' => 4, 'error' => 'El número de columnas debe estar entre 1 y 4.', 'gallery' => 5],
            'columns_desktop' => ['type' => 'int-range', 'default' => 3, 'section' => 'design', 'label' => 'Columnas en escritorio', 'min' => 1, 'max' => 10, 'error' => 'El número de columnas debe estar entre 1 y 10.', 'gallery' => 6],
            'photos_mobile' => ['type' => 'int-choice', 'default' => 12, 'section' => 'design', 'label' => 'Fotos visibles a la vez en móvil', 'error' => 'Selecciona un número válido de fotos visibles.', 'options' => static fn(): array => \site_photos_per_view_choices(), 'gallery' => 7],
            'photos_desktop' => ['type' => 'int-choice', 'default' => 20, 'section' => 'design', 'label' => 'Fotos visibles a la vez en escritorio', 'error' => 'Selecciona un número válido de fotos visibles.', 'options' => static fn(): array => \site_photos_per_view_choices(), 'gallery' => 8],
            'header_mobile' => ['type' => 'choice', 'default' => 'current', 'section' => 'design', 'label' => 'Cabecera móvil', 'choices' => $registry('headers-mobile'), 'body' => 'data'],
            'verified_badge' => ['type' => 'choice', 'default' => 'check', 'section' => 'design', 'label' => 'Insignia de verificación', 'choices' => $registry('badges'), 'body' => 'data'],
            'header_desktop' => ['type' => 'choice', 'default' => 'current', 'section' => 'design', 'label' => 'Cabecera de escritorio', 'choices' => $registry('headers-desktop'), 'body' => 'data'],
            'gallery_mobile' => ['type' => 'choice', 'default' => 'standard', 'section' => 'design', 'label' => 'Galería móvil', 'choices' => $galleries('mobile'), 'body' => 'data', 'gallery' => 2, 'premium_mark' => true],
            'gallery_desktop' => ['type' => 'choice', 'default' => 'standard', 'section' => 'design', 'label' => 'Galería de escritorio', 'choices' => $galleries('desktop'), 'body' => 'data', 'gallery' => 3, 'premium_mark' => true],
            'hover' => ['type' => 'choice', 'default' => 'soft', 'section' => 'design', 'label' => 'Efecto Hover', 'choices' => $registry('hover'), 'body' => 'data'],
            'pagination_shape' => ['type' => 'choice', 'default' => 'circle', 'section' => 'design', 'label' => 'Forma de la paginación', 'choices' => $registry('pagination-shapes'), 'body' => 'data', 'gallery' => 4],
            'gallery_premium' => ['type' => 'choice', 'default' => 'none', 'section' => 'design', 'label' => 'Galería premium', 'choices' => static fn(): array => \site_premium_gallery_choices(), 'body' => 'data'],
            'section_order' => ['type' => 'order', 'default' => static fn(): array => array_keys(\site_section_labels()), 'section' => 'content', 'label' => 'Orden de los bloques'],
        ];
        foreach (array_merge($headerToggles, $sectionToggles) as $key) {
            $list[$key] = ['type' => 'bool', 'default' => !in_array($key, ['show_map', 'show_microblog', 'show_social'], true), 'section' => in_array($key, $headerToggles, true) ? 'design' : 'content', 'body' => 'toggle'];
        }
        $list += [
            'texts' => ['type' => 'text-map', 'default' => [], 'section' => 'texts', 'label' => 'Textos'],
            'pages' => ['type' => 'html-map', 'default' => [], 'section' => 'pages', 'label' => 'Páginas de texto'],
            'profile_image' => ['type' => 'path', 'default' => '/profile-placeholder.svg', 'section' => 'content', 'label' => 'Imagen de perfil'],
            'logo_image' => ['type' => 'path', 'default' => '/favicon.svg', 'section' => 'content', 'label' => 'Logotipo'],
            'profile_website_url' => ['type' => 'https-url', 'default' => 'https://example.com', 'section' => 'social', 'label' => 'Web personal'],
            'social_links' => ['type' => 'social-list', 'section' => 'social', 'label' => 'Redes sociales', 'default' => []],
        ];
        // Valores iniciales propios de la instalación (installation.php) sobre los del núcleo.
        foreach (\Kcg\Core\Installation::settingDefaults() as $key => $value) {
            if (isset($list[$key])) $list[$key]['default'] = $value;
        }
        return $definitions = $list;
    }

    public static function has(string $key): bool
    {
        return isset(self::definitions()[$key]);
    }

    /** Valores iniciales de todos los ajustes (un archivo antiguo al que le falte una clave hereda estos). */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::definitions() as $key => $definition) {
            $default = $definition['default'];
            $defaults[$key] = $default instanceof \Closure ? $default() : $default;
        }
        return $defaults;
    }

    /** Opciones permitidas de los ajustes de tipo choice: clave => [valor => etiqueta]. */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::definitions() as $key => $definition) {
            if ($definition['type'] === 'choice') $choices[$key] = ($definition['choices'])();
        }
        return $choices;
    }

    /** Etiqueta de un ajuste en el panel. */
    public static function label(string $key): string
    {
        return (string) (self::definitions()[$key]['label'] ?? $key);
    }

    /** Claves de los ajustes de tipo `bool`, en el orden en que se emiten al <body> (el de site_toggle_keys()). */
    public static function toggleKeys(): array
    {
        return \site_toggle_keys();
    }

    /** Claves que llegan al <body> como data-<clave>, en orden de esquema. */
    public static function bodyChoiceKeys(): array
    {
        return array_keys(array_filter(self::definitions(), static fn(array $d): bool => ($d['body'] ?? null) === 'data'));
    }

    /** Claves de los ajustes de la galería estándar (los que una galería premium puede ignorar). */
    public static function galleryKeys(): array
    {
        $keys = array_filter(self::definitions(), static fn(array $d): bool => !empty($d['gallery']));
        uasort($keys, static fn(array $x, array $y): int => $x['gallery'] <=> $y['gallery']);
        return array_keys($keys);
    }
}
