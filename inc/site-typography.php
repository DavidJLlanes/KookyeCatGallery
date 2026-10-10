<?php
declare(strict_types=1);

/**
 * Parejas tipográficas curadas para fotografía. Las familias son archivos WOFF2
 * locales en assets/fonts; el identificador, no un nombre de fuente arbitrario,
 * es el único valor que se acepta desde el formulario.
 */
function site_font_pairs(): array
{
    return [
        'classic' => ['name' => 'Editorial clásico', 'heading' => 'Cormorant Garamond', 'body' => 'Inter', 'group' => 'editorial'],
        'luminous' => ['name' => 'Galería luminosa', 'heading' => 'Playfair Display', 'body' => 'DM Sans', 'group' => 'editorial'],
        'essay' => ['name' => 'Ensayo fotográfico', 'heading' => 'Fraunces', 'body' => 'Source Sans 3', 'group' => 'editorial'],
        'intimate' => ['name' => 'Colección íntima', 'heading' => 'EB Garamond', 'body' => 'Manrope', 'group' => 'editorial'],
        'photobook' => ['name' => 'Fotolibro actual', 'heading' => 'Source Serif 4', 'body' => 'Outfit', 'group' => 'editorial'],
        'landscape' => ['name' => 'Paisaje sereno', 'heading' => 'Lora', 'body' => 'Inter', 'group' => 'editorial'],
        'couture' => ['name' => 'Retrato de autor', 'heading' => 'Bodoni Moda', 'body' => 'Manrope', 'group' => 'editorial'],
        'museum' => ['name' => 'Museo contemporáneo', 'heading' => 'Cormorant Garamond', 'body' => 'DM Sans', 'group' => 'editorial'],
        'magazine' => ['name' => 'Revista visual', 'heading' => 'Playfair Display', 'body' => 'Source Sans 3', 'group' => 'editorial'],
        'journal' => ['name' => 'Cuaderno personal', 'heading' => 'Fraunces', 'body' => 'Lora', 'group' => 'editorial'],
        'heritage' => ['name' => 'Patrimonio visual', 'heading' => 'EB Garamond', 'body' => 'Source Sans 3', 'group' => 'editorial'],
        'portrait' => ['name' => 'Elegancia urbana', 'heading' => 'Bodoni Moda', 'body' => 'Inter', 'group' => 'editorial'],
        'nature' => ['name' => 'Naturaleza editorial', 'heading' => 'Lora', 'body' => 'Manrope', 'group' => 'editorial'],
        'archive' => ['name' => 'Archivo de imágenes', 'heading' => 'Source Serif 4', 'body' => 'DM Sans', 'group' => 'editorial'],
        'quiet' => ['name' => 'Mirada tranquila', 'heading' => 'Cormorant Garamond', 'body' => 'Source Sans 3', 'group' => 'editorial'],
        'urban' => ['name' => 'Urbano limpio', 'heading' => 'Space Grotesk', 'body' => 'Inter', 'group' => 'modern'],
        'studio' => ['name' => 'Estudio creativo', 'heading' => 'Syne', 'body' => 'DM Sans', 'group' => 'modern'],
        'minimal' => ['name' => 'Minimal cálido', 'heading' => 'Outfit', 'body' => 'Lora', 'group' => 'modern'],
        'contemporary' => ['name' => 'Portfolio contemporáneo', 'heading' => 'Manrope', 'body' => 'Source Serif 4', 'group' => 'modern'],
        'precise' => ['name' => 'Precisión visual', 'heading' => 'Plus Jakarta Sans', 'body' => 'Inter', 'group' => 'modern'],
        'documentary' => ['name' => 'Documental moderno', 'heading' => 'Archivo', 'body' => 'Manrope', 'group' => 'modern'],
        'gallery' => ['name' => 'Galería esencial', 'heading' => 'DM Sans', 'body' => 'Source Serif 4', 'group' => 'modern'],
        'soft' => ['name' => 'Luz natural', 'heading' => 'Source Sans 3', 'body' => 'Lora', 'group' => 'modern'],
        'geometry' => ['name' => 'Geometría editorial', 'heading' => 'Space Grotesk', 'body' => 'Source Serif 4', 'group' => 'modern'],
        'bold' => ['name' => 'Portada expresiva', 'heading' => 'Syne', 'body' => 'Lora', 'group' => 'modern'],
        'architecture' => ['name' => 'Arquitectura nítida', 'heading' => 'Archivo', 'body' => 'DM Sans', 'group' => 'modern'],
        'night' => ['name' => 'Nocturno gráfico', 'heading' => 'Outfit', 'body' => 'Roboto Mono', 'group' => 'experimental'],
        'contact' => ['name' => 'Hoja de contactos', 'heading' => 'Roboto Mono', 'body' => 'Inter', 'group' => 'experimental'],
        'analog' => ['name' => 'Laboratorio analógico', 'heading' => 'Fraunces', 'body' => 'Roboto Mono', 'group' => 'experimental'],
        'typewriter' => ['name' => 'Archivo tipográfico', 'heading' => 'Cormorant Garamond', 'body' => 'Roboto Mono', 'group' => 'experimental'],
    ];
}

function site_font_pair(string $id): array
{
    $pairs = site_font_pairs();
    return $pairs[$id] ?? $pairs['classic'];
}
