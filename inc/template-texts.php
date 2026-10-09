<?php
declare(strict_types=1);

/**
 * Campos de texto de las plantillas. Cada clave aparece una sola vez para que
 * los formularios de Perfil y Diseño no se pisen al guardarse.
 *
 * setting/options determinan cuándo se muestra el grupo en el panel.
 * '*' significa cualquier opción de ese selector.
 */
function site_template_text_groups(): array
{
    return [
        'mobile' => [
            'title' => 'Cabecera móvil · textos comunes a los 9 diseños',
            'setting' => 'header_mobile', 'options' => ['*'],
            'fields' => [
                'text_8aa8595771f39463' => 'Línea de edición junto al nombre',
                'text_577e559428a23438' => 'Estadísticas · nombre de las fotografías',
                'text_bb6db5ced87f3dfa' => 'Estadísticas · nombre de las categorías',
                'text_b5c772cf5b14f84e' => 'Nombre destacado en estadísticas e introducción',
                'text_ff1de97302308752' => 'Estadísticas · texto bajo la cifra destacada',
                'text_1d997c72b89b5e1f' => 'Accesibilidad · nombre de la cabecera de perfil',
                'text_84f085942c2a2aa1' => 'Accesibilidad · enlace para volver al inicio',
                'text_125ff372f2933b34' => 'Accesibilidad · descripción del logotipo',
                'text_7f9488ff977b7f67' => 'Accesibilidad · nombre de las estadísticas',
                'text_6ba397681d3ee9c1' => 'Accesibilidad · insignia de perfil verificado',
                'text_e5d843682b4f2596' => 'Accesibilidad · enlace de la web del perfil',
            ],
        ],
        'reel' => [
            'title' => 'Cine · tira de película',
            'setting' => 'header_mobile', 'options' => ['reel'],
            'fields' => ['template_reel_label' => 'Rótulo superior de Cine'],
        ],
        'atlas' => [
            'title' => 'Atlas · cuaderno cartográfico',
            'setting' => 'header_mobile', 'options' => ['atlas'],
            'fields' => [
                'template_atlas_label' => 'Título superior de Atlas',
                'template_atlas_photo_label' => 'Rótulo bajo cada fotografía de Atlas',
            ],
        ],
        'orbit' => [
            'title' => 'Órbita · cielo 3D',
            'setting' => 'header_mobile', 'options' => ['orbit'],
            'fields' => ['template_orbit_label' => 'Rótulo superior de Órbita'],
        ],
        'scrapbook' => [
            'title' => 'Álbum · recortes y postales',
            'setting' => 'header_mobile', 'options' => ['scrapbook'],
            'fields' => ['template_scrapbook_label' => 'Etiqueta de postales del Álbum'],
        ],
        'desktop' => [
            'title' => 'Cabecera de escritorio · textos comunes a los 9 diseños',
            'setting' => 'header_desktop', 'options' => ['*'],
            'fields' => [
                'text_b6b50664e231e202' => 'Frase superior de la portada',
                'text_cbbba360eb60dd04' => 'Título grande · primera línea',
                'text_2c1ac3057629f681' => 'Título grande · segunda línea',
                'text_30170303c506cf6c' => 'Nombre del autor · subtítulo y firma',
                'text_08e81d4e64f6b4ff' => 'Contador · singular de fotografía',
                'text_eefccddb211b22ea' => 'Galería vacía · frase inicial',
                'text_7fe0e410821a1ee4' => 'Galería vacía · nombre de la carpeta',
                'text_cc0ecea94d78a73f' => 'Galería vacía · frase final',
                'text_b2cec4172b3ff4d2' => 'Enlace para bajar a la galería',
                'text_83f4e59e2da59d76' => 'Accesibilidad · enlace del logotipo',
                'text_ddfe959388cbea71' => 'Accesibilidad · enlace para bajar a la galería',
            ],
        ],
        'photo' => [
            'title' => 'Fotografías · textos compartidos por todas las galerías',
            'setting' => 'gallery_premium', 'options' => ['*'],
            'fields' => [
                'categories_title' => 'Título del bloque de categorías',
                'text_c7ea52a379d46ca5' => 'Texto alternativo · inicio de la descripción de la foto',
                'text_70241acd5e85d5cc' => 'Texto alternativo · firma al final de la foto',
                'text_199d79e67ea29a83' => 'Accesibilidad · abrir la ficha de una foto',
                'text_7be0de9ca7203c46' => 'Accesibilidad · abrir una foto sin URL',
            ],
        ],
        'standard' => [
            'title' => 'Galería estándar · textos comunes a los 10 modos',
            'setting' => 'gallery_premium', 'options' => ['none'],
            'fields' => [
                'text_7d367bc92702d135' => 'Introducción · título',
                'text_c15e876671f50d92' => 'Introducción · texto antes del nombre destacado',
                'text_a9157ae01488ec67' => 'Introducción · texto después del nombre destacado',
                'text_5935e29992445803' => 'Accesibilidad · paginación',
            ],
        ],
        'premium_navigation' => [
            'title' => 'Galerías premium · controles compartidos',
            'setting' => 'gallery_premium', 'options' => ['deck', 'drum', 'cylinder', 'polaroid', 'swipe'],
            'fields' => [
                'deck_exit' => 'Botón para salir de la galería',
                'deck_exit_label' => 'Accesibilidad · salir y seguir viendo la web',
                'deck_exit_up_label' => 'Accesibilidad · salir hacia arriba',
                'deck_empty_filter' => 'Mensaje cuando el filtro no tiene fotos',
            ],
        ],
        'premium_cards' => [
            'title' => 'Baraja, Tambor, Cilindro y Polaroids · navegación',
            'setting' => 'gallery_premium', 'options' => ['deck', 'drum', 'cylinder', 'polaroid'],
            'fields' => [
                'deck_prev' => 'Accesibilidad · foto anterior',
                'deck_next' => 'Accesibilidad · foto siguiente',
                'deck_hint_touch' => 'Pista de navegación táctil',
                'deck_hint_wheel' => 'Pista de navegación con ratón o teclado',
            ],
        ],
        'deck' => [
            'title' => 'Galería premium · Estilo Baraja',
            'setting' => 'gallery_premium', 'options' => ['deck'],
            'fields' => ['deck_label' => 'Accesibilidad · nombre de la galería Baraja'],
        ],
        'drum' => [
            'title' => 'Galería premium · Estilo Tambor',
            'setting' => 'gallery_premium', 'options' => ['drum'],
            'fields' => ['drum_label' => 'Accesibilidad · nombre de la galería Tambor'],
        ],
        'cylinder' => [
            'title' => 'Galería premium · Estilo Cilindro',
            'setting' => 'gallery_premium', 'options' => ['cylinder'],
            'fields' => ['cylinder_label' => 'Accesibilidad · nombre de la galería Cilindro'],
        ],
        'polaroid' => [
            'title' => 'Galería premium · Estilo Polaroids',
            'setting' => 'gallery_premium', 'options' => ['polaroid'],
            'fields' => ['polaroid_label' => 'Accesibilidad · nombre de la galería Polaroids'],
        ],
        'bubbles' => [
            'title' => 'Galerías premium · Burbujas y Cuadrados',
            'setting' => 'gallery_premium', 'options' => ['bubbles', 'squares'],
            'fields' => [
                'bubbles_prev' => 'Accesibilidad · página anterior',
                'bubbles_next' => 'Accesibilidad · página siguiente',
                'bubbles_hint_touch' => 'Pista de navegación táctil',
                'bubbles_hint_wheel' => 'Pista de navegación con ratón o teclado',
                'bubbles_empty_filter' => 'Mensaje cuando el filtro no tiene fotos',
            ],
        ],
        'bubbles_name' => [
            'title' => 'Galería premium · Estilo Burbujas',
            'setting' => 'gallery_premium', 'options' => ['bubbles'],
            'fields' => ['bubbles_label' => 'Accesibilidad · nombre de la galería Burbujas'],
        ],
        'squares_name' => [
            'title' => 'Galería premium · Estilo Cuadrados',
            'setting' => 'gallery_premium', 'options' => ['squares'],
            'fields' => ['squares_label' => 'Accesibilidad · nombre de la galería Cuadrados'],
        ],
        'swipe' => [
            'title' => 'Galería premium · Estilo Tinder',
            'setting' => 'gallery_premium', 'options' => ['swipe'],
            'fields' => [
                'swipe_label' => 'Accesibilidad · nombre de la galería Tinder',
                'swipe_like_label' => 'Accesibilidad · dar corazón y avanzar',
                'swipe_pass_label' => 'Accesibilidad · pasar a la siguiente foto',
                'swipe_hint_touch' => 'Pista de navegación táctil',
                'swipe_hint_wheel' => 'Pista de navegación con ratón o teclado',
                'swipe_end' => 'Mensaje al terminar todas las fotos',
                'swipe_restart' => 'Botón para volver a empezar',
            ],
        ],
    ];
}
