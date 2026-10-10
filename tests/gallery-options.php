<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/site-settings.php';

function expect(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

$defaults = site_settings_defaults();
$layouts = array_keys(site_design_choices()['gallery_mobile']);
expect(count($layouts) === 10, 'Every standard layout must be covered.');
expect(array_keys(site_gallery_capabilities()) === $layouts, 'Every selectable layout must declare capabilities.');
foreach ($layouts as $mobile) foreach ($layouts as $desktop) {
    $settings = array_replace($defaults, ['gallery_mobile' => $mobile, 'gallery_desktop' => $desktop]);
    foreach (['mobile' => $mobile, 'desktop' => $desktop] as $device => $layout) {
        expect(site_gallery_control_enabled('columns_' . $device, $settings)
            === !in_array($layout, ['category-rails', 'exhibition', 'triptych'], true), "$layout: columns");
        expect(site_gallery_control_enabled('photos_' . $device, $settings)
            === ($layout !== 'category-rails'), "$layout: photo count");
    }
    expect(site_gallery_control_enabled('grid', $settings)
        === (in_array($mobile, ['grid', 'contact-sheet'], true) || in_array($desktop, ['grid', 'contact-sheet'], true)), 'Shared ratio');
    expect(site_gallery_control_enabled('pagination_shape', $settings)
        === ($mobile !== 'category-rails' || $desktop !== 'category-rails'), 'Shared pagination');
    $all = array_replace($settings, ['photos_mobile' => 0, 'photos_desktop' => 0]);
    expect(!site_gallery_control_enabled('pagination_shape', $all), 'All photos have no page buttons.');
    $forged = array_replace($settings, ['grid' => 'portrait', 'columns_mobile' => 4, 'columns_desktop' => 10]);
    $kept = site_gallery_preserve_inactive($forged, $settings);
    foreach (site_gallery_control_keys() as $key) {
        if (!site_gallery_control_enabled($key, $forged)) expect($kept[$key] === $settings[$key], 'Dormant value changed: ' . $key);
    }
    expect(site_settings_validate($kept) === $kept, 'Compatible settings must round trip.');
}
foreach (site_premium_gallery_definitions() as $premium => $definition) {
    $settings = array_replace($defaults, ['gallery_premium' => $premium]);
    foreach (site_gallery_control_keys() as $key) {
        $expected = in_array($premium, ['bubbles', 'squares'], true) && str_starts_with($key, 'photos_');
        expect(site_gallery_control_enabled($key, $settings) === $expected, "$premium / $key");
    }
    $form = site_settings_form('test', $settings, 'design');
    foreach (site_gallery_control_keys() as $key) {
        preg_match('/<select[^>]+name="' . $key . '"[^>]*>/', $form, $matches);
        expect(isset($matches[0]), 'Missing field: ' . $key);
        expect(str_contains($matches[0], ' disabled') === !site_gallery_control_enabled($key, $settings), 'Initial HTML disagrees with save rules.');
    }
    expect(site_gallery_control_enabled('hover', $settings), 'Hover must remain enabled.');
}

// Real atomic save: switching designs must not reset dormant configuration.
$path = sys_get_temp_dir() . '/gallery-options-' . bin2hex(random_bytes(8)) . '.json';
try {
    $saved = array_replace($defaults, ['gallery_mobile' => 'grid', 'gallery_desktop' => 'grid', 'grid' => 'landscape',
        'columns_mobile' => 4, 'columns_desktop' => 5, 'photos_mobile' => 8, 'photos_desktop' => 15]);
    site_settings_save($saved, $path);
    $premium = site_gallery_preserve_inactive(['gallery_premium' => 'swipe', 'grid' => 'portrait', 'photos_mobile' => 100], $saved);
    site_settings_save(array_replace($saved, $premium), $path);
    $loaded = site_settings_load($path);
    expect($loaded['grid'] === 'landscape' && $loaded['photos_mobile'] === 8 && $loaded['columns_desktop'] === 5, 'Saved values were reset.');
    $loaded['gallery_premium'] = 'none';
    site_settings_save($loaded, $path);
    expect(site_settings_load($path)['photos_desktop'] === 15, 'Returning to standard must restore stored counts.');
} finally { if (is_file($path)) unlink($path); }
echo "Gallery options passed: 100 mobile/desktop combinations, 8 premium designs, dormant values and atomic save.\n";
