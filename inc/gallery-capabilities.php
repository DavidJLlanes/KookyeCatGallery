<?php
declare(strict_types=1);

/** One contract for the admin controls and the server's save operation.
 * Geometry lives in gallery-layout.js; every new layout must declare its options here.
 */
function site_gallery_capabilities(): array
{
    $paged = ['columns' => true, 'photos' => true, 'grid' => false, 'pagination' => true];
    return [
        'standard' => $paged,
        'grid' => array_replace($paged, ['grid' => true]),
        'mosaic' => $paged,
        'asymmetric' => $paged,
        'category-rails' => ['columns' => false, 'photos' => false, 'grid' => false, 'pagination' => false],
        'scattered' => $paged,
        'exhibition' => array_replace($paged, ['columns' => false]),
        'contact-sheet' => array_replace($paged, ['grid' => true]),
        'narrative' => $paged,
        'triptych' => array_replace($paged, ['columns' => false]),
    ];
}

function site_gallery_control_keys(): array
{
    return site_standard_gallery_settings();
}

/** Shared settings apply if either device uses them; device settings are independent. */
function site_gallery_control_enabled(string $key, array $settings): bool
{
    if (!in_array($key, site_gallery_control_keys(), true)) return true;
    $premium = site_premium_gallery_active($settings);
    if ($premium !== null) return !in_array($key, site_premium_ignored_settings($premium), true);
    if (str_starts_with($key, 'gallery_')) return true;
    $matrix = site_gallery_capabilities();
    $supports = static function (string $device, string $option) use ($matrix, $settings): bool {
        $layout = $settings['gallery_' . $device] ?? 'standard';
        return !empty($matrix[$layout][$option]);
    };
    if ($key === 'grid') return $supports('mobile', 'grid') || $supports('desktop', 'grid');
    if ($key === 'pagination_shape') {
        return ($supports('mobile', 'pagination') && (int) ($settings['photos_mobile'] ?? 12) !== 0)
            || ($supports('desktop', 'pagination') && (int) ($settings['photos_desktop'] ?? 20) !== 0);
    }
    [$option, $device] = explode('_', $key, 2);
    return $supports($device, $option);
}

/** Keep dormant values, even if a crafted request submits an incompatible setting. */
function site_gallery_preserve_inactive(array $input, array $saved): array
{
    $selection = array_replace($saved, $input);
    foreach (site_gallery_control_keys() as $key) {
        if (!site_gallery_control_enabled($key, $selection)) $input[$key] = $saved[$key];
    }
    return $input;
}

function site_gallery_control_help(string $key, array $settings): string
{
    if (!site_gallery_control_enabled($key, $settings)) {
        return 'Este ajuste no se aplica al diseño seleccionado. Su valor se conserva.';
    }
    if (str_starts_with($key, 'photos_') && in_array(site_premium_gallery_active($settings), ['bubbles', 'squares'], true)) {
        return 'Máximo por página: si no caben sin solaparse, se muestran menos y el resto pasa a la siguiente página. «Todas» usa la capacidad de la pantalla.';
    }
    return '';
}
