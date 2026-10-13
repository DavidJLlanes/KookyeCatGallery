<?php
/**
 * Plantilla de `installation.php`: lo que distingue a tu web del núcleo.
 *
 * Copia este archivo como `installation.php` (en la raíz pública, junto a index.php) y deja solo lo que necesites:
 * todas las claves son opcionales. Sin `installation.php` el núcleo usa sus valores por defecto: la carpeta privada
 * `config/` hermana de la raíz pública (o `var/`), la dirección guardada en `config/public-url.txt` y los textos
 * genéricos. No guardes aquí contraseñas ni claves de API. Significado de cada clave: core/Installation.php.
 */

return [
    // Dirección pública con https y sin barra final.
    // 'site_url' => 'https://example.com',

    // Carpetas y archivos privados (fuera de la raíz pública si el hosting lo permite).
    // 'private_dir'    => '/ruta/privada/config',
    // 'microblog_dir'  => '/ruta/privada/microblog',
    // 'hearts_file'    => '/ruta/privada/hearts.json',
    // 'ai_config_file' => '/ruta/privada/config.php',

    // Nombres de la sesión del panel y de la cookie de corazones (cámbialos solo si ya tienes visitantes con la anterior).
    // 'session_name'  => 'kookye_gallery_admin',
    // 'hearts_cookie' => 'kookye_gallery_hearts',

    // Lugar de la web: etiquetas geográficas y datos estructurados para buscadores. Sin esta clave no se publica ninguno.
    // 'place' => [
    //     'name' => 'Ciudad, País', 'locality' => 'Ciudad', 'region' => 'Región', 'country' => 'ES', 'geo_region' => 'ES-XX',
    //     'latitude' => 0.0, 'longitude' => 0.0,
    //     'gallery_description' => 'Colección de fotografías de ...',
    // ],

    // Clasificación automática de fotos sin categoría (palabras del título o la descripción) y coordenadas por palabra clave.
    // 'category_keywords'   => ['Paisajes' => ['paisaje', 'valle']],
    // 'location_coords'     => ['catedral' => [40.627, -5.5898]],
    // 'default_coordinates' => [40.0, -5.0],

    // Valores iniciales propios: ajustes (profile_image, logo_image, social_links, show_map...) y textos (id => texto).
    // 'setting_defaults' => ['show_map' => true],
    // 'text_defaults'    => ['text_52179dc42df7efe5' => 'Nombre de mi web'],
];
