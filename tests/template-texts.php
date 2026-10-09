<?php
declare(strict_types=1);

require dirname(__DIR__) . '/inc/site-settings.php';

function assert_template_text(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

$catalog = site_text_catalog();
$choices = site_design_choices();
$groups = site_template_text_groups();
$seen = [];
foreach ($groups as $group) {
    $setting = $group['setting'];
    assert_template_text(isset($choices[$setting]), 'Selector de plantilla desconocido: ' . $setting);
    foreach ($group['options'] as $option) {
        assert_template_text($option === '*' || isset($choices[$setting][$option]), 'Opción de plantilla desconocida: ' . $option);
    }
    foreach ($group['fields'] as $key => $label) {
        assert_template_text(isset($catalog[$key]), 'Texto de plantilla sin valor por defecto: ' . $key);
        assert_template_text(!isset($seen[$key]), 'Campo de plantilla repetido: ' . $key);
        assert_template_text(trim($label) !== '', 'Campo sin nombre descriptivo: ' . $key);
        $seen[$key] = true;
    }
}
assert_template_text(count($seen) >= 20, 'Faltan textos de las plantillas');
assert_template_text(in_array('coverflow', $groups['deck']['options'], true), 'Cover Flow debe mostrar los textos de ayuda y salida de su interfaz.');
assert_template_text(count(site_design_choices()['header_mobile']) === 9, 'No se revisaron las nueve cabeceras móviles');
assert_template_text(count(site_design_choices()['header_desktop']) === 9, 'No se revisaron las nueve cabeceras de escritorio');
assert_template_text(count(site_design_choices()['gallery_mobile']) === 10, 'No se revisaron los diez modos de galería');

$settings = site_settings_defaults();
$settings['header_mobile'] = 'atlas';
$settings['texts'] = ['template_atlas_label' => '<Atlas & mundo>'];
$saved = site_settings_validate($settings);
assert_template_text($saved['texts']['template_atlas_label'] === '<Atlas & mundo>', 'El texto editado no se guarda');
$form = site_settings_form('csrf', $saved, 'texts');
assert_template_text(str_contains($form, 'name="texts[template_atlas_label]"'), 'Falta el campo de Atlas en Textos');
assert_template_text(str_contains($form, '&lt;Atlas &amp; mundo&gt;'), 'El texto no se escapa en el panel');
assert_template_text(str_contains($form, 'data-template-options="atlas"'), 'El grupo no depende de la plantilla seleccionada');
assert_template_text(!str_contains($form, 'Estadísticas · nombre'), 'No deben aparecer etiquetas de estadísticas sin personalización útil');
assert_template_text(!str_contains($form, 'name="texts[text_7fe0e410821a1ee4]"'), 'La ruta interna de imágenes no se ofrece como texto editable');
assert_template_text(str_contains($form, 'Nombre de la web') && str_contains($form, 'Crédito del pie de página'), 'Faltan los campos de marca y copyright');

echo "Textos de plantillas y formulario verificados.\n";
