<?php
declare(strict_types=1);

return [
    'description' => 'El título de «El proyecto» eran dos textos sueltos (línea 1 y línea 2); ahora es uno solo (text_6636c29ab23b6ec8). Si alguna línea estaba personalizada, el título conserva ambas.',
    'when' => 'always',
    'apply' => static function (array $data): array {
        $texts = $data['texts'] ?? [];
        if (!is_array($texts)) return $data;
        $text = static fn(mixed $value): string => is_string($value) ? trim($value) : '';
        $first = $text($texts['text_6636c29ab23b6ec8'] ?? '');
        $second = $text($texts['text_3d9d76375e115725'] ?? '');
        if ($second !== '') {
            $catalog = function_exists('site_text_catalog') ? site_text_catalog() : [];
            $first = $first !== '' ? $first : (string) ($catalog['text_6636c29ab23b6ec8']['default'] ?? '');
            $texts['text_6636c29ab23b6ec8'] = trim($first . ' ' . $second);
        }
        unset($texts['text_3d9d76375e115725']);
        $data['texts'] = $texts;
        return $data;
    },
];
