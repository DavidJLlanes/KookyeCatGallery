<?php
declare(strict_types=1);

return [
    'description' => 'La proporción «masonry» duplicaba el selector de la galería Masonry y se retiró: pasa a «adaptive».',
    'when' => 'always',
    'apply' => static function (array $data): array {
        if (($data['grid'] ?? null) === 'masonry') $data['grid'] = 'adaptive';
        return $data;
    },
];
