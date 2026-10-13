<?php
declare(strict_types=1);

return [
    'description' => 'El nombre de la web era varios textos sueltos (título de la portada en dos líneas, «Título de la web» y nombre del sitio); ahora es uno solo (text_52179dc42df7efe5) que se ve en la portada, el pie de página y el panel. Si no había nombre propio, se conserva el que salía hasta ahora.',
    'when' => 'always',
    'apply' => static function (array $data): array {
        $texts = $data['texts'] ?? [];
        // Datos mal formados: no se tocan, el validador los rechazará con su mensaje.
        if (!is_array($texts)) return $data;
        $key = 'text_52179dc42df7efe5';
        // Valores que no son texto (datos mal formados) se tratan como vacíos; el validador los rechazará después.
        $text = static fn(mixed $value): string => is_string($value) ? trim($value) : '';
        $first = $text($texts['text_cbbba360eb60dd04'] ?? '');
        $second = $text($texts['text_2c1ac3057629f681'] ?? '');
        if ($text($texts[$key] ?? '') === '') {
            $name = '';
            if ($first !== '' || $second !== '') {
                // Se personalizó alguna línea del título: el nombre es el título completo (la línea que falta, la de siempre).
                $catalog = function_exists('site_text_catalog') ? site_text_catalog() : [];
                $name = trim(($first !== '' ? $first : (string) ($catalog['text_cbbba360eb60dd04']['default'] ?? ''))
                    . ' ' . ($second !== '' ? $second : (string) ($catalog['text_2c1ac3057629f681']['default'] ?? '')));
            } elseif (is_string($data['site_title'] ?? null) && trim($data['site_title']) !== '') {
                $name = trim($data['site_title']);
            }
            if ($name !== '') $texts[$key] = $name;
        }
        // Las dos líneas del título ya no son textos propios: salen del nombre.
        unset($texts['text_cbbba360eb60dd04'], $texts['text_2c1ac3057629f681']);
        $data['texts'] = $texts;
        return $data;
    },
];
