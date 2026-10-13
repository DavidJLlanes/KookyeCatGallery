<?php
declare(strict_types=1);

use Kcg\Core\Settings\Schema;
use Kcg\Core\Settings\Store;
use Kcg\Core\Settings\Validator;

/**
 * Ajustes del sitio. La lógica vive en core/Settings/ (esquema, validación, almacén y migraciones); este archivo
 * conserva las funciones de siempre como puerta de entrada, más lo que aún es específico de la web pública
 * (atributos del <body>, CSS de la paleta, textos para el JS).
 *
 * Archivos hermanos (todos se cargan desde aquí):
 *   inc/site-sections.php       bloques de la portada: definiciones, interruptores y orden
 *   inc/site-text-api.php       textos editables: catálogo y lectura por clave
 *   inc/site-pages.php          páginas de texto editables
 *   inc/site-badges.php         insignias de verificación (themes/badges)
 *   inc/gallery-collections.php colecciones de la galería
 *   inc/site-settings-form.php  formularios del panel
 *   inc/premium-galleries.php   galerías premium
 */

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once __DIR__ . '/premium-galleries.php';
require_once __DIR__ . '/gallery-capabilities.php';
require_once __DIR__ . '/template-texts.php';
require_once __DIR__ . '/site-brand-assets.php';
require_once __DIR__ . '/site-typography.php';
require_once __DIR__ . '/site-sections.php';
require_once __DIR__ . '/site-text-api.php';
require_once __DIR__ . '/site-pages.php';
require_once __DIR__ . '/site-badges.php';
require_once __DIR__ . '/gallery-collections.php';
require_once __DIR__ . '/site-assets.php';

/**
 * Carpeta de la configuración privada (variable SITE_CONFIG_DIR; sin ella, la del VPS). Ver Store::configDir().
 */
function site_config_dir(): string
{
    return Store::configDir();
}

/**
 * Ruta del JSON de configuración.
 */
function site_settings_path(): string
{
    return Store::path();
}

/**
 * Opciones permitidas para cada ajuste de diseño: clave => [valor => etiqueta]. Es la lista blanca
 * que usa site_settings_validate(). Sale del esquema (core/Settings/Schema.php).
 */
function site_design_choices(): array
{
    return Schema::choices();
}

/**
 * Diseños estándar que aplican la proporción elegida en «Tipo de cuadrícula».
 * Las dos galerías (móvil y escritorio) comparten el mismo ajuste, por eso basta
 * con que una de ellas sea compatible para mantenerlo disponible.
 */
function site_grid_setting_layouts(): array
{
    return array_keys(array_filter(site_gallery_capabilities(), static fn(array $options): bool => $options['grid']));
}

function site_grid_setting_is_used(array $settings): bool
{
    return site_gallery_control_enabled('grid', $settings);
}

/**
 * Fotos visibles a la vez por pantalla (0 = todas). Es independiente del número de columnas; main.js
 * pagina con este valor.
 */
function site_photos_per_view_choices(): array
{
    return [6, 8, 9, 10, 12, 15, 16, 18, 20, 24, 30, 36, 40, 50, 60, 100, 0];
}

/**
 * Redes disponibles en el editor y en la portada. Instagram y Threads conservan sus marcas CSS actuales.
 */
function site_social_networks(): array
{
    return ['instagram'=>'Instagram', 'threads'=>'Threads', 'facebook'=>'Facebook', 'x'=>'X', 'youtube'=>'YouTube',
        'tiktok'=>'TikTok', 'flickr'=>'Flickr', 'linkedin'=>'LinkedIn', 'pinterest'=>'Pinterest', '500px'=>'500px',
        'bluesky'=>'Bluesky', 'mastodon'=>'Mastodon'];
}

/**
 * Valores iniciales de todos los ajustes. Un archivo antiguo al que le falte una clave hereda estos valores.
 */
function site_settings_defaults(): array
{
    return Schema::defaults();
}

/**
 * Cuenta caracteres UTF-8 sin exigir que mbstring esté instalado en el hosting.
 */
function site_utf8_length(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $length = preg_match_all('/./us', $value, $matches);
    return $length === false ? PHP_INT_MAX : $length;
}

/**
 * Valida y normaliza los ajustes recibidos (formulario o JSON). Lanza InvalidArgumentException si
 * algo no es válido; devuelve siempre un conjunto completo de ajustes. Ver Validator.
 */
function site_settings_validate(array $input): array
{
    return Validator::validate($input);
}

/**
 * Lee los ajustes guardados. Si el archivo no existe o está dañado, devuelve los valores por defecto.
 */
function site_settings_load(?string $path = null): array
{
    return Store::load($path);
}

/**
 * Valida y guarda los ajustes de forma atómica (archivo temporal + rename). Lanza RuntimeException
 * si no puede escribir.
 */
function site_settings_save(array $input, ?string $path = null): void
{
    Store::save($input, $path);
}

/**
 * Atributos del <body> (data-* y variables CSS) que activan en el CSS y el JS los ajustes de diseño,
 * de visibilidad y de columnas. Los ajustes con `body` en el esquema llegan solos.
 */
function site_design_attributes(): string
{
    $settings = site_settings_load();
    $attributes = '';
    // Con una galería premium activa los ajustes estándar de galería no se aplican: el <body> los marca como «premium».
    $premium = site_premium_gallery_active($settings);
    foreach (Schema::bodyChoiceKeys() as $key) {
        $value = $premium !== null && !empty(Schema::definitions()[$key]['premium_mark']) ? 'premium' : $settings[$key];
        $attributes .= ' data-' . str_replace('_', '-', $key) . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
    }
    foreach (Schema::toggleKeys() as $key) {
        $attributes .= ' data-' . str_replace('_', '-', $key) . '="' . ($settings[$key] ? 'true' : 'false') . '"';
    }
    $attributes .= ' data-photos-mobile="' . (int) $settings['photos_mobile'] . '" data-photos-desktop="' . (int) $settings['photos_desktop'] . '"';
    $font = site_font_pair($settings['font_pair']);
    $heading = "'" . $font['heading'] . "',Georgia,serif";
    $body = "'" . $font['body'] . "',system-ui,sans-serif";
    return $attributes . ' style="--columns-mobile:' . $settings['columns_mobile'] . ';--columns-desktop:' . $settings['columns_desktop']
        . ';--site-heading-font:' . $heading . ';--site-body-font:' . $body
        . ';--serif:var(--site-heading-font);--sans:var(--site-body-font)"';
}

/**
 * <style> con el CSS de la paleta elegida (themes/palettes/). Va justo antes de assets/css/site-design.css.
 */
function site_palette_style_tag(): string
{
    return \Kcg\Themes\Palettes::styleTag(site_settings_load()['palette']);
}

/**
 * Textos editables para el JS: JSON incrustado más site-texts.js.
 */
function site_client_texts(): string
{
    $texts = [];
    foreach (site_text_catalog() as $key => $entry) $texts[$entry['default']] = site_text($key);
    // El JavaScript del núcleo lleva los textos por defecto del núcleo: se traducen también al texto de esta instalación.
    foreach (site_text_core_catalog() as $key => $entry) $texts[$entry['default']] ??= site_text($key);
    return '<script id="siteTexts" type="application/json">'
        . json_encode($texts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
        . '</script><script src="/assets/js/site-texts.js?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/assets/js/site-texts.js'), 0, 12) . '"></script>';
}

// Formularios del panel (solo HTML), en su propio archivo.
require_once __DIR__ . '/site-settings-form.php';
