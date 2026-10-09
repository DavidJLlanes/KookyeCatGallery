<?php
declare(strict_types=1);
function site_template_text_groups(): array
{
    return [
        'mobile_common' => ['title' => 'Cabecera móvil · presentación', 'setting' => 'header_mobile', 'options' => ['*'], 'fields' => ['text_8aa8595771f39463' => 'Frase breve junto al nombre', 'text_b5c772cf5b14f84e' => 'Lugar o tema destacado', 'text_ff1de97302308752' => 'Frase bajo el lugar destacado']],
        'reel' => ['title' => 'Cine · tira de película', 'setting' => 'header_mobile', 'options' => ['reel'], 'fields' => ['template_reel_label' => 'Rótulo de la tira de película']],
        'atlas' => ['title' => 'Atlas · cuaderno cartográfico', 'setting' => 'header_mobile', 'options' => ['atlas'], 'fields' => ['template_atlas_label' => 'Título del cuaderno', 'template_atlas_photo_label' => 'Rótulo que acompaña a las fotografías']],
        'orbit' => ['title' => 'Órbita · cielo 3D', 'setting' => 'header_mobile', 'options' => ['orbit'], 'fields' => ['template_orbit_label' => 'Rótulo del archivo celeste']],
        'scrapbook' => ['title' => 'Álbum · recortes y postales', 'setting' => 'header_mobile', 'options' => ['scrapbook'], 'fields' => ['template_scrapbook_label' => 'Texto de la postal']],
        'desktop' => ['title' => 'Cabecera de escritorio · presentación', 'setting' => 'header_desktop', 'options' => ['*'], 'fields' => ['text_b6b50664e231e202' => 'Frase breve de la portada', 'text_cbbba360eb60dd04' => 'Título principal', 'text_2c1ac3057629f681' => 'Segunda línea del título']],
        'empty_gallery' => ['title' => 'Inicio sin fotografías', 'setting' => 'header_desktop', 'options' => ['*'], 'fields' => ['template_empty_gallery_message' => 'Mensaje mientras no haya fotografías']],
        'categories' => ['title' => 'Categorías', 'setting' => 'gallery_premium', 'options' => ['*'], 'fields' => ['categories_title' => 'Título del bloque de categorías']],
        'standard_gallery' => ['title' => 'Galería estándar · introducción', 'setting' => 'gallery_premium', 'options' => ['none'], 'fields' => ['text_7d367bc92702d135' => 'Título de la introducción', 'text_c15e876671f50d92' => 'Texto antes del nombre del fotógrafo', 'text_a9157ae01488ec67' => 'Texto después del nombre del fotógrafo']],
        'deck' => ['title' => 'Galería premium · navegación a pantalla completa', 'setting' => 'gallery_premium', 'options' => ['deck', 'drum', 'cylinder', 'polaroid'], 'fields' => ['deck_exit' => 'Texto del botón para salir', 'deck_hint_touch' => 'Ayuda para avanzar en móvil', 'deck_hint_wheel' => 'Ayuda para avanzar con ratón o teclado', 'deck_empty_filter' => 'Mensaje si un filtro no encuentra fotos']],
        'bubbles' => ['title' => 'Galería premium · Burbujas y Cuadrados', 'setting' => 'gallery_premium', 'options' => ['bubbles', 'squares'], 'fields' => ['bubbles_hint_touch' => 'Ayuda para avanzar en móvil', 'bubbles_hint_wheel' => 'Ayuda para avanzar con ratón o teclado', 'bubbles_empty_filter' => 'Mensaje si un filtro no encuentra fotos']],
        'swipe' => ['title' => 'Galería premium · Deslizamiento', 'setting' => 'gallery_premium', 'options' => ['swipe'], 'fields' => ['swipe_hint_touch' => 'Ayuda para deslizar en móvil', 'swipe_hint_wheel' => 'Ayuda para avanzar con ratón o teclado', 'swipe_end' => 'Mensaje al llegar al final de la galería', 'swipe_restart' => 'Texto del botón para volver a empezar']],
    ];
}
